<?php
namespace Ntuple\Synctree\Util\Authorization\JWT;

use Exception;
use Jose\Component\Core\JWK;
use Jose\Easy\JWSBuilder;
use Ntuple\Synctree\Exceptions\JWTException;

class GenerateToken
{
    public const JWT_PERFORMANCE_TYPE_JWS = 'jws';

    private $manager;
    private $performanceType;
    private $key;
    private $header;
    private $payload;

    /**
     * GenerateToken constructor.
     * @param string $performanceType
     * @param string|array $key
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
     * @return string|void
     * @throws Exception
     */
    public function run()
    {
        switch ($this->performanceType) {
            case self::JWT_PERFORMANCE_TYPE_JWS:
                return $this->generateJWS();

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
     * @return string
     * @throws Exception
     */
    private function generateJWS(): string
    {
        // create builder
        $this->manager = new JWSBuilder();

        // set content
        $this->setTyp();
        $this->setAlg();
        $this->setIat();
        $this->setIss();
        $this->setSub();
        $this->setAud();
        $this->setExp();
        $this->setNbf();
        $this->setJti();
        $this->setAddClaim();

        // get jwk
        $jwk = $this->getJWK();

        try {
            // return token after sign
            return $this->manager->sign($jwk);
        } catch (Exception $ex) {
            throw new JWTException($ex->getMessage());
        }
    }

    private function setTyp(): void
    {
        $this->manager->typ('JWT');
    }

    private function setAlg(): void
    {
        $this->manager->alg($this->header['alg'][0]);
    }

    private function setIss(): void
    {
        if(isset($this->payload['iss']) && !empty($this->payload['iss'])) {
            $this->manager->iss($this->payload['iss']);
        }
        unset($this->payload['iss']);
    }

    private function setSub(): void
    {
        if(isset($this->payload['sub']) && !empty($this->payload['sub'])) {
            $this->manager->sub($this->payload['sub']);
        }
        unset($this->payload['sub']);
    }

    private function setAud(): void
    {
        if(isset($this->payload['aud']) && !empty($this->payload['aud'])) {
            $audiences = $this->payload['aud'];
            if (is_array($audiences)) {
                foreach ($audiences as $audience) {
                    $this->manager->aud($audience);
                }
            } else {
                $this->manager->aud($audiences);
            }
        }
        unset($this->payload['aud']);
    }

    private function setExp(): void
    {
        if(isset($this->payload['exp']) && !empty($this->payload['exp'])) {
            $this->manager->exp($this->payload['exp']);
        }
        unset($this->payload['exp']);
    }

    private function setNbf(): void
    {
        if(isset($this->payload['nbf']) && !empty($this->payload['nbf'])) {
            $this->manager->nbf($this->payload['nbf']);
        }
        unset($this->payload['nbf']);
    }

    /**
     * @throws Exception
     */
    private function setJti(): void
    {
        if(isset($this->payload['jti']) && !empty($this->payload['jti'])) {
            $this->manager->jti($this->payload['jti']);
        } else {
            $this->manager->jti(bin2hex(random_bytes(8)));
        }
        unset($this->payload['jti']);
    }

    private function setIat(): void
    {
        $this->manager->iat(time());
    }

    private function setAddClaim(): void
    {
        foreach ($this->payload as $key=>$value) {
            $this->manager->claim($key, $value);
        }
    }
}