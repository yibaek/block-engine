<?php
namespace Ntuple\Synctree\Util;

use libraries\constant\CommonConst;
use Ntuple\Synctree\Exceptions\FileNotFoundException;
use Psr\Http\Message\ServerRequestInterface as Request;

class CommonUtil
{
    /**
     * @param string $field
     * @return string
     */
    public static function getSecureConfig(string $field): string
    {
        $config = include APP_DIR . 'config/' . APP_ENV . '.php';
        $inis = parse_ini_file($config['settings']['secure']['file_path'], true);

        if (!isset($inis[$field])) {
            throw new \RuntimeException('failed to load config file(field:' . $field . ')');
        }

        return $inis[$field];
    }

    /**
     * @param string $field
     * @return array
     */
    public static function getCredentialConfig(string $field): array
    {
        $config = include APP_DIR . 'config/' . APP_ENV . '.php';
        $inis = parse_ini_file($config['settings']['credentials']['file_path'], true);

        if (!isset($inis[$field])) {
            throw new \RuntimeException('failed to load credential config file(field:' . $field . ')');
        }

        return $inis[$field];
    }

    /**
     * @return array
     */
    public static function getLoggerConfig(): array
    {
        $config = include APP_DIR . 'config/' . APP_ENV . '.php';
        return $config['settings']['logger'];
    }

    /**
     * @param Request $request
     * @return array|bool|object|string|null
     */
    public static function getParams(Request $request)
    {
        switch (strtoupper($request->getMethod())) {
            case 'POST':
                $params = self::getParsedBody($request);
                if (false === $params) {
                    $params = self::getContents($request);
                }
                break;

            case 'GET':
                $params = self::getQueryParams($request);
                break;

            default:
                throw new \RuntimeException('not allow http method(method:' . $request->getMethod() . ')');
        }

        return $params;
    }

    /**
     * @param array $headers
     * @return array
     */
    public static function getHeaders(array $headers): array
    {
        return self::replaceRawHeaders(self::reOrderHeader($headers));
    }

    /**
     * @param array $headers
     * @return array
     */
    public static function reOrderHeader(array $headers): array
    {
        $resData = [];
        foreach ($headers as $key=>$header) {
            $resData[$key] = $header[0];
        }

        return $resData;
    }

    /**
     * @param bool $isAddSec
     * @param int $length
     * @return string
     */
    public static function getUsec(bool $isAddSec = false, int $length = 6): string
    {
        [$usec, $sec] = explode(' ', microtime());

        $usec = substr($usec, strpos($usec, '.')+1, $length);
        if (true === $isAddSec) {
            return $sec.$usec;
        }

        return $usec;
    }

    /**
     * @param string $data
     * @param string $algo
     * @return string
     */
    public static function getHashKey(string $data, string $algo = 'sha256'): string
    {
        return hash($algo, trim($data), false);
    }

    /**
     * @param array $org
     * @param array $target
     * @return array
     */
    public static function intersectHeader(array $org, array $target): array
    {
        $resData = [];
        foreach ($target as $key) {
            $searchKey = strtoupper($key);
            foreach ($org as $orgKey => $orgValue) {
                if (strtoupper($orgKey) === $searchKey) {
                    $resData[$key] = $orgValue;
                    break;
                }
            }
        }

        return $resData;
    }

    /**
     * @param array $org
     * @param array $target
     * @return array
     */
    public static function intersectParameter(array $org, array $target): array
    {
        foreach ($target as $key => $value) {
            if (is_array($value)) {
                if (isset($org[$key])) {
                    if (true === self::isAssocArray($value)) {
                        $target[$key] = self::intersectParameter($org[$key], $value);
                    } else {
                        foreach ($org[$key] as $seqKey => $seqValue) {
                            $target[$key][$seqKey] = self::intersectParameter($seqValue, current($value));
                        }
                    }
                }
            } else {
                if (isset($org[$key])) {
                    $target[$key] = $org[$key];
                }
            }
        }

        return $target;
    }

    /**
     * @param array $headers
     * @return bool
     */
    public static function isSetJsonContentType(array $headers): bool
    {
        return false !== strpos($headers['Content-Type'], 'application/json');
    }

    /**
     * @param array $headers
     * @return bool
     */
    public static function isSetUrlEncodedContentType(array $headers): bool
    {
        return false !== strpos($headers['Content-Type'], 'application/x-www-form-urlencoded');
    }

    /**
     * @param string $data
     * @return string
     */
    public static function replaceHttpHeaderFormat(string $data): string
    {
        return strtoupper($data);
    }

    /**
     * @param array $datas
     * @param bool $isGetSubKeys
     * @return array
     */
    public static function getArrayKeys(array $datas, bool $isGetSubKeys = true): array
    {
        $resData = [];
        foreach ($datas as $key => $data) {
            if (false === $isGetSubKeys) {
                $resData[$key] = null;
            } else {
                if (is_array($data)) {
                    $resData[$key] = self::getArrayKeys($data);
                } else {
                    $resData[$key] = null;
                }
            }
        }

        return $resData;
    }

    /**
     * @param string $file
     * @return string
     */
    public static function getSaveFilePath(string $file): string
    {
        $basePath = realpath(CommonConst::PATH_USER_STORAGE_BASE_PATH);
        $filePath = realpath(CommonConst::PATH_USER_STORAGE_BASE_PATH.'/'.$file);

        if ($filePath === false || strpos($filePath, $basePath) !== 0) {
            throw new FileNotFoundException('No such file('.$file.')');
        }

        return $filePath;
    }

    /**
     * @param string $uri
     * @return string
     */
    public static function getAuthEndpoint(string $uri = ''): string
    {
        $credential = self::getCredentialConfig('end-point');
        return $credential['synctree-auth'].$uri;
    }

    /**
     * @return string
     */
    public static function getStorageEncryptKey(): string
    {
        $config = include APP_DIR . 'config/' . APP_ENV . '.php';
        $encryptKey = $config['settings']['storage']['db_key'];

        return $encryptKey.self::getSecureConfig(CommonConst::SECURE_STORAGE_DB_KEY);
    }

    /**
     * @param Request $request
     * @return string|null
     */
    private static function getContents(Request $request): ?string
    {
        $params = trim($request->getBody()->getContents());
        if (empty($params)) {
            return null;
        }

        return $params;
    }

    /**
     * @param Request $request
     * @return array|object|null
     */
    private static function getParsedBody(Request $request)
    {
        $params = $request->getParsedBody();
        if (empty($params)) {
            return null;
        }

        return $params;
    }

    /**
     * @param Request $request
     * @return array|null
     */
    private static function getQueryParams(Request $request): ?array
    {
        $params = $request->getQueryParams();
        if (empty($params)) {
            return null;
        }

        return $params;
    }

    /**
     * @param array $headers
     * @return array
     */
    private static function replaceRawHeaders(array $headers): array
    {
        $resData = [];
        $prefix = '/\AHTTP_/';
        foreach ($headers as $key => $value) {
            if (preg_match($prefix, $key)) {
                $key = preg_replace($prefix, '', $key);
            }

            $containKeys = explode('_', $key);
            if (count($containKeys) > 0 && strlen($key) > 2) {
                foreach ($containKeys as $subKey => $subValue) {
                    $containKeys[$subKey] = ucfirst($subValue);
                }
                $key = implode('-', $containKeys);
            }
            $resData[$key] = $value;
        }

        return $resData;
    }

    /**
     * @param array $data
     * @return bool
     */
    private static function isAssocArray(array $data): bool
    {
        return array_keys($data) !== range(0, count($data) - 1);
    }

    /**
     * @return string
     */
    public static function getUserStoragePath(): string
    {
        try {
            $credential = self::getCredentialConfig('user-storage');
            return $credential['path'];
        } catch (\Exception $ex) {
            return CommonConst::PATH_USER_STORAGE_BASE_PATH;
        }
    }

    /**
     * @param string $filename
     * @return array|false
     */
    public static function readUserConfig(string $filename)
    {
        return parse_ini_file($filename, true);
    }

    /**
     * 모든 서비스에서 공유되어 사용되는 스토리지 경로를 반환한다.
     *
     * @return string
     * @since SYN-973
     */
    public static function getUserFileStorePath(): string
    {
        $config = include APP_DIR . 'config/' . APP_ENV . '.php';
        return $config['settings']['userFileStore']['path'];
    }
}