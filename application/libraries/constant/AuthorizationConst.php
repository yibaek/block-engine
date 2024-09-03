<?php
namespace libraries\constant;

use libraries\auth\oauth\OAuth2;
use libraries\auth\simplekey\SimpleKey;

class AuthorizationConst
{
    // authorization header key
    public const AUTHORIZATION_SIMPLE_KEY = 'X-Synctree-Auth-SimpleKey';

    // authorization environment
    public const AUTHORIZATION_ENVIRONMENT = [
        'dev',
        'stage',
        'production',
        'feature',
        'hotfix'
    ];

    // authorization type code
    public const AUTHORIZATION_TYPE_CODE = [
        OAuth2::AUTHORIZATION_TYPE => 10,
        SimpleKey::AUTHORIZATION_TYPE => 20
    ];

    // authorization result code
    public const AUTHORIZATION_RESULT_CODE_SUCCESS = '0000';
    public const AUTHORIZATION_RESULT_CODE_FAIL_AUTHORIZE = '9993';
    public const AUTHORIZATION_RESULT_CODE_FAIL_VALIDATE_TOKEN = '9994';
    public const AUTHORIZATION_RESULT_CODE_FAIL_GENERATE_TOKEN = '9995';
    public const AUTHORIZATION_RESULT_CODE_INVALID_TOKEN = '9996';
    public const AUTHORIZATION_RESULT_CODE_NOT_FOUND = '9997';
    public const AUTHORIZATION_RESULT_CODE_INVALID_REQUIRE_FIELD = '9998';
    public const AUTHORIZATION_RESULT_CODE_FAILURE = '9999';
}