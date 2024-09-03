<?php
namespace Ntuple\Synctree\Constant;

class PlanConst
{
    // header key for synctree plan id
    public const SYNCTREE_PLAN_ID = 'X-Synctree-Plan-ID';
    public const SYNCTREE_PLAN_ENVIRONMENT = 'X-Synctree-Plan-Environment';
    public const SYNCTREE_BIZUNIT_VERSION = 'X-Synctree-Bizunit-Version';
    public const SYNCTREE_REVISION_ID = 'X-Synctree-Revision-ID';

    // secure key for secure protocol
    public const SYNCTREE_SECURE_KEY = 'X-Synctree-Secure-Key';

    // verification code of secure protocol
    public const SYNCTREE_VERIFICATION_CODE = 'X-Synctree-Verification-Code';

    // synctree storage common table info
    public const SYNCTREE_STORAGE_COMMON_DYNAMODB_TABLE_NAME = 'product_professional_common';

    // synctree redis key prefix
    public const SYNCTREE_REDIS_KEY_PREFIX = 'YyTg5k';
    public const SYNCTREE_REDIS_INNER_KEY_PREFIX = 'uPGP7v';
}