<?php declare(strict_types=1);
namespace Tests\functional\Template\BlockHandle\Blocks\Storage\Driver\S3;

use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Stream;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\StorageException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\S3Create;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\S3GetObject;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\File\Adapter\IAdapter;

/**
 * @since SRT-186
 */
class S3GetObjectBlockTest extends S3BlockTestCase
{
    /** @var string */
    private static $randomKey;
    /** @var string */
    private static $randomData;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$randomKey =  self::randomHex(20);
        self::$randomData = self::TEMP_PATH . '/' . self::randomHex(20);

        $client = new S3Client(self::$connectionInfo[0]);
        $client->putObject([
            'Bucket' => self::$connectionInfo[1],
            'Key' => self::$randomKey,
            'Body' => self::$randomData
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
     * @testdox 블럭은 invalid credential 입력에 대해 {@link StorageException}을 던진다.
     * @throws ISynctreeException
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

        $key = $this->createStub(IBlock::class);
        $key->method('do')->willReturn(self::$randomKey);

        $handleCreator = new S3Create($storage, $extra, $connInfo);
        $sut = new S3GetObject($storage, $extra, $handleCreator, $key, $bucket);

        // assert
        $this->expectException(StorageException::class);

        // act
        $blockStorage = [];
        $sut->do($blockStorage);
    }

    /**
     * @test
     * @testdox 블럭은 존재하지 않는 bucket name 입력에 대해 {@link StorageException}을 던진다.
     * @throws ISynctreeException
     */
    public function block_fails_with_invalid_bucket_name()
    {
        // arrange
        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $connInfo = $this->createStub(IBlock::class);

        $connectionInfo = self::$connectionInfo;
        $connectionInfo[1] = self::randomHex(10);

        $connInfo->method('do')->willReturn($connectionInfo);

        $bucket = $this->createStub(IBlock::class);
        $bucket->method('do')->willReturn(null);

        $key = $this->createStub(IBlock::class);
        $key->method('do')->willReturn(self::$randomKey);

        $handleCreator = new S3Create($storage, $extra, $connInfo);
        $sut = new S3GetObject($storage, $extra, $handleCreator, $key, $bucket);

        // assert
        $this->expectException(StorageException::class);

        // act
        $blockStorage = [];
        $sut->do($blockStorage);
    }

    /**
     * @test
     * @testdox response 배열에서 바로 데이터를 획득
     * @depends test_env_available
     * @throws ISynctreeException
     */
    public function get_block_retrieves_target_object()
    {
        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $connInfo = $this->createStub(IBlock::class);
        $connInfo->method('do')->willReturn(self::$connectionInfo);

        $bucket = $this->createStub(IBlock::class);
        $bucket->method('do')->willReturn(self::$connectionInfo[1]);

        $key = $this->createStub(IBlock::class);
        $key->method('do')->willReturn(self::$randomKey);

        $handleCreator = new S3Create($storage, $extra, $connInfo);
        $sut = new S3GetObject($storage, $extra, $handleCreator, $key, $bucket);

        $blockStorage = [];
        $result = $sut->do($blockStorage);

        $this->assertNotEmpty($result);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('metadata', $result);

        $this->assertNotEmpty($result['body']);
        $this->assertEquals(self::$randomData, $result['body']);
    }

    /**
     * @test
     * @testdox 파일 어댑터 이외의 블럭을 `dest`로 지정하면 {@link InvalidArgumentException} 발생
     * @throws ISynctreeException
     */
    public function get_block_throws_when_destination_is_not_file_adaptor()
    {
        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $connInfo = $this->createStub(IBlock::class);
        $connInfo->method('do')->willReturn(self::$connectionInfo);

        $key = $this->createStub(IBlock::class);
        $key->method('do')
            ->willReturn(self::KEY);

        $dest = $this->createMock(IBlock::class);
        $dest->method('do')
            ->willReturn('/tmp/'.self::randomHex(20));

        $handleCreator = new S3Create($storage, $extra, $connInfo);
        $sut = new S3GetObject($storage, $extra, $handleCreator, $key, null, $dest);

        $this->expectException(InvalidArgumentException::class);
        $blockStorage = [];
        $sut->do($blockStorage);
    }

    /**
     * @test
     * @testdox get 블럭에 destination(saveAs) 지정하면 해당 경로에 파일로 저장한다.
     * @depends get_block_retrieves_target_object
     * @throws ISynctreeException
     */
    public function get_block_with_path_saves_target_object_as_file()
    {
        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);


        $connInfo = $this->createStub(IBlock::class);
        $connInfo->method('do')->willReturn(self::$connectionInfo);

        $bucket = $this->createStub(IBlock::class);
        $bucket->method('do')
            ->willReturn(self::$connectionInfo[1]);

        $key = $this->createStub(IBlock::class);
        $key->method('do')
            ->willReturn(self::$randomKey);

        $destination = $this->createStub(IBlock::class);
        $adapter = $this->createStub(IAdapter::class);
        $adapter->method('getFile')->willReturn(self::TEMP_PATH);
        $destination->method('do')->willReturn($adapter);

        $handleCreator = new S3Create($storage, $extra, $connInfo);
        $sut = new S3GetObject($storage, $extra, $handleCreator, $key, $bucket, $destination);

        $blockStorage = [];
        $result = $sut->do($blockStorage);

        $this->assertNotEmpty($result);
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('body', $result);
        $this->assertArrayHasKey('metadata', $result);

        $this->assertTrue(file_exists(self::TEMP_PATH));
    }
}