<?php
namespace Ntuple\Synctree\Util\Authorization\JWT;

use Exception;
use Jose\Component\Core\JWK;
use Jose\Easy\Validate;
use Ntuple\Synctree\Exceptions\JWTException;

class ValidateToken
{
    public const JWT_PERFORMANCE_TYPE_JWS = 'jws';

    private $manager;
    private $performanceType;
    private $key;
    private $header;
    private $payload;

    /**
     * JWTValidator constructor.
     * @param string $performanceType
     * @param array|string $key
     * @param array $header
     * @param array $payload
     */
    public function __construct(string $performanceType, $key, array $header, array $payload)
    {
        $this->performanceType = $performanceType;
        $this->key = $key;
        $this->header = $header;
        $this->payload = $payload;
    }

    /**
     * @param string $token
     * @return array
     * @throws Exception
     */
    public function run(string $token): array
    {
        switch ($this->performanceType) {
            case self::JWT_PERFORMANCE_TYPE_JWS:
                return $this->verifyJWS($token);

            default:
                throw new \RuntimeException('invalid authorization jwt performace type[type:'.$this->performanceType.']');
        }
    }

    /**
     * @return JWK
     */
    private function getJWK(): JWK
    {
        if (is_string($this->key)) {
            return new JWK([
                'kty' => 'oct',
                'k' => base64_encode($this->key),
            ]);
        }

        if ($this->key instanceof CreateJWK) {
            return $this->key->getJWK();
        }

        return new JWK($this->key);
    }

    /**
     * @param string $token
     * @return array
     * @throws Exception
     */
    private function verifyJWS(string $token): array
    {
        // create validator
        $this->manager = Validate::token($token);

        // set content
        $this->setAlg();
        $this->setIss();
        $this->setIat();
        $this->setSub();
        $this->setAud();
        $this->setExp();
        $this->setNbf();
        $this->setJti();

        // set jwk
        $this->manager = $this->manager->key($this->getJWK());

        try {
            // validate
            $jwt = $this->manager->run();

            return [
                'header' => $jwt->header->all(),
                'payload' => $jwt->claims->all()
            ];
        } catch (Exception $ex) {
            throw new JWTException($ex->getMessage());
        }
    }

    private function setAlg(): void
    {
        foreach ($this->header['alg'] as $algo) {
            $this->manager = $this->manager->alg($algo);
        }
    }

    private function setIss(): void
    {
        if(isset($this->payload['iss']) && !empty($this->payload['iss'])) {
            $this->manager = $this->manager->iss($this->payload['iss']);
        }
    }

    private function setSub(): void
    {
        if(isset($this->payload['sub']) && !empty($this->payload['sub'])) {
            $this->manager = $this->manager->sub($this->payload['sub']);
        }
    }

    private function setAud(): void
    {
        if(isset($this->payload['aud']) && !empty($this->payload['aud'])) {
            if (is_array($this->payload['aud'])) {
                $this->manager = $this->manager->aud($this->payload['aud'][0]);
            } else {
                $this->manager = $this->manager->aud($this->payload['aud']);
            }
        }
    }

    private function setExp(): void
    {
        if(isset($this->payload['exp']) && !empty($this->payload['exp'])) {
            $this->manager = $this->manager->exp($this->payload['exp']);
        } else {
            $this->manager = $this->manager->exp();
        }
    }

    private function setNbf(): void
    {
        if(isset($this->payload['nbf']) && !empty($this->payload['nbf'])) {
            $this->manager = $this->manager->nbf($this->payload['nbf']);
        } else {
            $this->manager = $this->manager->nbf();
        }
    }

    /**
     * @throws Exception
     */
    private function setJti(): void
    {
        if(isset($this->payload['jti']) && !empty($this->payload['jti'])) {
            $this->manager = $this->manager->jti($this->payload['jti']);
        }
    }

    private function setIat(): void
    {
        $this->manager = $this->manager->iat();
    }
}