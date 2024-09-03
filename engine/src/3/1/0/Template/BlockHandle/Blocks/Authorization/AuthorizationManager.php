<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\JWT\Content\Header;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\JWT\Content\Payload;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\JWT\Content\RegistedClaim;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\JWT\Keystore\JWK\Factory\CreateFromCertificateFile;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\JWT\Keystore\JWK\Factory\CreateFromSecret;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\JWT\Keystore\JWK\JWKCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\JWT\Performance\JWS\JWSCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\JWT\Performance\JWS\JWSVerify;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\OAuth2\Authorize;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\OAuth2\Extension\SAML2BearerAssertion;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\OAuth2\TokenCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\OAuth2\TokenRevoke;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\OAuth2\TokenType\JWT\JWTTokenCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\OAuth2\TokenVerify;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion\AssertionCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion\Condition\AudienceRestriction;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion\Condition\ConditionCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion\Items\AttributeStatement;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion\Items\AttributeStatementValue;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion\Items\AuthnStatement;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion\Items\Element\SubjectLocality;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion\Subject\BearerConfirmation;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion\Subject\NameID;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion\Subject\SubjectCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Credential\Certificate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Credential\CredentialCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Credential\PrivateKey;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\ResponseCreate;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\ResponseVerify;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SimpleKey\SimpleKeyVerify;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\OAuth2\TokenType\JWT\Payload as JWTTokenTypePayload;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class AuthorizationManager implements IBlock
{
    public const TYPE = 'authorization';

    private $storage;
    private $block;

    /**
     * AuthorizationManager constructor.
     * @param PlanStorage $storage
     * @param IBlock|null $block
     */
    public function __construct(PlanStorage $storage, IBlock $block = null)
    {
        $this->storage = $storage;
        $this->block = $block;
    }

    /**
     * @param array $data
     * @return IBlock
     * @throws Exception
     */
    public function setData(array $data): IBlock
    {
        switch ($data['action']) {
            case JWSCreate::ACTION:
                $this->block = (new JWSCreate($this->storage))->setData($data);
                return $this;

            case JWSVerify::ACTION:
                $this->block = (new JWSVerify($this->storage))->setData($data);
                return $this;

            case Header::ACTION:
                $this->block = (new Header($this->storage))->setData($data);
                return $this;

            case Payload::ACTION:
                $this->block = (new Payload($this->storage))->setData($data);
                return $this;

            case RegistedClaim::ACTION:
                $this->block = (new RegistedClaim($this->storage))->setData($data);
                return $this;

            case JWKCreate::ACTION:
                $this->block = (new JWKCreate($this->storage))->setData($data);
                return $this;

            case CreateFromCertificateFile::ACTION:
                $this->block = (new CreateFromCertificateFile($this->storage))->setData($data);
                return $this;

            case CreateFromSecret::ACTION:
                $this->block = (new CreateFromSecret($this->storage))->setData($data);
                return $this;

            case TokenCreate::ACTION:
                $this->block = (new TokenCreate($this->storage))->setData($data);
                return $this;

            case TokenVerify::ACTION:
                $this->block = (new TokenVerify($this->storage))->setData($data);
                return $this;

            case TokenRevoke::ACTION:
                $this->block = (new TokenRevoke($this->storage))->setData($data);
                return $this;

            case SimpleKeyVerify::ACTION:
                $this->block = (new SimpleKeyVerify($this->storage))->setData($data);
                return $this;

            case Authorize::ACTION:
                $this->block = (new Authorize($this->storage))->setData($data);
                return $this;

            case JWTTokenCreate::ACTION:
                $this->block = (new JWTTokenCreate($this->storage))->setData($data);
                return $this;

            case JWTTokenTypePayload::ACTION:
                $this->block = (new JWTTokenTypePayload($this->storage))->setData($data);
                return $this;

            case ResponseCreate::ACTION:
                $this->block = (new ResponseCreate($this->storage))->setData($data);
                return $this;

            case AssertionCreate::ACTION:
                $this->block = (new AssertionCreate($this->storage))->setData($data);
                return $this;

            case SubjectCreate::ACTION:
                $this->block = (new SubjectCreate($this->storage))->setData($data);
                return $this;

            case NameID::ACTION:
                $this->block = (new NameID($this->storage))->setData($data);
                return $this;

            case BearerConfirmation::ACTION:
                $this->block = (new BearerConfirmation($this->storage))->setData($data);
                return $this;

            case AttributeStatement::ACTION:
                $this->block = (new AttributeStatement($this->storage))->setData($data);
                return $this;

            case AttributeStatementValue::ACTION:
                $this->block = (new AttributeStatementValue($this->storage))->setData($data);
                return $this;

            case AuthnStatement::ACTION:
                $this->block = (new AuthnStatement($this->storage))->setData($data);
                return $this;

            case AudienceRestriction::ACTION:
                $this->block = (new AudienceRestriction($this->storage))->setData($data);
                return $this;

            case ConditionCreate::ACTION:
                $this->block = (new ConditionCreate($this->storage))->setData($data);
                return $this;

            case CredentialCreate::ACTION:
                $this->block = (new CredentialCreate($this->storage))->setData($data);
                return $this;

            case PrivateKey::ACTION:
                $this->block = (new PrivateKey($this->storage))->setData($data);
                return $this;

            case Certificate::ACTION:
                $this->block = (new Certificate($this->storage))->setData($data);
                return $this;

            case ResponseVerify::ACTION:
                $this->block = (new ResponseVerify($this->storage))->setData($data);
                return $this;

            case SAML2BearerAssertion::ACTION:
                $this->block = (new SAML2BearerAssertion($this->storage))->setData($data);
                return $this;

            case SubjectLocality::ACTION:
                $this->block = (new SubjectLocality($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid authorization block action[action:'.$data['action'].']');
        }
    }

    /**
     * @return array
     */
    public function getTemplate(): array
    {
        return $this->block->getTemplate();
    }

    /**
     * @param array $blockStorage
     */
    public function do(array &$blockStorage)
    {
        return $this->block->do($blockStorage);
    }
}