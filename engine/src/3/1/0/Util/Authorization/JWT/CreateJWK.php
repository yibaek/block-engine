<?php
namespace Ntuple\Synctree\Util\Authorization\JWT;

use Exception;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Ntuple\Synctree\Exceptions\FileNotFoundException;
use Ntuple\Synctree\Util\CommonUtil;

class CreateJWK
{
    private $jwk;

    public function __construct()
    {
    }

    /**
     * @return JWK
     */
    public function getJWK(): JWK
    {
        return $this->jwk;
    }

    /**
     * @param string $secret
     * @param array|null $additionalValues
     * @return CreateJWK
     */
    public function createFromSecret(string $secret, array $additionalValues = null): CreateJWK
    {
        $this->jwk = JWKFactory::createFromSecret($secret, $additionalValues ?? []);
        return $this;
    }

    /**
     * @param string $file
     * @param array|null $additionalValues
     * @return CreateJWK
     */
    public function createFromCertificateFile(string $file, array $additionalValues = null): CreateJWK
    {
        $this->jwk = JWKFactory::createFromCertificate($this->getKey($file), $additionalValues ?? []);
        return $this;
    }

    /**
     * @param string $file
     * @param string|null $password
     * @param array|null $additionalValues
     * @return CreateJWK
     */
    public function createFromKeyFile(string $file, string $password = null, array $additionalValues = null): CreateJWK
    {
        $this->jwk = JWKFactory::createFromKey($this->getKey($file), $password, $additionalValues ?? []);
        return $this;
    }

    /**
     * @param string $file
     * @return string
     */
    private function getKey(string $file): string
    {
        try {
            return file_get_contents(CommonUtil::getSaveFilePath($file), false, null, 0, 20480);
        } catch (Exception $ex) {
            throw new FileNotFoundException('No such file('.$file.')');
        }
    }
}