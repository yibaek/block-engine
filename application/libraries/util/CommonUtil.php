<?php
namespace libraries\util;

use Psr\Http\Message\ResponseInterface as Response;

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
     * @param boolean $required
     * @return array
     */
    public static function getCredentialConfig(string $field, bool $required = true): array
    {
        $config = include APP_DIR . 'config/' . APP_ENV . '.php';
        $inis = parse_ini_file($config['settings']['credentials']['file_path'], true);

        if ($required && !isset($inis[$field])) {
            throw new \RuntimeException('failed to load credential config file(field:' . $field . ')');
        }

        return $inis[$field] ?? [];
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
     * @param string $fileName
     * @param string|null $path
     * @return string
     */
    public static function getContentsFile(string $fileName, string $path = null): string
    {
        if (empty($path)) {
            // get contents base path
            $config = include APP_DIR . 'config/' . APP_ENV . '.php';
            $path = $config['settings']['contents']['file_path'];
        }

        $contents = file_get_contents($path . $fileName);
        if (empty($contents)) {
            throw new \RuntimeException('failed to load contents file(filename:' . $fileName . ')');
        }

        return self::removeBom($contents);
    }

    /**
     * remove bom(byte order mark) from file
     * @param string $data
     * @return string
     */
    public static function removeBom(string $data): string
    {
        $bom = pack('H*', 'EFBBBF');
        return preg_replace("/^$bom/", '', $data);
    }

    /**
     * get microseconds
     * @param bool $isAddSec
     * @param int $length
     * @return string
     */
    public static function getUsec(bool $isAddSec = false, int $length = 6): string
    {
        [$usec, $sec] = explode(' ', microtime());

        $usec = substr($usec, strpos($usec, '.') + 1, $length);
        if (true === $isAddSec) {
            return $sec . $usec;
        }

        return $usec;
    }

    /**
     * get hash key
     * @param string $data
     * @param string $algo
     * @return string
     */
    public static function getHashKey(string $data, string $algo = 'sha256'): string
    {
        return hash($algo, trim($data), false);
    }

    /**
     * extract data from http header
     * @param array $org
     * @param array $target
     * @return array
     */
    public static function intersectHeader(array $org, array $target): array
    {
        $resData = [];
        foreach ($target as $key => $value) {
            $searchKey = strtoupper($key);
            foreach ($org as $orgKey => $orgValue) {
                if ($orgKey === $searchKey) {
                    $resData[$key] = $orgValue;
                    break;
                }
            }
        }

        return $resData;
    }

    /**
     * @param array $params
     * @param array $fields
     * @return array
     */
    public static function validateParams(array $params, array $fields): array
    {
        foreach ($fields as $key => $field) {
            if (is_array($field)) {
                foreach ($field as $sub) {
                    if (!isset($params[$key][$sub])) {
                        return [false, 'not valid required field(' . $sub . ' of ' . $key . ')'];
                    }
                }
            } else {
                if (true === array_key_exists($field, $params)) {
                    if (!isset($params[$field])) {
                        return [false, 'empty required field(' . $field . ')'];
                    }
                } else {
                    return [false, 'not found required field(' . $field . ')'];
                }
            }
        }

        return [true, null];
    }

    /**
     * @param Response $response
     * @return Response
     */
    public static function responseWithJson(Response $response): Response
    {
        return $response->withHeader('Content-Type', 'application/json; ; charset=utf-8');
    }

    /**
     * @param string $data
     * @return int
     */
    private static function dataLength(string $data): int
    {
        return strlen($data);
    }

    /**
     * @param $data
     * @return string
     * @throws \Exception
     */
    public static function seed($data): string
    {
        $seed   = '';
        $length = self::dataLength($data);
        for ($i=0;$i<$length;++$i) {
            $seed .= random_int(0, 9);
        }
        return $seed;
    }

    /**
     * @param string $data
     * @param string $seed
     * @return string
     */
    public static function shuffle(string $data, string $seed): string
    {
        $length1 = self::dataLength($data);
        $length2 = self::dataLength($seed);
        for ($i=0;$i<$length1;++$i) {
            $swap = $seed[$i % $length2] % $length1;
            $temp = $data[$swap];
            $data[$swap] = $data[$i];
            $data[$i] = $temp;
        }
        return $data;
    }

    /**
     * @param string $data
     * @param string $seed
     * @return string
     */
    public static function unshuffle(string $data, string $seed): string
    {
        $length1 = self::dataLength($data);
        $length2 = self::dataLength($seed);
        for ($i=$length1-1;$i>=0;--$i) {
            $swap = $seed[$i % $length2] % $length1;
            $temp = $data[$swap];
            $data[$swap] = $data[$i];
            $data[$i] = $temp;
        }
        return $data;
    }
}