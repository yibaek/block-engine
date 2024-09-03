<?php
namespace libraries\auth\saml\build;

use Exception;
use LightSaml\Context\Profile\Helper\MessageContextHelper;
use LightSaml\Context\Profile\MessageContext;
use LightSaml\Credential\KeyHelper;
use LightSaml\Credential\X509Certificate;
use LightSaml\Model\Protocol\SamlMessage;
use LightSaml\Model\XmlDSig\SignatureWriter;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Throwable;

class BuildUtil
{
    /**
     * @param array $config
     * @return SignatureWriter|null
     * @throws Throwable
     */
    public static function makeSignature(array $config): ?SignatureWriter
    {
        try {
            if (!self::useSignature($config)) {
                return null;
            }

            if ($config['signature']['load_type'] === 'file') {
                $certificate = X509Certificate::fromFile($config['signature']['cert']);
                $privateKey = KeyHelper::createPrivateKey($config['signature']['key'], $config['signature']['passphrase'] ?? '', true);
            } else {
                $certificate = (new X509Certificate())->loadPem($config['signature']['cert']);
                $privateKey = KeyHelper::createPrivateKey($config['signature']['key'], $config['signature']['passphrase'] ?? '', false);
            }

            return new SignatureWriter($certificate, $privateKey);
        } catch (Throwable $ex) {
            throw $ex;
        }
    }

    /**
     * @param array $config
     * @return XMLSecurityKey|null
     * @throws Throwable
     */
    public static function makePublicKey(array $config): ?XMLSecurityKey
    {
        try {
            if (!self::useSignature($config)) {
                return null;
            }

            if ($config['signature']['load_type'] === 'file') {
                $certificate = X509Certificate::fromFile($config['signature']['cert']);
            } else {
                $certificate = (new X509Certificate())->loadPem($config['signature']['cert']);
            }

            return KeyHelper::createPublicKey($certificate);
        } catch (Throwable $ex) {
            throw $ex;
        }
    }

    /**
     * @param array $config
     * @param SamlMessage $message
     * @return string
     */
    public static function makeMessageXML(array $config, SamlMessage $message): string
    {
        $messageContext = new MessageContext();
        $messageContext->setMessage($message);

        $samlMmessage = MessageContextHelper::asSamlMessage($messageContext);

        $serializationContext = $messageContext->getSerializationContext();
        $samlMmessage->serialize($serializationContext->getDocument(), $serializationContext);
        $messageXML = $serializationContext->getDocument()->saveXML();

        return self::useBase64Encoding($config) ?base64_encode($messageXML) :$messageXML;
    }

    /**
     * @param array $config
     * @return bool
     */
    public static function useBase64Encoding(array $config): bool
    {
        return $config['use_base64'] ?? true;
    }

    /**
     * @param array $config
     * @return bool
     */
    public static function useSignature(array $config): bool
    {
        return isset($config['signature']) && $config['signature'];
    }

    /**
     * @param array $config
     * @return bool
     */
    public static function useOauthBearerAssertion(array $config): bool
    {
        return $config['use_oauth_bearer_assertion'] ?? false;
    }

    /**
     * @return string
     * @throws Exception
     */
    public static function generateID(): string
    {
        return '_'.bin2hex(random_bytes(32));
    }

    /**
     * @return string
     */
    public static function makeOauthBearerAssertionAttrName(): string
    {
        return hash('md5', '__use_oauth_bearer_assertion_client_id__');
    }
}