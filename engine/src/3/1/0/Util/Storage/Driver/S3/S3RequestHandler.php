<?php declare(strict_types=1);

namespace Ntuple\Synctree\Util\Storage\Driver\S3;

use Aws\Exception\AwsException;
use Aws\Result;
use Aws\S3\S3Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Stream;

/**
 * 실제 요청을 처리하고 {@link AwsException}이 발생한 경우 {@link S3StorageException}으로 변환한다.
 *
 * @since SRT-186
 */
class S3RequestHandler
{
    private $client;

    public function __construct(S3Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param array $request
     * @return array ['metadata' => array, ('body' => {contents})]
     */
    public function getObject(array $request): array
    {
        try {
            $result = $this->client->getObject($request);

            $data = $result->toArray();

            unset($data['Body']);

            $output = [
                'metadata' => $data,
            ];

            if (empty($request['SaveAs'])) {
                /** @var Stream $body */
                $body = $result->get('Body');
                $output['body'] = $body->getContents();
            }

            return $output;
        } catch (AwsException $ex) {
            throw new S3StorageException('S3-Get-Object: '.$ex->getAwsErrorMessage(), 0, $ex);
        } catch (RequestException $ex) {
            throw new S3StorageException($ex->getMessage(), 0, $ex);
        }
    }

    /**
     * @param array $request
     * @return array response as array
     */
    public function putObject(array $request): array
    {
        try {
            $result =  $this->client->putObject($request);
            return $result->toArray();
        } catch (AwsException $ex) {
            throw new S3StorageException('S3-Put-Object: '.$ex->getAwsErrorMessage(), 0, $ex);
        } catch (RequestException $ex) {
            throw new S3StorageException($ex->getMessage(), 0, $ex);
        }
    }

    /**
     * @param array $request
     * @return array response as array
     */
    public function deleteObject(array $request): array
    {
        try {
            $result = $this->client->deleteObject($request);
            return $result->toArray();
        } catch (AwsException $ex) {
            throw new S3StorageException('S3-Delete-Object: '.$ex->getAwsErrorMessage(), 0, $ex);
        } catch (RequestException $ex) {
            throw new S3StorageException($ex->getMessage(), 0, $ex);
        }
    }

    /**
     * @since SRT-219
     * @param array $request
     * @return array [{ key, owner, size, storage class, ... }, ...]
     */
    public function getObjectList(array $request): array
    {
        try {
            $result = $this->client->listObjectsV2($request);
            return $result->toArray();
        } catch (AwsException $ex) {
            throw new S3StorageException('S3-List-Objects: '.$ex->getAwsErrorMessage(), 0, $ex);
        } catch (RequestException $ex) {
            throw new S3StorageException($ex->getMessage(), 0, $ex);
        }
    }
}