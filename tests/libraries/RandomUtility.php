<?php declare(strict_types=1);
namespace Tests\libraries;

use Exception;

/**
 * @since SRT-201
 */
class RandomUtility
{
    /**
     * 무작위로 구성된 연관배열을 만든다. 각 `param`에 대해 최소값은 1
     *
     * @param int $maxElements 최대 항목 갯수
     * @param int $maxKeyLength 최대 키 길이
     * @param int $maxValueLength 최대 값 (string) 길이
     * @return array
     * @throws Exception
     */
    public function getRandomAssociativeStringArray(
        int $maxElements,
        int $maxKeyLength,
        int $maxValueLength): array
    {
        $ret = [];

        for ($i = 0, $j = random_int(1, $maxElements); $i < $j; ++$i)
        {
            $keyLength = random_int(1, $maxKeyLength);
            $valueLength = random_int(1, $maxValueLength);

            $key = bin2hex(openssl_random_pseudo_bytes($keyLength));
            $ret[$key] = bin2hex(openssl_random_pseudo_bytes($valueLength));
        }

        return $ret;
    }

    /**
     * 무작위 바이트 열을 지정된 길이로 생성하고 `hex`문자열로 만든다.
     *
     * @param int $length
     * @return string
     * @since SRT-231
     */
    public function generateHex(int $length): string
    {
        return bin2hex(openssl_random_pseudo_bytes($length));
    }
}