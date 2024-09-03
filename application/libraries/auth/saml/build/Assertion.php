<?php
namespace libraries\auth\saml\build;

use Exception;
use libraries\auth\saml\SamlConstants;
use LightSaml\Error\LightSamlSecurityException;
use LightSaml\Error\LightSamlValidationException;
use LightSaml\Model\Assertion\AbstractCondition;
use LightSaml\Model\Assertion\AbstractNameID;
use LightSaml\Model\Assertion\AbstractStatement;
use LightSaml\Model\Assertion\Assertion as AssertionModel;
use LightSaml\Model\Assertion\Attribute;
use LightSaml\Model\Assertion\AttributeStatement;
use LightSaml\Model\Assertion\AudienceRestriction;
use LightSaml\Model\Assertion\AuthnContext;
use LightSaml\Model\Assertion\AuthnStatement;
use LightSaml\Model\Assertion\Conditions;
use LightSaml\Model\Assertion\Issuer;
use LightSaml\Model\Assertion\NameID;
use LightSaml\Model\Assertion\Subject;
use LightSaml\Model\Assertion\SubjectConfirmation;
use LightSaml\Model\Assertion\SubjectConfirmationData;
use LightSaml\Model\Assertion\SubjectLocality;
use LightSaml\Model\Context\DeserializationContext;
use LightSaml\Model\Protocol\Response as SAML2Response;
use LightSaml\Model\Protocol\Status;
use LightSaml\Model\Protocol\StatusCode;
use LightSaml\Model\XmlDSig\SignatureXmlReader;
use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;
use RuntimeException;
use Throwable;

class Assertion
{
    private $config;
    private $request;
    private $response;
    private $authnRequest;

    /**
     * Assertion constructor.
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array $config
     */
    public function __construct(RequestInterface $request, ResponseInterface $response, array $config = [])
    {
        $this->request = $request;
        $this->response = $response;

        // set config
        $this->config = $this->addDefaultConfig($config);
    }

    /**
     * @return bool
     * @throws Exception|Throwable
     */
    public function generate(): bool
    {
        try {
            // make response
            if(!$response=$this->makeResponse($this->request)) {
                return false;
            }

            // add assertion
            if (!$this->addAssertionToResponse($response)) {
                return false;
            }

            // set signature
            if ($signature = BuildUtil::makeSignature($this->config)) {
                $response->setSignature($signature);
            }

            // set reponse
            $this->response->setContents(BuildUtil::makeMessageXML($this->config, $response));
            return true;
        } catch (Throwable $ex) {
            throw $ex;
        }
    }

    /**
     * @return bool
     * @throws Throwable
     */
    public function validate(): bool
    {
        try {
            // check assertion [required]
            if (!$assertion=$this->request->request('assertion', false)) {
                $this->response->setError(400, 'invalid_request', 'The assertion was not specified in the request');
                return false;
            }

            $deserializationContext = new DeserializationContext();
            $deserializationContext->getDocument()->loadXML(BuildUtil::useBase64Encoding($this->config) ?base64_decode($assertion) :$assertion);

            $response = new SAML2Response();
            $response->deserialize($deserializationContext->getDocument()->firstChild, $deserializationContext);

            // validate signature
            if(!$valid=$this->validateWithSignature($response)) {
                return $valid;
            }

            // validate client_id
            if (!$valid=$this->checkOauthBearerAssertion($response)) {
                return $valid;
            }

            // validate time restrictions
            if (!$valid=$this->validateTimeRestrictions($response)) {
                return $valid;
            }

            return true;
        } catch (Throwable $ex) {
            throw $ex;
        }
    }

    /**
     * @param array $config
     * @return array
     */
    private function addDefaultConfig(array $config): array
    {
        return $config;
    }

    /**
     * @param RequestInterface $request
     * @return SAML2Response|null
     * @throws Exception
     */
    private function makeResponse(RequestInterface $request): ?SAML2Response
    {
        // check issuer [required]
        if (!$issuer = $request->request('issuer', false)) {
            $this->response->setError(400, 'invalid_request', 'The issuer was not specified in the request');
            return null;
        }

        if (is_array($issuer)) {
            $issuer = new Issuer($issuer['value'], $issuer['format']);
        } else {
            $issuer = new Issuer($issuer);
        }

        // check requestId [optional]
        $requestID = $request->request('request_id', BuildUtil::generateID());

        // check issueInstant [optional]
        $issusInstant = $request->request('issue_instant', time());

        // generate Saml2 Response
        $response = new SAML2Response();
        $response->setStatus($this->makeResponseStatus())
            ->setID($requestID)
            ->setIssueInstant($issusInstant)
            ->setIssuer($issuer);

        // check destination [optional]
        if ($this->authnRequest) {
            if ($destination = $this->authnRequest->getAssertionConsumerServiceURL()) {
                $response->setDestination($destination);
            }
        } else if ($destination = $request->request('destination', false)) {
            $response->setDestination($destination);
        }

        // check inResponseTo [optional]
        if ($this->authnRequest) {
            if ($inResponseTo = $this->authnRequest->getID()) {
                $response->setInResponseTo($inResponseTo);
            }
        } else if ($inResponseTo = $request->request('in_response_to', false)) {
            $response->setInResponseTo($inResponseTo);
        }

        return $response;
    }

    /**
     * @param SAML2Response $response
     * @return bool
     * @throws Exception
     */
    private function addAssertionToResponse(SAML2Response $response): bool
    {
        // make assertion
        if(!$assertion=$this->makeAssertion($this->request, $response)) {
            return false;
        }

        $response->addAssertion($assertion);
        return true;
    }

    /**
     * @param RequestInterface $request
     * @param SAML2Response $response
     * @return AssertionModel|null
     * @throws Exception
     */
    private function makeAssertion(RequestInterface $request, SAML2Response $response): ?AssertionModel
    {
        // generate Assertion
        $assertion = new AssertionModel();
        $assertion->setId($response->getID())
            ->setIssueInstant($response->getIssueInstantDateTime())
            ->setIssuer($response->getIssuer());

        // check subject [optional]
        if ($subject = $this->makeAssertionSubject($request)) {
            $assertion->setSubject($subject);
        }

        // check conditions [optional]
        if ($conditions = $this->makeAssertionConditions($request)) {
            $assertion->setConditions($conditions);
        }

        // check items [optional]
        if(!$this->addAssertionItems($assertion, $request)) {
            return null;
        }

        return $assertion;
    }

    /**
     * @param string $status
     * @return Status
     */
    private function makeResponseStatus(string $status = SamlConstants::STATUS_SUCCESS): Status
    {
        return new Status(new StatusCode($status));
    }

    /**
     * @param RequestInterface $request
     * @return Subject|null
     */
    private function makeAssertionSubject(RequestInterface $request) :?Subject
    {
        try {
            if (!$subject = $request->request('subject', false)) {
                return null;
            }

            $assertionSubject = new Subject();

            // add subject
            $assertionSubject->setNameID(new NameID($subject['name_id']['value'], $subject['name_id']['format']));

            // add confirmation
            foreach ($subject['confirmation'] as $confirmation) {
                switch ($confirmation['type']) {
                    case 'bearer':
                        if (isset($confirmation['value']) && !empty($confirmation['value'])) {
                            $subjectConfirmationData = new SubjectConfirmationData();
                            if (isset($confirmation['value']['in_response_to']) && !empty($confirmation['value']['in_response_to'])) {
                                $subjectConfirmationData->setInResponseTo($confirmation['value']['in_response_to']);
                            }
                            if (isset($confirmation['value']['noton_or_after']) && !empty($confirmation['value']['noton_or_after'])) {
                                $subjectConfirmationData->setNotOnOrAfter($confirmation['value']['noton_or_after']);
                            }
                            if (isset($confirmation['value']['recipient']) && !empty($confirmation['value']['recipient'])) {
                                $subjectConfirmationData->setRecipient($confirmation['value']['recipient']);
                            }

                            $assertionSubject->addSubjectConfirmation((new SubjectConfirmation())->setMethod(SamlConstants::CONFIRMATION_METHOD_BEARER)->setSubjectConfirmationData($subjectConfirmationData));
                        }
                        break;
                }
            }

            return $assertionSubject;
        } catch (Throwable $ex) {
            return null;
        }
    }

    /**
     * @param RequestInterface $request
     * @return Conditions|null
     */
    private function makeAssertionConditions(RequestInterface $request) :?Conditions
    {
        try {
            if (!$condition = $request->request('condition', false)) {
                return null;
            }

            $assertionCondition = (new Conditions())
                ->setNotBefore($condition['not_before'])
                ->setNotOnOrAfter($condition['noton_or_after']);

            // add condition items
            $this->addAssertionConditionItems($assertionCondition, $condition['items'] ?? []);

            return $assertionCondition;
        } catch (Throwable $ex) {
            return null;
        }
    }

    /**
     * @param AssertionModel $assertion
     * @param RequestInterface $request
     * @return bool
     */
    private function addAssertionItems(AssertionModel $assertion, RequestInterface $request): bool
    {
        if ($items = $this->makeAssertionItems($request)) {
            foreach ($items as $item) {
                $assertion->addItem($item);
            }
        }

        // check oauth bearer aserrtion attribute statement [optional]
        if(!$this->addOAuthBearerAssertionAttributeStatement($assertion, $request)) {
            return false;
        }

        return true;
    }

    /**
     * @param RequestInterface $request
     * @return array|null
     */
    private function makeAssertionItems(RequestInterface $request) :?array
    {
        // get assertion items
        $items = $request->request('items', []);

        $assertionItems = [];
        foreach ($items as $item) {
            if($assertionItem = $this->makeAssertionItem($item)) {
                $assertionItems[] = $assertionItem;
            }
        }

        return $assertionItems;
    }

    /**
     * @param array $item
     * @return AbstractStatement|null
     */
    private function makeAssertionItem(array $item): ?AbstractStatement
    {
        try {
            switch ($item['type']) {
                case 'attribute_statement':
                    return $this->makeAttributeStatement($item['value']);

                case 'authn_statement':
                    return $this->makeAuthnStatement($item['value']);

                default:
                    throw new RuntimeException('invalid assertion item type[type:'.$item['type'].']');
            }
        } catch (Throwable $ex) {
            return null;
        }
    }

    /**
     * @param array $attributes
     * @return AttributeStatement
     */
    private function makeAttributeStatement(array $attributes): AttributeStatement
    {
        $attributeStatement = new AttributeStatement();

        foreach ($attributes as $attribute) {
            $attributeValue = new Attribute($attribute['name'], $attribute['value']);
            if (isset($attribute['format']) && !empty($attribute['format'])) {
                $attributeValue->setNameFormat($attribute['format']);
            }

            $attributeStatement->addAttribute($attributeValue);
        }

        return $attributeStatement;
    }

    /**
     * @param array $value
     * @return AuthnStatement
     */
    private function makeAuthnStatement(array $value): AuthnStatement
    {
        $authnStatement = new AuthnStatement();

        if (isset($value['instant']) && !empty($value['instant'])) {
            $authnStatement->setAuthnInstant($value['instant']);
        }

        if (isset($value['session_index']) && !empty($value['session_index'])) {
            $authnStatement->setSessionIndex($value['session_index']);
        }

        if (isset($value['session_noton_or_after']) && !empty($value['session_noton_or_after'])) {
            $authnStatement->setSessionNotOnOrAfter($value['session_noton_or_after']);
        }

        if (isset($value['context']) && !empty($value['context'])) {
            /** AuthnContext에 대한 확장이 필요
             * 기존 : AuthnContext Element 중 AuthnContextClassRef만 적용할 수 있도록 구현
             * 변경 : AuthnContext의 Element를 다 받을 수 있는 구조(array)로 변경
             */
            $authnStatement->setAuthnContext($this->makeAuthnContext($value['context']));
        }

        if (isset($value['subject_locality']) && !empty($value['subject_locality'])) {
            $authnStatement->setSubjectLocality($this->makeSubjectLocality($value['subject_locality']));
        }

        return $authnStatement;
    }

    /**
     * @param Conditions $condition
     * @param array $items
     */
    private function addAssertionConditionItems(Conditions $condition, array $items = [])
    {
        foreach ($items as $item) {
            if ($item = $this->makeAssertionConditionItem($item)) {
                $condition->addItem($item);
            }
        }
    }

    /**
     * @param array $item
     * @return AbstractCondition|null
     */
    private function makeAssertionConditionItem(array $item): ?AbstractCondition
    {
        try {
            switch ($item['type']) {
                case 'audience_restriction':
                    return new AudienceRestriction($item['value']);

                default:
                    throw new RuntimeException('invalid assertion condition item type[type:'.$item['type'].']');
            }
        } catch (Throwable $ex) {
            return null;
        }
    }

    /**
     * @param SAML2Response $response
     * @return bool
     * @throws Throwable
     */
    private function validateWithSignature(SAML2Response $response): bool
    {
        try {
            if ($publicKey = BuildUtil::makePublicKey($this->config)) {
                /** @var SignatureXmlReader $signatureReader */
                $signatureReader = $response->getSignature();
                if (!$signatureReader) {
                    $this->response->setError(400, 'invalid_grant', 'Unable to verify Signature');
                    return false;
                }
                return $signatureReader->validate($publicKey);
            }

            return true;
        } catch (LightSamlSecurityException $ex) {
            $this->response->setError(400, 'invalid_grant', $ex->getMessage());
            return false;
        } catch (Throwable $ex) {
            throw $ex;
        }
    }

    /**
     * @param AssertionModel $assertion
     * @param RequestInterface $request
     * @return bool
     */
    private function addOAuthBearerAssertionAttributeStatement(AssertionModel $assertion, RequestInterface $request): bool
    {
        if (!BuildUtil::useOauthBearerAssertion($this->config)) {
            return true;
        }

        // check client_id [required]
        if (!$clientId = $request->request('client_id', false)) {
            $this->response->setError(400, 'invalid_request', 'The client_id was not specified in the request');
            return false;
        }

        $assertion->addItem(
            (new AttributeStatement())->addAttribute(
                (new Attribute(BuildUtil::makeOauthBearerAssertionAttrName(), $clientId))->setNameFormat(SamlConstants::ATTRIBUTE_NAME_FORMAT_BASIC)));

        return true;
    }

    /**
     * @param SAML2Response $response
     * @return bool
     */
    private function checkOauthBearerAssertion(SAML2Response $response):bool
    {
        if (!BuildUtil::useOauthBearerAssertion($this->config)) {
            return true;
        }

        if (!$clientId=$this->request->request('client_id', false)) {
            $this->response->setError(400, 'invalid_request', 'The client_id was not specified in the request');
            return false;
        }

        $assertion = $response->getFirstAssertion();
        if (!$assertion) {
            $this->response->setError(401, 'invalid_assertion', 'The assertion provided is invalid');
            return false;
        }

        $valid = true;
        foreach ($assertion->getAllAttributeStatements() as $statement) {
            if($attribute=$statement->getFirstAttributeByName(BuildUtil::makeOauthBearerAssertionAttrName())) {
                $valid = $attribute->getFirstAttributeValue() === $clientId;
                break;
            }
        }

        if (!$valid) {
            $this->response->setError(400, 'client_id_mismatch', 'The cient_id does not match');
        }

        return $valid;
    }

    /**
     * @param SAML2Response $response
     * @param int $allowedSecondsSkew
     * @return bool
     */
    private function validateTimeRestrictions(SAML2Response $response, int $allowedSecondsSkew = 0):bool
    {
        try {
            $assertion = $response->getFirstAssertion();
            if (!$assertion) {
                $this->response->setError(401, 'invalid_assertion', 'The assertion provided is invalid');
                return false;
            }

            // validate
            (new AssertionTimeValidator())->validateTimeRestrictions($assertion, time(), $allowedSecondsSkew);

            return true;
        } catch (LightSamlValidationException $ex) {
            $this->response->setError(401, 'invalid_assertion', $ex->getMessage());
            return false;
        }
    }

    /**
     * @param array $data
     * @return SubjectLocality
     */
    private function makeSubjectLocality(array $data): SubjectLocality
    {
        $subjectLocality = new SubjectLocality();

        if (isset($data['address']) && !empty($data['address'])) {
            $subjectLocality->setAddress($data['address']);
        }

        if (isset($data['dns']) && !empty($data['dns'])) {
            $subjectLocality->setDNSName($data['dns']);
        }

        return $subjectLocality;
    }

    /**
     * @param array|string $data
     * @return AuthnContext
     */
    private function makeAuthnContext($data): AuthnContext
    {
        $authnContext = new AuthnContext();

        // string type이면 AuthnContextClassRef Element로 판단
        if (is_string($data)) {
            $authnContext->setAuthnContextClassRef($data);
            return $authnContext;
        }

        // 전체 Element를 받을 수 있게 확장
        if (isset($data['authn_context_class_ref']) && !empty($data['authn_context_class_ref'])) {
            $authnContext->setAuthnContextClassRef($data['authn_context_class_ref']);
        }

        if (isset($data['authn_context_decl']) && !empty($data['authn_context_decl'])) {
            $authnContext->setAuthnContextDecl($data['authn_context_decl']);
        }

        if (isset($data['authn_context_decl_ref']) && !empty($data['authn_context_decl_ref'])) {
            $authnContext->setAuthnContextDeclRef($data['authn_context_decl_ref']);
        }

        if (isset($data['authenticating_authority']) && !empty($data['authenticating_authority'])) {
            $authnContext->setAuthenticatingAuthority($data['authenticating_authority']);
        }

        return $authnContext;
    }
}