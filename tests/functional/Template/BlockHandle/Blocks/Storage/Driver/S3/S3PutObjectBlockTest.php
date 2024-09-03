<?php declare(strict_types=1);
namespace Tests\functional\Template\BlockHandle\Blocks\Storage\Driver\S3;

use Aws\S3\S3Client;
use DateInterval;
use DateTime;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\Parameters\S3ObjectParamContentEncoding;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\Parameters\S3ObjectParamContentType;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\Parameters\S3ObjectParamExpires;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\Parameters\S3ObjectParamStorageClass;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\S3Create;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\S3PutObject;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Tests\libraries\RandomUtility;
use Throwable;

/**
 * @since SRT-187
 */
class S3PutObjectBlockTest extends S3BlockTestCase
{
    /** @var string 매 테스트마다 새로 생성하는 키 */
    private static $randomKey;

    /** @var array 테스트 종료 시 전체 삭제를 위해 키를 보관. */
    private static $randomKeys = [];

    /** @var string */
    private static $randomData;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::$randomKey = bin2hex(openssl_random_pseudo_bytes(20));
        self::$randomData = self::TEMP_PATH . '/' . bin2hex(openssl_random_pseudo_bytes(20));

    }

    public static function tearDownAfterClass(): void
    {
        $keys = array_map(function($i) {return ['Key' => $i];}, self::$randomKeys);

        $client = new S3Client(self::$connectionInfo[0]);
        $client->deleteObjects([
            'Bucket' => self::$connectionInfo[1],
            'Delete' => [
                'Objects' => $keys
            ]
        ]);
    }

    public function test_env_available()
    {
        parent::test_env_available();

        $client = new S3Client(self::$connectionInfo[0]);
        $this->assertFalse($client->doesObjectExist(self::$connectionInfo[1], self::$randomKey));
    }

    /**
     * @test
     * @testdox `put`블럭은 지정된 키를 가지고 S3에 오브젝트를 생성한다.
     * @depends test_env_available
     * @throws ISynctreeException
     * @throws Throwable
     */
    public function put_block_posts_an_object()
    {
        self::$randomKeys[] = self::$randomKey;

        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $connInfo = $this->createStub(IBlock::class);
        $connInfo->method('do')->willReturn(self::$connectionInfo);

        $bucket = $this->createStub(IBlock::class);
        $bucket->method('do')->willReturn(self::$connectionInfo[1]);

        $key = $this->createStub(IBlock::class);
        $key->method('do')->willReturn(self::$randomKey);

        $source = $this->createStub(IBlock::class);
        $source->method('do')->willReturn(self::$randomData);

        $handleCreator = new S3Create($storage, $extra, $connInfo);
        $sut = new S3PutObject($storage, $extra, $handleCreator, $key, $source, $bucket);

        $blockStorage = [];
        $result = $sut->do($blockStorage);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertIsString($result['ObjectURL']);

        $client = new S3Client(self::$connectionInfo[0]);
        $resultRaw = $client->getObject([
            'Bucket' => self::$connectionInfo[1],
            'Key' => self::$randomKey
        ]);

        $contents = $resultRaw->get('Body')->getContents();
        $this->assertEquals(self::$randomData, $contents);
    }

    /**
     * @test
     * @testdox `put`블럭에 옵션을 지정할 경우 s3에 생성된 객체에 해당 내용이 반영된다.
     * @depends test_env_available
     * @throws Throwable
     * @throws ISynctreeException
     */
    public function put_block_with_options_creates_an_object_having_metadata()
    {
        self::$randomKeys[] = self::$randomKey;

        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $connInfo = $this->createStub(IBlock::class);
        $connInfo->method('do')->willReturn(self::$connectionInfo);

        $bucket = $this->createStub(IBlock::class);
        $bucket->method('do')->willReturn(self::$connectionInfo[1]);

        $key = $this->createStub(IBlock::class);
        $key->method('do')->willReturn(self::$randomKey);

        $source = $this->createStub(IBlock::class);
        $source->method('do')->willReturn(self::$randomData);

        $metadata = $this->createMock(IBlock::class);
        $metadata->method('do')
            ->willReturn(['Metadata', ['random_key' => self::$randomKey]]);

        $options = new BlockAggregator($metadata);

        $handleCreator = new S3Create($storage, $extra, $connInfo);
        $sut = new S3PutObject($storage, $extra, $handleCreator, $key, $source, $bucket, $options);

        $blockStorage = [];
        $result = $sut->do($blockStorage);

        $this->assertNotEmpty($result);

        $client = new S3Client(self::$connectionInfo[0]);
        $resultRaw = $client->getObject([
            'Bucket' => self::$connectionInfo[1],
            'Key' => self::$randomKey
        ]);

        $contents = $resultRaw->get('Body')->getContents();
        $this->assertEquals(self::$randomData, $contents);

        $resultMeta = $resultRaw->get('Metadata');

        $this->assertEquals(self::$randomKey, $resultMeta['random_key']);
    }

    /**
     * @test
     * @testdox Content-Type 블럭을 사용한 Put 블럭은 지정된 `MimeType`이 설정된 객체를 만든다.
     * @depends test_env_available
     * @throws Throwable
     * @throws ISynctreeException
     * @dataProvider provideContent
     */
    public function block_with_content_type_param_creates_an_object_has_specified_mime_type(
        string $contentType,
        string $content)
    {
        self::$randomKeys[] = self::$randomKey;

        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $connInfo = $this->createStub(IBlock::class);
        $connInfo->method('do')->willReturn(self::$connectionInfo);

        $bucket = $this->createStub(IBlock::class);
        $bucket->method('do')->willReturn(self::$connectionInfo[1]);

        $key = $this->createStub(IBlock::class);
        $key->method('do')->willReturn(self::$randomKey);

        $source = $this->createStub(IBlock::class);
        $source->method('do')->willReturn($content);

        $mimeType = $this->createStub(IBlock::class);
        $mimeType->method('do')->willReturn($contentType);

        $options = new BlockAggregator(
            new S3ObjectParamContentType($storage, $extra, $mimeType)
        );

        $handleCreator = new S3Create($storage, $extra, $connInfo);
        $sut = new S3PutObject($storage, $extra, $handleCreator, $key, $source, $bucket, $options);

        $blockStorage = [];
        $result = $sut->do($blockStorage);

        $this->assertNotEmpty($result);

        $client = new S3Client(self::$connectionInfo[0]);
        $resultRaw = $client->getObject([
            'Bucket' => self::$connectionInfo[1],
            'Key' => self::$randomKey
        ]);

        $resultContent = $resultRaw->get('Body')->getContents();
        $this->assertEquals($content, $resultContent);

        $resultMeta = $resultRaw->get('ContentType');
        $this->assertEquals($contentType, $resultMeta);
    }

    public function provideContent(): iterable
    {
        yield 'json' => ['application/json', '{"hello": "world!"}'];
        yield 'plain text' => ['text/plain', bin2hex(openssl_random_pseudo_bytes(20))];
        yield 'csv' => ['text/csv', "a,b,c,d\n1,2,3,4"];
    }

    /**
     * @test
     * @testdox StorageClass 블럭을 사용한 Put 블럭은 지정된 `StorageClass`가 설정된 객체를 만든다.
     * @depends test_env_available
     * @throws Throwable
     * @throws ISynctreeException
     * @dataProvider provideStorageClasses
     */
    public function block_with_storage_class_creates_an_object_has_that(
        string $storageClass,
        string $comparer)
    {
        self::$randomKeys[] = self::$randomKey;

        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $connInfo = $this->createStub(IBlock::class);
        $connInfo->method('do')->willReturn(self::$connectionInfo);

        $bucket = $this->createStub(IBlock::class);
        $bucket->method('do')->willReturn(self::$connectionInfo[1]);

        $key = $this->createStub(IBlock::class);
        $key->method('do')->willReturn(self::$randomKey);

        $source = $this->createStub(IBlock::class);
        $source->method('do')->willReturn(self::$randomData);

        $mimeType = $this->createStub(IBlock::class);
        $mimeType->method('do')->willReturn($storageClass);

        $options = new BlockAggregator(
            new S3ObjectParamStorageClass($storage, $extra, $mimeType)
        );

        $handleCreator = new S3Create($storage, $extra, $connInfo);
        $sut = new S3PutObject($storage, $extra, $handleCreator, $key, $source, $bucket, $options);

        $blockStorage = [];
        $result = $sut->do($blockStorage);

        $this->assertNotEmpty($result);

        $client = new S3Client(self::$connectionInfo[0]);
        $resultRaw = $client->getObject([
            'Bucket' => self::$connectionInfo[1],
            'Key' => self::$randomKey
        ]);

        $resultMeta = $resultRaw->get('StorageClass');
        $this->assertEquals($comparer, $resultMeta);
    }

    /**
     * Glacier 등의 `storage class`는 빈번한 삭제 시 비용이 커지므로 테스트하지 않는다.
     */
    public function provideStorageClasses(): iterable
    {
        // standard 클래스는 StorageClass 확인 시 `empty string`을 돌려준다.
        yield 'Standard' => ['STANDARD', ''];
        yield 'INTELLIGENT_TIERING' => ['INTELLIGENT_TIERING', 'INTELLIGENT_TIERING'];
    }

    /**
     * @test
     * @testdox Expires 블럭 사용 시 Cache expiry 설정이 적용된다.
     * @depends test_env_available
     * @throws Throwable
     * @throws ISynctreeException
     */
    public function block_with_expiry_param_creates_an_object_has_expiry_meta()
    {
        self::$randomKeys[] = self::$randomKey;

        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $connInfo = $this->createStub(IBlock::class);
        $connInfo->method('do')->willReturn(self::$connectionInfo);

        $bucket = $this->createStub(IBlock::class);
        $bucket->method('do')->willReturn(self::$connectionInfo[1]);

        $key = $this->createStub(IBlock::class);
        $key->method('do')->willReturn(self::$randomKey);

        $source = $this->createStub(IBlock::class);
        $source->method('do')->willReturn(self::$randomData);

        $expires = $this->createStub(IBlock::class);
        $timestamp = (new DateTime())->add(new DateInterval('PT1H'));
        $timestamp = (new DateTime())->setTime(
            (int)$timestamp->format('G'),
            (int)$timestamp->format('i'),
            (int)$timestamp->format('s'),
            0);

        $expires->method('do')->willReturn($timestamp->getTimestamp());

        $options = new BlockAggregator(
            new S3ObjectParamExpires($storage, $extra, $expires)
        );

        $handleCreator = new S3Create($storage, $extra, $connInfo);
        $sut = new S3PutObject($storage, $extra, $handleCreator, $key, $source, $bucket, $options);

        $blockStorage = [];
        $result = $sut->do($blockStorage);

        $this->assertNotEmpty($result);

        $client = new S3Client(self::$connectionInfo[0]);
        $resultRaw = $client->getObject([
            'Bucket' => self::$connectionInfo[1],
            'Key' => self::$randomKey
        ]);

        $contents = $resultRaw->get('Body')->getContents();
        $this->assertEquals(self::$randomData, $contents);

        $resultExpires = $resultRaw->get('Expires');

        $this->assertNotEmpty($resultExpires);
        $this->assertEquals($timestamp, $resultExpires);
    }

    /**
     * @test
     * @testdox content-encoding 파라미터 설정된 블럭은 객체 전송 방식이 변경된다.
     * @depends block_with_content_type_param_creates_an_object_has_specified_mime_type
     * @dataProvider imageProvider
     * @throws Throwable
     * @throws ISynctreeException
     */
    public function block_with_content_encoding_affects_on_object_transfer(
        $encodingMethod, $dataSource)
    {
        // arrange
        self::$randomKeys[] = self::$randomKey;

        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $connInfo = $this->createStub(IBlock::class);
        $connInfo->method('do')->willReturn(self::$connectionInfo);

        $bucket = $this->createStub(IBlock::class);
        $bucket->method('do')->willReturn(self::$connectionInfo[1]);

        $key = $this->createStub(IBlock::class);
        $key->method('do')->willReturn(self::$randomKey);

        $source = $this->createStub(IBlock::class);
        $source->method('do')->willReturn($dataSource);

        $mimeType = $this->createStub(IBlock::class);
        $mimeType->method('do')->willReturn('image/png');
        $encoding = $this->createStub(IBlock::class);
        $encoding->method('do')->willReturn($encodingMethod);

        $options = new BlockAggregator(
            new S3ObjectParamContentType($storage, $extra, $mimeType),
            new S3ObjectParamContentEncoding($storage, $extra, $encoding)
        );

        $handleCreator = new S3Create($storage, $extra, $connInfo);
        $sut = new S3PutObject($storage, $extra, $handleCreator, $key, $source, $bucket, $options);

        // act
        $blockStorage = [];
        $result = $sut->do($blockStorage);

        // assert
        $this->assertNotEmpty($result);

        $client = new S3Client(self::$connectionInfo[0]);

        $randomFilename = (new RandomUtility())->generateHex(5);
        $randomFilename = "/tmp/$randomFilename.png";
        $client->getObject([
            'Bucket' => self::$connectionInfo[1],
            'Key' => self::$randomKey,
            'SaveAs' => $randomFilename
        ]);

        $this->assertFileExists($randomFilename);
        $this->assertEquals(IMAGETYPE_PNG, exif_imagetype($randomFilename));
    }

    public function imageProvider(): iterable
    {
        $dataSource = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+P+/HgAFhAJ/wlseKgAAAABJRU5ErkJggg==';

        yield '1px png' => [
            'gzip',
            gzencode(base64_decode($dataSource), 6)
        ];
    }
}