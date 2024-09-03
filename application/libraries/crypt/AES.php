<?php
namespace libraries\crypt;

use Exception;
use libraries\log\LogMessage;

class AES
{
    public const AES_256_CBC = 'aes-256-cbc';

    /**
     * @param string $method
     * @param string $plainText
     * @param string $key
     * @param bool $isBase64
     * @return string|null
     * @throws Exception
     */
    public static function encryptWithHmac(string $method, string $plainText, string $key, bool $isBase64 = false): ?string
    {
        // make iv
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));

        // encrypt
        if (false === ($output=self::encrypt($method, $plainText, $key, $iv))) {
            return null;
        }

        // encoding
        if (true === $isBase64) {
            $resData = base64_encode($output . $iv . hash_hmac('sha256', $output, $key, true));
        } else {
            $resData = bin2hex($output . $iv . hash_hmac('sha256', $output, $key, true));
        }

        return $resData;
    }

    /**
     * @param string $method
     * @param string $cipherData
     * @param string $key
     * @param bool $isBase64
     * @return string|null
     * @throws Exception
     */
    public static function decryptWithHmac(string $method, string $cipherData, string $key, bool $isBase64 = false): ?string
    {
        // decoding cipher data
        if (true === $isBase64) {
            $cipherData = base64_decode($cipherData);
        } else {
            $cipherData = hex2bin($cipherData);
        }

        $len = strlen($cipherData);

        // check cipher valid length
        if ($len < 64) {
            LogMessage::error('not valid cipher data length');
            return null;
        }

        $iv = substr($cipherData, $len - 48, 16);
        $hmac = substr($cipherData, $len - 32, 32);
        $_cipherData = substr($cipherData, 0, $len - 48);

        // check hash_hmac
        if ($hmac !== hash_hmac('sha256', $_cipherData, $key, true)) {
            LogMessage::error('not valid hash_hmac');
            return null;
        }

        // decrypt
        if (false === ($output=self::decrypt($method, $_cipherData, $key, $iv))) {
            return null;
        }

        return $output;
    }

    /**
     * @param string $method
     * @param string $plainText
     * @param string $key
     * @param string $iv
     * @return string|null
     * @throws Exception
     */
    private static function encrypt(string $method, string $plainText, string $key, string $iv): ?string
    {
        // check plain text is empty
        if (empty(trim($plainText))) {
            LogMessage::error('not valid plain text;; empty data');
            return null;
        }

        // encrypt
        if (false === ($output=openssl_encrypt($plainText, $method, $key, OPENSSL_RAW_DATA, $iv))) {
            LogMessage::error('failed to encrypt!!');
            return null;
        }

        LogMessage::debug('encrypt::' . bin2hex($output));
        return $output;
    }

    /**
     * @param string $method
     * @param string $cipherData
     * @param string $key
     * @param string $iv
     * @return string|null
     * @throws Exception
     */
    private static function decrypt(string $method, string $cipherData, string $key, string $iv): ?string
    {
        // check cipher data is empty
        if (strlen(trim($cipherData)) < 16) {
            LogMessage::error('not valid cipher data length');
            return null;
        }

        // decrypt
        if (false === ($output=openssl_decrypt($cipherData, $method, $key, OPENSSL_RAW_DATA, $iv))) {
            LogMessage::error('failed to decrypt!!');
            return null;
        }

        LogMessage::debug('decrypt::' . $output);
        return $output;
    }
}