<?php declare(strict_types=1);
namespace Tests\functional\Template\BlockHandle\Blocks\Storage\Driver\S3;

use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;

/**
 * @since SRT-187
 */
class S3BlockTestCase extends TestCase
{
    protected const TEMP_PATH = '/tmp/tests-file';
    protected const KEY = 'test-key';

    protected static $connectionInfo = [
        [
            'region' => 'ap-northeast-2',
            'version' => 'latest',
            'credentials' => [
                'key' => '',
                'secret' => ''
            ]
        ],
    ];

    /**
     * 테스트 실행 환경에 AWS_* 환경변수 세팅이 필요하다.
     */
    public static function setUpBeforeClass(): void
    {
        $key = getenv('AWS_S3_KEY');
        $secret = getenv('AWS_S3_SECRET');
        $bucket = getenv('AWS_S3_BUCKET');

        self::$connectionInfo[0]['credentials']['key'] = $key;
        self::$connectionInfo[0]['credentials']['secret'] = $secret;
        self::$connectionInfo[] = $bucket;
    }

    protected function setUp(): void
    {
        $this->assertNotEmpty(self::$connectionInfo[0]['credentials']['key']);
        $this->assertNotEmpty(self::$connectionInfo[0]['credentials']['key']);
        $this->assertNotEmpty(self::$connectionInfo[1]);
    }

    public function test_env_available()
    {
        $client = new S3Client(self::$connectionInfo[0]);

        $result = $client->listBuckets();

        $this->assertNotEmpty($result);

        $buckets = array_map(function ($i) { return $i['Name']; }, $result->get('Buckets'));

        $this->assertContains(self::$connectionInfo[1], $buckets);
    }

    /**
     * todo: RandomGenerator 클래스로 통합
     *
     * @param $length
     * @return string
     * @since SRT-219
     */
    public static function randomHex($length): string
    {
        return bin2hex(openssl_random_pseudo_bytes($length));
    }
}