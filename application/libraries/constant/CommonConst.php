<?php
namespace libraries\constant;

class CommonConst
{
    // redis db index
    public const REDIS_SESSION = 0;
    public const REDIS_ACCESS_CONTROL_SESSION = 1;
    public const REDIS_CONTENTS = 10;

    // redis session expire time
    public const REDIS_SESSION_EXPIRE_TIME_MIN_3    = 180;          // sec (60*3)
    public const REDIS_SESSION_EXPIRE_TIME_MIN_30   = 1800;         // sec (60*30)
    public const REDIS_SESSION_EXPIRE_TIME_DAY_1    = 86400;        // sec (60*60*24)
    public const REDIS_SESSION_EXPIRE_TIME_DAY_7    = 604800;       // sec (60*60*24*7)

    // s3 bucket
    public const S3_BUCKET_CONTENTS = 'synctree-contents';

    // header key for synctree plan load type
    public const SYNCTREE_PLAN_LOAD_TYPE = 'X-Synctree-Plan-Load-Type';

    // header key for synctree plan id
    public const SYNCTREE_PLAN_ID = 'X-Synctree-Plan-ID';

    // header key for synctree plan environment, bizunit version, revision id
    public const SYNCTREE_PLAN_ENVIRONMENT = 'X-Synctree-Plan-Environment';
    public const SYNCTREE_BIZUNIT_VERSION = 'X-Synctree-Bizunit-Version';
    public const SYNCTREE_REVISION_ID = 'X-Synctree-Revision-ID';
    public const SYNCTREE_TRANSACTION_KEY = 'X-Synctree-Bizunit-Transaction-Key';
    public const SYNCTREE_PLAN_TEST_MODE = 'X-Synctree-Plan-Test-Mode';

    // secure key for secure protocol
    public const SYNCTREE_SECURE_KEY = 'X-Synctree-Secure-Key';

    // plan data load type
    public const PLAN_DATA_LOAD_TYPE_LOCAL_CONTENTS = 1;
    public const PLAN_DATA_LOAD_TYPE_S3_CONTENTS = 2;
    public const PLAN_DATA_LOAD_TYPE_RDB_CONTENTS = 3;

    // plan test mode type
    public const PLAN_TEST_MODE_BIZUNIT = 'bizunit';
    public const PLAN_TEST_MODE_LIBRARY = 'library';

    /**
     * used by synctree engine
     */

    // encryption key
    public const SECURE_REDIS_KEY = 'redis_key';
    public const SECURE_DYNAMO_KEY = 'dynamo_key';
    public const SECURE_STORAGE_DB_KEY = 'storage_db_key';

    // secure protocol redis info
    public const SECURE_PROTOCOL_REDIS_DB = self::REDIS_SESSION;
    public const SECURE_PROTOCOL_REDIS_SESSION_EXPIRE_TIME = self::REDIS_SESSION_EXPIRE_TIME_MIN_3;

    // access control redis info
    public const ACCESS_CONTROL_REDIS_DB = self::REDIS_ACCESS_CONTROL_SESSION;
    public const ACCESS_CONTROL_NOSQL_COMMON_SESSION_EXPIRE_TIME = self::REDIS_SESSION_EXPIRE_TIME_DAY_7;

    // authorization uri
    public const AUTHORIZATION_OAUTH2_AUTHORIZE = '/oauth2/authorize';
    public const AUTHORIZATION_OAUTH2_TOKEN_GENERATE = '/oauth2/token';
    public const AUTHORIZATION_OAUTH2_TOKEN_VALIDATE = '/oauth2/validate';
    public const AUTHORIZATION_OAUTH2_TOKEN_REVOKE = '/oauth2/revoke';
    public const AUTHORIZATION_SIMPLEKEY_VALIDATE = '/simplekey/validate';
    public const AUTHORIZATION_SAML2_ASSERTION_GENERATE = '/saml2/assertion';
    public const AUTHORIZATION_SAML2_ASSERTION_VALIDATE = '/saml2/assertion/validate';

    // authorization header
    public const AUTHORIZATION_SIMPLE_KEY_HEADER = AuthorizationConst::AUTHORIZATION_SIMPLE_KEY;

    // synctree test mode header
    public const PLAN_TEST_MODE_HEADER = self::SYNCTREE_PLAN_TEST_MODE;

    // user storage base path
    public const PATH_USER_STORAGE_BASE_PATH = '/home/ubuntu/user-storage';

    // throttle buffer log config path
    public const PATH_THROTTLE_BUFFER_LOG_CONFIG_PATH = '/home/ubuntu/.synctree/throttleBufferLog.ini';
}