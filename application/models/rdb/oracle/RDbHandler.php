<?php
namespace models\rdb\oracle;

use models\rdb\RDbHandlerCommon;
use Throwable;

class RDbHandler extends RDbHandlerCommon
{
    /**
     * @param int $appID
     * @param int $credentialID
     * @param string|null $sequenceID
     * @return int
     * @throws Throwable
     */
    public function executeAddCredential(int $appID, int $credentialID, string $sequenceID = null): int
    {
        return parent::executeAddCredential($appID, $credentialID, 'CERTIFICATION_MATCH_CERTIF_SEQ'); // TODO: Change the autogenerated stub
    }

    /**
     * @param int $type
     * @param int $credentialID
     * @param string $environment
     * @param string $clientID
     * @param string $clientSecret
     * @param string|null $sequenceID
     * @return int
     * @throws Throwable
     */
    public function executeAddAuthorization(int $type, int $credentialID, string $environment, string $clientID, string $clientSecret, string $sequenceID = null): int
    {
        return parent::executeAddAuthorization($type, $credentialID, $environment, $clientID, $clientSecret, 'CERTIFICATION_CERTIFICATIO_SEQ'); // TODO: Change the autogenerated stub
    }
}
