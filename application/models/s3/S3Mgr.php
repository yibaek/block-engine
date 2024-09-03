<?php
namespace models\s3;

use Aws\S3\S3Client;
use libraries\log\LogMessage;
use libraries\util\CommonUtil;
use Throwable;

class S3Mgr
{
    public const ACL_PUBLIC_READ = 'public-read';
    public const ACL_PRIVATE = 'private';

    private $client;

    /**
     * S3Mgr constructor.
     * @param array|null $config
     */
    public function __construct(array $config = null) {
        // load config
        if (empty($config)) {
            $config = $this->getConfig();
        }

        $this->client = new S3Client([
            'region' => $config['region'],
            'version' => $config['version'],
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret']
            ]
        ]);
    }

    /**
     * @param string $bucket
     * @param string $prefix
     * @param bool $isJson
     * @return bool|mixed|string
     * @throws Throwable
     */
    public function getObject(string $bucket, string $prefix, bool $isJson = false)
    {
        try {
            // register stream wrapper
            $this->client->registerStreamWrapper();

            // read on stream
            $resData = '';
            if (false !== ($stream=fopen('s3://'.$bucket.'/'.$prefix, 'rb'))) {
                while (!feof($stream)) {
                    $resData .= fread($stream, 1024);
                }

                // close stream
                fclose($stream);

                if (0 >= strlen($resData)) {
                    return false;
                }

                // remove bom
                $resData = CommonUtil::removeBom($resData);
            }

            if (true === $isJson) {
                $resData = json_decode($resData, true, 512, JSON_THROW_ON_ERROR);
            }

            return $resData;
        } catch (Throwable $ex) {
            // logging
            LogMessage::exception($ex, $bucket.'/'.$prefix);
            throw $ex;
        }
    }

    /**
     * @param string $bucket
     * @param string $prefix
     * @param string $filePath
     * @param string $acl
     * @return mixed
     * @throws Throwable
     */
    public function putObjectFromPath(string $bucket, string $prefix, string $filePath, string $acl = self::ACL_PUBLIC_READ)
    {
        try {
            $resData = $this->client->putObject([
                'Bucket' => $bucket,
                'Key' => $prefix,
                'ACL' => $acl,
                'Body' => fopen($filePath, 'rb'),
                'ContentType' => mime_content_type($filePath)
            ]);

            return $resData->toArray();
        } catch (Throwable $ex) {
            // logging
            LogMessage::exception($ex, $bucket.'/'.$prefix);
            throw $ex;
        }
    }

    /**
     * @param string $bucket
     * @param string $prefix
     * @param string $rawData
     * @param string $contentType
     * @param string $acl
     * @return mixed
     * @throws Throwable
     */
    public function putObjectFromData(string $bucket, string $prefix, string $rawData, string $contentType, string $acl = self::ACL_PUBLIC_READ)
    {
        try {
            $resData = $this->client->putObject([
                'Bucket' => $bucket,
                'Key' => $prefix,
                'ACL' => $acl,
                'Body' => $rawData,
                'ContentType' => $contentType
            ]);

            return $resData->toArray();
        } catch (Throwable $ex) {
            // logging
            LogMessage::exception($ex, $bucket.'/'.$prefix);
            throw $ex;
        }
    }

    /**
     * @return array
     */
    private function getConfig(): array
    {
        $credential = CommonUtil::getCredentialConfig('s3');

        $config = include APP_DIR . 'config/' . APP_ENV . '.php';
        $config = $config['settings']['s3'];

        return array_merge($credential, $config);
    }
}