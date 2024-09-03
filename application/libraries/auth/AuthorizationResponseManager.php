<?php
namespace libraries\auth;

use JsonException;
use libraries\constant\AuthorizationConst;

class AuthorizationResponseManager
{
    private $result;
    private $resultData;
    private $extraData;
    private $isJson;

    /**
     * AuthorizationResponseManager constructor.
     * @param string $result
     * @param string|array|null $resultData
     * @param null $extraData
     */
    public function __construct(string $result, $resultData = null, $extraData = null)
    {
        $this->result = $result;
        $this->resultData = $resultData;
        $this->extraData = $extraData;
        $this->isJson = true;
    }

    /**
     * @param string|null $addMessage
     * @return array|false|string
     * @throws JsonException
     */
    public function makeResult(string $addMessage = null)
    {
        // make common set
        $resData = $this->makeCommonSet();

        // add message
        if ($this->isAddErrorMessage()) {
            $resData['message'] = $this->getErrorMessage($addMessage);
        }

        return $this->isJson ?json_encode($resData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE, 512) :$resData;
    }

    /**
     * @return array
     */
    private function makeCommonSet(): array
    {
        $resData = [
            'result' => $this->result,
            'result_data' => $this->resultData
        ];

        // add extra data
        if (!empty($this->extraData)) {
            $resData['extra_data'] = $this->extraData;
        }

        return $resData;
    }

    /**
     * @param string|null $addMessage
     * @return string
     */
    private function getErrorMessage(string $addMessage = null): string
    {
        if (!empty($addMessage)) {
            return $addMessage;
        }

        switch ($this->result) {
            case AuthorizationConst::AUTHORIZATION_RESULT_CODE_SUCCESS:
                $message = '성공';
                break;

            case AuthorizationConst::AUTHORIZATION_RESULT_CODE_NOT_FOUND:
                $message = '발급받은 키가 없습니다.';
                break;

            case AuthorizationConst::AUTHORIZATION_RESULT_CODE_INVALID_TOKEN:
                $message = '토큰이 올바르지 않습니다.';
                break;

            default:
                $message = '일시적인 오류가 발생하였습니다. 다시 시도하여 주시기 바랍니다.';
                break;
        }

        return $message;
    }

    /**
     * @return bool
     */
    private function isAddErrorMessage(): bool
    {
        switch ($this->result) {
            case AuthorizationConst::AUTHORIZATION_RESULT_CODE_SUCCESS:
            case AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAIL_AUTHORIZE:
            case AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAIL_GENERATE_TOKEN:
            case AuthorizationConst::AUTHORIZATION_RESULT_CODE_FAIL_VALIDATE_TOKEN:
                return false;

            default:
                return true;
        }
    }
}
