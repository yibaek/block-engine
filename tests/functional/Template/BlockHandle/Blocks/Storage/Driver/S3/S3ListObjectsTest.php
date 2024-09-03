<?php declare(strict_types=1);
namespace Tests\functional\Template\BlockHandle\Blocks\Storage\Driver\S3;

use Aws\S3\S3Client;
use Exception;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\StorageException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\S3Create;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\S3ListObjects;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

/**
 * Set of functional tests for {@link S3ListObjects}
 *
 * @since SRT-219
 */
class S3ListObjectsTest extends S3BlockTestCase
{
    /** @var string */
    private static $randomKey;
    /** @var string */
    private static $continuationKey;
    /** @var string */
    private static $lastKey;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$randomKey =  self::randomHex(20);

        $client = new S3Client(self::$connectionInfo[0]);
        $client->putObject([
            'Bucket' => self::$connectionInfo[1],
            'Key' => self::$randomKey,
            'Body' => self::TEMP_PATH . '/' . self::randomHex(20)
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        $client = new S3Client(self::$connectionInfo[0]);
        $client->deleteObject([
            'Bucket' => self::$connectionInfo[1],
            'Key' => self::$randomKey
        ]);
    }

    /**
     * @test
     * @testdox 블럭은 지정된 버킷에 들어있는 object 목록을 가져온다.
     * @throws ISynctreeException
     * @depends test_env_available
     */
    public function block_retrieves_object_list_of_specified_bucket(): void
    {
        // arrange
        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $connInfo = $this->createStub(IBlock::class);
        $connInfo->method('do')->willReturn(self::$connectionInfo);

        $bucket = $this->createStub(IBlock::class);
        $bucket->method('do')->willReturn(self::$connectionInfo[1]);

        $handleCreator = new S3Create($storage, $extra, $connInfo);
        $sut = new S3ListObjects($storage, $extra, $handleCreator, $bucket);

        // act
        $blockStorage = [];
        $result = $sut->do($blockStorage);

        // assert
        $this->assertNotEmpty($result);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('Contents', $result);
        $this->assertArrayHasKey('MaxKeys', $result);
        $this->assertArrayHasKey('KeyCount', $result);
    }

    /**
     * @test
     * @testdox 블럭은 invalid credential 입력에 대해 {@link StorageException}을 던진다.
     * @throws ISynctreeException
     * @depends test_env_available
     */
    public function block_fails_with_invalid_credential()
    {
        // arrange
        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $connInfo = $this->createStub(IBlock::class);
        $connInfo->method('do')->willReturn([
            [
                'region' => 'ap-northeast-2',
                'version' => 'latest',
                'credentials' => [
                    'key' =>  self::randomHex(10),
                    'secret' =>  self::randomHex(20)
                ]
            ],
            'dummy'
        ]);

        $bucket = $this->createStub(IBlock::class);
        $bucket->method('do')->willReturn(self::$connectionInfo[1]);

        $handleCreator = new S3Create($storage, $extra, $connInfo);
        $sut = new S3ListObjects($storage, $extra, $handleCreator, $bucket);

        // assert
        $this->expectException(StorageException::class);

        // act
        $blockStorage = [];
        $sut->do($blockStorage);
    }

    /**
     * @test
     * @testdox 블럭은 존재하지 않는 버킷 이름이 지정된 경우 {@link StorageException}을 던진다.
     * @throws ISynctreeException
     * @depends test_env_available
     */
    public function block_fails_with_name_of_bucket_which_not_exists(): void
    {
        // arrange
        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $connInfo = $this->createStub(IBlock::class);
        $connInfo->method('do')->willReturn(self::$connectionInfo);

        $bucket = $this->createStub(IBlock::class);
        $bucket->method('do')->willReturn(self::randomHex(10));

        $handleCreator = new S3Create($storage, $extra, $connInfo);
        $sut = new S3ListObjects($storage, $extra, $handleCreator, $bucket);

        // assert
        $this->expectException(StorageException::class);

        // act
        $blockStorage = [];
        $sut->do($blockStorage);
    }

    /**
     * @test
     * @testdox max keys 지정된 블럭은 해당 값을 결과에 포함한다.
     * @throws Exception random_int error
     * @throws ISynctreeException
     */
    public function block_with_max_keys_returns_specified_value_as_result()
    {
        // arrange
        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $connInfo = $this->createStub(IBlock::class);
        $connInfo->method('do')->willReturn(self::$connectionInfo);

        $bucket = $this->createStub(IBlock::class);
        $bucket->method('do')->willReturn(self::$connectionInfo[1]);

        $maxKeys = $this->createStub(IBlock::class);
        $randomValue = random_int(0, S3ListObjects::MAX_KEYS);
        $maxKeys->method('do')->willReturn($randomValue);

        $handleCreator = new S3Create($storage, $extra, $connInfo);
        $sut = new S3ListObjects($storage, $extra, $handleCreator, $bucket, $maxKeys);

        // act
        $blockStorage = [];
        $result = $sut->do($blockStorage);

        // assert
        $this->assertNotEmpty($result);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('MaxKeys', $result);

        $this->assertEquals($randomValue, $result['MaxKeys']);
    }

    /**
     * @test
     * @testdox 1개 이상 object 담고있는 버킷에 대해 1개 max-key 값으로 List 요청하면
     *          결과 데이터에 `continuation token`이 포함된다.
     * @throws Exception random_int error
     * @throws ISynctreeException
     * @depends block_with_max_keys_returns_specified_value_as_result
     */
    public function block_requests_1_max_key_returns_next_continuation_token()
    {
        // arrange
        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $connInfo = $this->createStub(IBlock::class);
        $connInfo->method('do')->willReturn(self::$connectionInfo);

        $bucket = $this->createStub(IBlock::class);
        $bucket->method('do')->willReturn(self::$connectionInfo[1]);

        $maxKeys = $this->createStub(IBlock::class);
        $maxKeys->method('do')->willReturn(1);

        $handleCreator = new S3Create($storage, $extra, $connInfo);
        $sut = new S3ListObjects(
            $storage, $extra, $handleCreator, $bucket, $maxKeys);

        // act
        $blockStorage = [];
        $result = $sut->do($blockStorage);

        // assert
        $this->assertNotEmpty($result);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('IsTruncated', $result);
        $this->assertTrue($result['IsTruncated']);
        $this->assertArrayHasKey('NextContinuationToken', $result);

        self::$continuationKey = $result['NextContinuationToken'];
        self::$lastKey = $result['Contents'][0]['Key'];
    }

    /**
     * @test
     * @testdox `continuation token`이 설정된 블럭의 실행은 이전 요청 다음의 결과 셋을 반환한다.
     * @depends block_requests_1_max_key_returns_next_continuation_token
     * @throws ISynctreeException
     */
    public function block_with_continuation_token_returns_specified_value_as_result()
    {
        // arrange
        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $connInfo = $this->createStub(IBlock::class);
        $connInfo->method('do')->willReturn(self::$connectionInfo);

        $bucket = $this->createStub(IBlock::class);
        $bucket->method('do')->willReturn(self::$connectionInfo[1]);

        $maxKeys = $this->createStub(IBlock::class);
        $maxKeys->method('do')->willReturn(1);

        $token = $this->createStub(IBlock::class);
        $token->method('do')->willReturn(self::$continuationKey);

        $handleCreator = new S3Create($storage, $extra, $connInfo);
        $sut = new S3ListObjects(
            $storage, $extra, $handleCreator, $bucket, $maxKeys, $token);

        // act
        $blockStorage = [];
        $result2 = $sut->do($blockStorage);

        // assert
        $this->assertNotEmpty($result2);
        $this->assertIsArray($result2);
        $this->assertNotEmpty(self::$lastKey);
        $this->assertNotEquals(self::$lastKey, $result2['Contents'][0]['Key']);
    }
}