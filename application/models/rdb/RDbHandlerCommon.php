<?php
namespace models\rdb;

use Throwable;
use Exception;
use RuntimeException;

use libraries\log\LogMessage;
use models\rdb\dtos\SetOAuthTokenMatchDto;

abstract class RDbHandlerCommon implements IRDbHandler
{
    private $rdb;

    /**
     * @param IRdbMgr $rdb
     */
    public function __construct(IRdbMgr $rdb)
    {
        $this->rdb = $rdb;
    }

    /**
     * @param int|null $appid
     * @param string|null $bizunitID
     * @return array
     * @throws Throwable
     */
    public function executeGetAccountInfo(int $appid = null, string $bizunitID = null): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            if ($appid === null) {
                // bizunit
                $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                    ->select('app_id')
                    ->table('bizunit')
                    ->where('bizunit_id', $bizunitID)
                    ->orderBy('bizunit_version', 'DESC')
                    ->limit(1));
                $appid = $resData[0]['app_id'];
            }

            // app, account_slave, account_master
            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('s.slave_id')
                ->select('s.master_account')
                ->select('m.master_id')
                ->select('m.master_division')
                ->select('m.product_sno')
                ->table('app a')
                ->join('INNER JOIN', 'account_slave s', 'a.user_id = s.slave_id')
                ->join('INNER JOIN', 'account_master m', 's.master_id = m.master_id')
                ->where('a.app_id', $appid));
            $account = $resData[0];

            // t_product, t_product_limit
            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('P.product_name')
                ->select('P.product_tier')
                ->select('P.product_price')
                ->select('P.product_price_krw')
                ->select('L.*')
                ->table('t_product P')
                ->join('INNER JOIN', 't_product_limit L', 'P.product_sno = L.product_sno')
                ->where('P.product_sno', $account['product_sno']));
            $product = $resData[0];

            return array_merge($product, $account);
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $libraryID
     * @return array
     * @throws Throwable
     */
    public function executeGetLibraryAccountInfo(string $libraryID): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            // library
            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('register_account AS slave_id')
                ->table('library')
                ->where('library_id', $libraryID));
            $libraryData = $resData[0];

            // slave
            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('master_id')
                ->select('master_account')
                ->table('account_slave')
                ->where('slave_id', $libraryData['slave_id']));
            $accountSlaveData = $resData[0];

            // master
            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('master_division')
                ->select('product_sno')
                ->table('account_master')
                ->where('master_id', $accountSlaveData['master_id']));
            $accountMasterData = $resData[0];

            // product
            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('P.product_name')
                ->select('P.product_tier')
                ->select('P.product_price')
                ->select('P.product_price_krw')
                ->select('L.*')
                ->table('t_product P')
                ->join('INNER JOIN', 't_product_limit L', 'P.product_sno = L.product_sno')
                ->where('P.product_sno', $accountMasterData['product_sno']));
            $product = $resData[0];

            return array_merge($product, $libraryData, $accountSlaveData, $accountMasterData);
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @return array
     * @throws Throwable
     */
    public function executeGetPlaygroundAccountInfo(): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $playgroundData = [
                'slave_id' => '0',
                'master_id' => '0',
                'master_account' => '0000000000',
                'master_division' => 'public'
            ];

            // product
            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('P.product_name')
                ->select('P.product_tier')
                ->select('P.product_price')
                ->select('P.product_price_krw')
                ->select('L.*')
                ->table('t_product P')
                ->join('INNER JOIN', 't_product_limit L', 'P.product_sno = L.product_sno')
                ->where('P.product_sno', 2));
            $product = $resData[0];

            return array_merge($product, $playgroundData);
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $path
     * @param string $method
     * @param string $masterAccountNo
     * @return array
     * @throws Throwable
     * @throws NotFoundBizunit 
     */
    public function executeGetBizUnitProxyInfo(string $path, string $method, string $masterAccountNo): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $query = $this->rdb->getSelect()
                ->select('p.bizunit_proxy_id')
                ->select('b.bizunit_sno')
                ->select('b.bizunit_id')
                ->select('b.bizunit_version')
                ->select('r.revision_sno')
                ->select('r.revision_id')
                ->select('o.operator_sno')
                ->table('bizunit_proxy p')
                ->join('INNER JOIN', 'bizunit b', 'p.bizunit_sno = b.bizunit_sno')
                ->join('INNER JOIN', 'revision r', "b.bizunit_sno = r.bizunit_sno AND r.revision_status_code = 1 AND r.revision_environment = 'production'")
                ->join('INNER JOIN', 'operator o', "r.revision_sno = o.revision_sno")
                ->where('p.bizunit_proxy_base_path', $path)
                ->whereAnd('p.bizunit_proxy_method', $method);

            if ($masterAccountNo !== '-1') {
                $query->whereAnd('p.master_account', $masterAccountNo);
            }

            $resData = $this->rdb->executeQuery($query);
            if (!isset($resData[0])) {
                throw new NotFoundBizunit('failed to get bizunit proxy info[path:'.$path.', method:'.$method.']');
            }

            return $resData[0];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $planEnvironment
     * @param string $bizunitID
     * @param string $bizunitVersion
     * @param string $revisionID
     * @return array
     * @throws Throwable
     */
    public function executeGetPlan(string $planEnvironment, string $bizunitID, string $bizunitVersion, string $revisionID): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('plan_environment')
                ->select('bizunit_id')
                ->select('bizunit_version')
                ->select('revision_id')
                ->select('plan_content')
                ->select('register_date')
                ->select('modify_date')
                ->table('t_plan')
                ->where('plan_environment', $planEnvironment)
                ->whereAnd('bizunit_id', $bizunitID)
                ->whereAnd('bizunit_version', $bizunitVersion)
                ->whereAnd('revision_id', $revisionID));

            return $resData[0];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param array $accountInfo
     * @param array $bizunitInfo
     * @param array $logData
     * @return bool
     * @throws Exception
     */
    public function executeSetApiLog(array $accountInfo, array $bizunitInfo, array $logData): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection((int)$accountInfo['slave_id']);

            $this->rdb->executeQuery($this->rdb->getInsert()
                ->insert('master_id', $accountInfo['master_id'])
                ->insert('slave_id', $accountInfo['slave_id'])
                ->insert('bizunit_sno', $bizunitInfo['bizunit-sno'])
                ->insert('bizunit_id', $bizunitInfo['bizunit_id'])
                ->insert('bizunit_version', $bizunitInfo['bizunit_version'])
                ->insert('revision_sno', $bizunitInfo['revision-sno'])
                ->insert('revision_id', $bizunitInfo['revision_id'])
                ->insert('environment', $bizunitInfo['environment'])
                ->insert('latency', $logData['latency'])
                ->insert('size', $logData['size'])
                ->insert('response_status', $logData['status_code'])
                ->insertDatetime('regdate', date('Y-m-d H:i:s'))
                ->table('t_api_log'));

            return true;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            return false;
        }
    }

    /**
     * $this->rdb 는 studio
     *
     * @param array $bizunitInfo 'bizunit-sno' 또는 'bizunit_sno' 키 필요
     * @return integer
     * @throws Exception
     */
    public function executeGetProxyIDByBizunitInfo(array $bizunitInfo): int
    {
        $bizunitSno = (int) ($bizunitInfo['bizunit-sno'] ?? $bizunitInfo['bizunit_sno']);

        $this->rdb->makeConnection();
        $proxyIDs = $this->rdb->executeQuery(
            $this->rdb->getSelect()
                ->select('bizunit_proxy_id')
                ->table('bizunit_proxy')
                ->where('bizunit_sno', $bizunitSno)
                ->limit(1)
        );

        if (count($proxyIDs) < 1) {
            throw new Exception('proxy not found');
        }
        return (int) $proxyIDs[0]['bizunit_proxy_id'];
    }

    /**
     * @param int $slaveID
     * @return string
     * @throws Throwable
     */
    public function executeGetShardInfo(int $slaveID): string
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            // center info
            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('increment')
                ->table('t_center_info')
                ->where('slave_id', $slaveID));
            $increment = empty($resData[0]['increment']) ? 0 : (int) $resData[0]['increment'];

            if ($increment === 0) {
                // find shard target
                $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                    ->select('shard_idx')
                    ->select('ratio')
                    ->select('accum_value')
                    ->table('t_shard_info')
                    ->orderBy('accum_value')
                    ->limit(1));
                $shardInfo = $resData[0];

                // insert into center
                $this->rdb->executeQuery($this->rdb->getInsert()
                    ->insert('slave_id', $slaveID)
                    ->insert('shard_no', $shardInfo['shard_idx'])
                    ->insertDatetime('reg_date', date('Y-m-d H:i:s'))
                    ->table('t_center_info'));

                // reset increment
                $increment = $this->rdb->getLastInsertID('increment');

                // update accum
                $affectedRows = $this->rdb->executeQuery($this->rdb->getUpdate()
                    ->update('accum_value', $shardInfo['accum_value'] + $shardInfo['ratio'])
                    ->table('t_shard_info')
                    ->where('shard_idx', $shardInfo['shard_idx']));

                if ($affectedRows !== 1) {
                    throw new RuntimeException('failed to get log shard connection info[slave:'.$slaveID.']');
                }
            }

            // get connection info
            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('center.increment')
                ->select('shard.shard_idx')
                ->select('shard.connection_string')
                ->table('t_center_info center')
                ->join('INNER JOIN', 't_shard_info shard', 'shard.shard_idx = center.shard_no')
                ->where('center.increment', $increment));

            return $resData[0]['connection_string'];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param int $appID
     * @param int $credentialID
     * @param string|null $sequenceID
     * @return int
     * @throws Throwable
     */
    public function executeAddCredential(int $appID, int $credentialID, string $sequenceID = null): int
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $exist = $this->rdb->exist($this->rdb->getSelect()
                ->selectAll()
                ->table('certification_match')
                // ->where('slave_id', $slaveID)
                ->where('app_id', $appID)
                ->whereAnd('credential_id', $credentialID));
            if (true === $exist) {
                throw new RuntimeException('failed to add credential:duplicate key[ appID:'.$appID.', credentialID:'.$credentialID.']');
            }

            $affectedRows = $this->rdb->executeQuery($this->rdb->getInsert()
                ->insert('slave_id', -1) // todo 삭제
                ->insert('app_id', $appID)
                ->insert('credential_id', $credentialID)
                ->insertDatetime('register_date', date('Y-m-d H:i:s'))
                ->table('certification_match'));

            if ($affectedRows !== 1) {
                throw new RuntimeException('failed to add credential[ appID:'.$appID.', credentialID:'.$credentialID.']');
            }

            return $this->rdb->getLastInsertID('certification_match_id', $sequenceID);
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param int $appID
     * @param int $credentialTarget
     * @param int $credentialID
     * @return array
     * @throws Throwable
     */
    public function executeGetCredential(int $appID, int $credentialTarget, int $credentialID): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $exist = $this->rdb->exist($this->rdb->getSelect()
                ->selectAll()
                ->table('certification_match')
                ->where('credential_id', $credentialID));
            if (false === $exist) {
                throw new RuntimeException('failed to get credential[credentialID:'.$credentialID.']');
            }

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('certification_match_id')
                ->select('slave_id')
                ->select('app_id')
                ->select('credential_target')
                ->select('credential_id')
                ->select('register_date')
                ->table('certification_match')
                ->where('app_id', $appID)
                ->whereAnd('credential_target', $credentialTarget)
                ->whereAnd('credential_id', $credentialID));

            return (count($resData)) ? end($resData) : $resData;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param int $credentialTarget
     * @param int $credentialID
     * @return array
     * @throws Throwable
     */
    public function executeGetCredentialWithoutAppID(int $credentialTarget, int $credentialID): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('certification_match_id')
                ->select('slave_id')
                ->select('app_id')
                ->select('credential_target')
                ->select('credential_id')
                ->select('register_date')
                ->table('certification_match')
                ->where('credential_target', $credentialTarget)
                ->whereAnd('credential_id', $credentialID));

            return (count($resData)) ? end($resData) : $resData;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param int $appID
     * @param int $credentialID
     * @return bool
     * @throws Throwable
     */
    public function executeDeleteCredential(int $appID, int $credentialID): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $exist = $this->rdb->exist($this->rdb->getSelect()
                ->selectAll()
                ->table('certification_match')
                // ->where('slave_id', $slaveID)
                ->where('app_id', $appID)
                ->whereAnd('credential_id', $credentialID)
                ->whereAnd('credential_target', 0));

            if (true === $exist) {
                $affectedRows = $this->rdb->executeQuery($this->rdb->getDelete()
                    ->table('certification_match')
                    // ->where('slave_id', $slaveID)
                    ->where('app_id', $appID)
                    ->whereAnd('credential_id', $credentialID)
                    ->whereAnd('credential_target', 0));

                if ($affectedRows !== 1) {
                    throw new RuntimeException('failed to delete credential[ appID:'.$appID.', credentialID:'.$credentialID.']');
                }
            }

            return true;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param int $type
     * @param int $credentialID
     * @param string $environment
     * @param string|null $clientID
     * @param string $clientSecret
     * @param string|null $sequenceID
     * @return int
     * @throws Throwable
     */
    public function executeAddAuthorization(int $type, int $credentialID, string $environment, string $clientID, string $clientSecret, string $sequenceID = null): int
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $exist = $this->rdb->exist($this->rdb->getSelect()
                ->selectAll()
                ->table('certification')
                ->where('client_id', $clientID));
            if (true === $exist) {
                throw new RuntimeException('failed to add authorization:duplicate key[type:'.$type.', credentialID:'.$credentialID.', environment:'.$environment.', clientiID:'.$clientID.']');
            }

            $affectedRows = $this->rdb->executeQuery($this->rdb->getInsert()
                ->insert('credential_id', $credentialID)
                ->insert('certification_type', $type)
                ->insert('certification_environment', $environment)
                ->insert('client_id', $clientID)
                ->insert('client_secret', $clientSecret)
                ->insertDatetime('register_date', date('Y-m-d H:i:s'))
                ->table('certification'));

            if ($affectedRows !== 1) {
                throw new RuntimeException('failed to add authorization[type:'.$type.', credentialID:'.$credentialID.', environment:'.$environment.', clientiID:'.$clientID.']');
            }

            return $this->rdb->getLastInsertID('certification_id', $sequenceID);
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $clientID
     * @return array
     * @throws Throwable
     */
    public function executeGetAuthorization(string $clientID): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('certification_id')
                ->select('credential_target')
                ->select('credential_id')
                ->select('certification_type')
                ->select('certification_environment')
                ->select('client_id')
                ->select('client_secret')
                ->select('register_date')
                ->select('modify_date')
                ->table('certification')
                ->where('client_id', $clientID)
                ->whereAnd('is_del', 'N'));

            return (count($resData) > 0) ? end($resData) : [];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $clientID
     * @return bool
     * @throws Throwable
     */
    public function executeDeleteAuthorization(string $clientID): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $exist = $this->rdb->exist($this->rdb->getSelect()
                ->selectAll()
                ->table('certification')
                ->where('client_id', $clientID)
                ->whereAnd('is_del', 'N'));
            if (false === $exist) {
                throw new RuntimeException('failed to delete authorization:not found clientID[clientID:'.$clientID.']');
            }

            $affectedRows = $this->rdb->executeQuery($this->rdb->getUpdate()
                ->update('is_del', 'Y')
                ->updateDatetime('del_date', date('Y-m-d H:i:s'))
                ->table('certification')
                ->where('client_id', $clientID)
                ->whereAnd('credential_target', 0));

            if ($affectedRows !== 1) {
                throw new RuntimeException('failed to delete authorization[clientID:'.$clientID.']');
            }

            return true;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $clientID
     * @return array
     * @throws Throwable
     */
    public function executeGetOAuthClients(string $clientID): array
    {
        try {
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('client_id')
                ->select('client_secret')
                ->select('redirect_uri')
                ->select('grant_types')
                ->select('scope')
                ->select('user_id')
                ->table('t_oauth_clients')
                ->where('client_id', $clientID));

            return !empty($resData) ? $resData[0] : [];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $clientID
     * @return bool
     * @throws Throwable
     */
    public function executeDelOAuthClients(string $clientID): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $affectedRows = $this->rdb->executeQuery($this->rdb->getDelete()
                ->table('t_oauth_clients')
                ->where('client_id', $clientID));

            if ($affectedRows !== 1) {
                throw new RuntimeException('failed to delete oauth clients[clientID:'.$clientID.']');
            }

            return true;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $clientID
     * @param string|null $clientSecret
     * @param string|null $redirectURI
     * @param string|null $grantTypes
     * @param string|null $scope
     * @param string|null $userID
     * @return bool
     * @throws Throwable
     */
    public function executeSetOAuthClients(string $clientID, string $clientSecret = null, string $redirectURI = null, string $grantTypes = null, string $scope = null, $userID = null): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $affectedRows = $this->rdb->executeQuery($this->rdb->getInsert()
                ->insert('client_id', $clientID)
                ->insert('client_secret', $clientSecret)
                ->insert('redirect_uri', $redirectURI)
                ->insert('grant_types', $grantTypes)
                ->insert('scope', $scope)
                ->insert('user_id', $userID)
                ->table('t_oauth_clients'));

            if ($affectedRows !== 1) {
                throw new RuntimeException('failed to add oauth clients[clientID:'.$clientID.', userID:'.$userID.']');
            }

            return true;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $accessToken
     * @return array
     * @throws Throwable
     */
    public function executeGetOAuthAccessTokens(string $accessToken): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('access_token')
                ->select('client_id')
                ->select('user_id')
                ->selectDatetime('expires')
                ->select('scope')
                ->table('t_oauth_access_tokens')
                ->where('access_token', $accessToken));

            return !empty($resData) ? $resData[0] : [];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $accessToken
     * @param string $clientID
     * @param string $expires
     * @param string $userID
     * @param string|null $scope
     * @return bool
     * @throws Throwable
     */
    public function executeSetOAuthAccessTokens(string $accessToken, string $clientID, string $expires, $userID, string $scope = null): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $exist = $this->rdb->exist($this->rdb->getSelect()
                ->selectAll()
                ->table('t_oauth_access_tokens')
                ->where('access_token', $accessToken));

            if (true === $exist) {
                $affectedRows = $this->rdb->executeQuery($this->rdb->getUpdate()
                    ->update('client_id', $clientID)
                    ->updateDatetime('expires', $expires)
                    ->update('user_id', $userID)
                    ->update('scope', $scope)
                    ->table('t_oauth_access_tokens')
                    ->where('access_token', $accessToken));

                if ($affectedRows !== 1) {
                    throw new RuntimeException('failed to update oauth access token[clientID:'.$clientID.', accessToken:'.$accessToken.']');
                }
                return true;
            }

            $affectedRows = $this->rdb->executeQuery($this->rdb->getInsert()
                ->insert('access_token', $accessToken)
                ->insert('client_id', $clientID)
                ->insertDatetime('expires', $expires)
                ->insert('user_id', $userID)
                ->insert('scope', $scope)
                ->table('t_oauth_access_tokens'));

            if ($affectedRows !== 1) {
                throw new RuntimeException('failed to add oauth access token[clientID:'.$clientID.', accessToken:'.$accessToken.']');
            }

            return true;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $accessToken
     * @return bool
     * @throws Throwable
     */
    public function executeDelOAuthAccessTokens(string $accessToken): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $affectedRows = $this->rdb->executeQuery($this->rdb->getDelete()
                ->table('t_oauth_access_tokens')
                ->where('access_token', $accessToken));

            return $affectedRows === 1;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param int $portalAppId
     * @param int $studioAppId
     * @return bool
     * @throws Throwable
     */
    public function executeVerificationCertificationForPortal(int $portalAppId, int $studioAppId): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->selectCount('app_count')
                ->table('portal_app p')
                ->join('INNER JOIN', 'app_api_match m', 'p.portal_app_id = m.portal_app_id')
                ->join('INNER JOIN', 'bizunit_api_match b', 'b.portal_api_id = m.portal_api_id')
                ->where('p.portal_app_id', $portalAppId)
                ->whereAnd('b.studio_app_id', $studioAppId));

            return ((int) $resData[0]['app_count']) > 0;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param int $portalCredentialID
     * @return array
     * @throws Throwable
     */
    public function executeGetPortalCredential(int $portalCredentialID): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('portal_credential_id')
                ->select('portal_app_id')
                ->select('portal_credential_type')
                ->select('key_value')
                ->selectDatetime('register_date')
                ->table('portal_credential')
                ->where('portal_credential_id', $portalCredentialID));

            return $resData[0];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param array $accountInfo
     * @param array $bizunitInfo
     * @param array $message
     * @return bool
     * @throws Throwable
     */
    public function executeSetConsoleLog(array $accountInfo, array $bizunitInfo, array $message): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection((int) $accountInfo['slave_id']);

            foreach ($message as $data) {
                $this->rdb->executeQuery($this->rdb->getInsert()
                    ->insert('master_id', $accountInfo['master_id'])
                    ->insert('slave_id', $accountInfo['slave_id'])
                    ->insert('bizunit_sno', $bizunitInfo['bizunit-sno'])
                    ->insert('bizunit_id', $bizunitInfo['bizunit_id'])
                    ->insert('bizunit_version', $bizunitInfo['bizunit_version'])
                    ->insert('revision_sno', $bizunitInfo['revision-sno'])
                    ->insert('revision_id', $bizunitInfo['revision_id'])
                    ->insert('environment', $bizunitInfo['environment'])
                    ->insert('console_level', $data['level'])
                    ->insert('console_message', $data['message'])
                    ->insert('console_type', $data['type'])
                    ->insert('transaction_key', $bizunitInfo['transaction_key'])
                    ->insertDatetime('regdate', date('Y-m-d H:i:s'))
                    ->table('t_console_log'));
            }

            return true;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $clientID
     * @return string
     * @throws Throwable
     */
    public function executeGetPrivateKey(string $clientID): string
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('private_key')
                ->table('t_oauth_public_keys')
                ->where('client_id', $clientID));

            if (empty($resData)) {
                throw new RuntimeException('failed to get private_key[clientID:'.$clientID.']');
            }

            return $resData[0]['private_key'];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $clientID
     * @return string
     * @throws Throwable
     */
    public function executeGetEncryptionAlgorithm(string $clientID): string
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('encryption_algorithm')
                ->table('t_oauth_public_keys')
                ->where('client_id', $clientID));

            if (empty($resData)) {
                throw new RuntimeException('failed to get encryption_algorithm[clientID:'.$clientID.']');
            }

            return $resData[0]['encryption_algorithm'];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $refreshToken
     * @return array
     * @throws Throwable
     */
    public function executeGetOAuthRefreshTokens(string $refreshToken): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->selectAll()
                ->table('t_oauth_refresh_tokens')
                ->where('refresh_token', $refreshToken));

            return !empty($resData) ? $resData[0] : [];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $refreshToken
     * @param string $clientID
     * @param string $expires
     * @param string $userID
     * @param string|null $scope
     * @return bool
     * @throws Throwable
     */
    public function executeSetOAuthRefreshTokens(string $refreshToken, string $clientID, $userID, string $expires, string $scope = null): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $exist = $this->rdb->exist($this->rdb->getSelect()
                ->selectAll()
                ->table('t_oauth_refresh_tokens')
                ->where('refresh_token', $refreshToken));

            if (true === $exist) {
                $affectedRows = $this->rdb->executeQuery($this->rdb->getUpdate()
                    ->update('client_id', $clientID)
                    ->updateDatetime('expires', $expires)
                    ->update('user_id', $userID)
                    ->update('scope', $scope)
                    ->table('t_oauth_refresh_tokens')
                    ->where('refresh_token', $refreshToken));

                if ($affectedRows !== 1) {
                    throw new RuntimeException('failed to update oauth refersh token[clientID:'.$clientID.', userID:'.$userID.', refreshToken:'.$refreshToken.']');
                }
                return true;
            }

            $affectedRows = $this->rdb->executeQuery($this->rdb->getInsert()
                ->insert('refresh_token', $refreshToken)
                ->insert('client_id', $clientID)
                ->insertDatetime('expires', $expires)
                ->insert('user_id', $userID)
                ->insert('scope', $scope)
                ->table('t_oauth_refresh_tokens'));

            if ($affectedRows !== 1) {
                throw new RuntimeException('failed to add oauth refersh token[clientID:'.$clientID.', userID:'.$userID.', refreshToken:'.$refreshToken.']');
            }

            return true;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @since SRT-148
     * @param string $refreshToken
     * @return bool
     * @throws Throwable
     */
    public function executeDelOAuthRefreshTokens(string $refreshToken): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $refreshtokenData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('refresh_token_id')
                ->table('t_oauth_refresh_tokens')
                ->where('refresh_token', $refreshToken));

            if (!empty($refreshtokenData)) {
                $refreshTokenId = $refreshtokenData[0]['refresh_token_id'];

                $this->rdb->executeQuery($this->rdb->getDelete()
                    ->table('oauth_token_match')
                    ->where('refresh_token_id', $refreshTokenId));

                $affectedRows = $this->rdb->executeQuery($this->rdb->getDelete()
                    ->table('t_oauth_refresh_tokens')
                    ->where('refresh_token_id', $refreshTokenId));

                if ($affectedRows !== 1) {
                    throw new RuntimeException('failed to delete refresh token[token:'.$refreshToken.']');
                }
            }

            return true;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $code
     * @return array
     * @throws Throwable
     */
    public function executeGetOAuthAuthorizationCodes(string $code): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->selectAll()
                ->table('t_oauth_authorization_codes')
                ->where('authorization_code', $code));

            return !empty($resData) ? $resData[0] : [];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $code
     * @param string $clientID
     * @param string $userID
     * @param string $redirectUri
     * @param string $expires
     * @param string|null $scope
     * @return bool
     * @throws Throwable
     */
    public function executeSetOAuthAuthorizationCodes(string $code, string $clientID, $userID, string $redirectUri, string $expires, string $scope = null): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $exist = $this->rdb->exist($this->rdb->getSelect()
                ->selectAll()
                ->table('t_oauth_authorization_codes')
                ->where('authorization_code', $code));

            if (true === $exist) {
                $affectedRows = $this->rdb->executeQuery($this->rdb->getUpdate()
                    ->update('client_id', $clientID)
                    ->update('user_id', $userID)
                    ->update('redirect_uri', $redirectUri)
                    ->updateDatetime('expires', $expires)
                    ->update('scope', $scope)
                    ->table('t_oauth_authorization_codes')
                    ->where('authorization_code', $code));

                if ($affectedRows !== 1) {
                    throw new RuntimeException('failed to update oauth authorization code[clientID:'.$clientID.', userID:'.$userID.', code:'.$code.', redirectUri:'.$redirectUri.']');
                }
                return true;
            }

            $affectedRows = $this->rdb->executeQuery($this->rdb->getInsert()
                ->insert('authorization_code', $code)
                ->insert('client_id', $clientID)
                ->insert('user_id', $userID)
                ->insert('redirect_uri', $redirectUri)
                ->insertDatetime('expires', $expires)
                ->insert('scope', $scope)
                ->table('t_oauth_authorization_codes'));

            if ($affectedRows !== 1) {
                throw new RuntimeException('failed to add oauth authorization code[clientID:'.$clientID.', userID:'.$userID.', code:'.$code.', redirectUri:'.$redirectUri.']');
            }

            return true;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $code
     * @param string $clientID
     * @param string $userID
     * @param string $redirectUri
     * @param string $expires
     * @param string|null $scope
     * @param string|null $idToken
     * @return bool
     * @throws Throwable
     */
    public function executeSetOAuthAuthorizationCodesWithIdToken(string $code, string $clientID, $userID, string $redirectUri, string $expires, string $scope = null, string $idToken = null): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $exist = $this->rdb->exist($this->rdb->getSelect()
                ->selectAll()
                ->table('t_oauth_authorization_codes')
                ->where('authorization_code', $code));

            if (true === $exist) {
                $affectedRows = $this->rdb->executeQuery($this->rdb->getUpdate()
                    ->update('client_id', $clientID)
                    ->update('user_id', $userID)
                    ->update('redirect_uri', $redirectUri)
                    ->updateDatetime('expires', $expires)
                    ->update('scope', $scope)
                    ->update('id_token', $idToken)
                    ->table('t_oauth_authorization_codes')
                    ->where('authorization_code', $code));

                if ($affectedRows !== 1) {
                    throw new RuntimeException('failed to update oauth authorization code with idtoken[clientID:'.$clientID.', userID:'.$userID.', code:'.$code.', redirectUri:'.$redirectUri.', idToken:'.$idToken.']');
                }
                return true;
            }

            $affectedRows = $this->rdb->executeQuery($this->rdb->getInsert()
                ->insert('authorization_code', $code)
                ->insert('client_id', $clientID)
                ->insert('user_id', $userID)
                ->insert('redirect_uri', $redirectUri)
                ->insertDatetime('expires', $expires)
                ->insert('scope', $scope)
                ->insert('id_token', $idToken)
                ->table('t_oauth_authorization_codes'));

            if ($affectedRows !== 1) {
                throw new RuntimeException('failed to add oauth authorization code with idtoken[clientID:'.$clientID.', userID:'.$userID.', code:'.$code.', redirectUri:'.$redirectUri.', idToken:'.$idToken.']');
            }

            return true;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $code
     * @return bool
     * @throws Throwable
     */
    public function executeDelOAuthAuthorizationCodes(string $code): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $exist = $this->rdb->exist($this->rdb->getSelect()
                ->selectAll()
                ->table('t_oauth_authorization_codes')
                ->where('authorization_code', $code));

            if (true === $exist) {
                $affectedRows = $this->rdb->executeQuery($this->rdb->getDelete()
                    ->table('t_oauth_authorization_codes')
                    ->where('authorization_code', $code));

                if ($affectedRows !== 1) {
                    throw new RuntimeException('failed to delete authorization_code[code:'.$code.']');
                }
            }

            return true;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $username
     * @return array
     * @throws Throwable
     */
    public function executeGetOAuthUser(string $username): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->selectAll()
                ->table('t_oauth_users')
                ->where('username', $username));

            return !empty($resData) ? $resData[0] : [];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $username
     * @param string $password
     * @param string|null $firstName
     * @param string|null $lastName
     * @return bool
     * @throws Throwable
     */
    public function executeSetOAuthUser(string $username, string $password, string $firstName = null, string $lastName = null): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $exist = $this->rdb->exist($this->rdb->getSelect()
                ->selectAll()
                ->table('t_oauth_users')
                ->where('username', $username));

            if (true === $exist) {
                $affectedRows = $this->rdb->executeQuery($this->rdb->getUpdate()
                    ->update('password', $password)
                    ->update('first_name', $firstName)
                    ->update('last_name', $lastName)
                    ->table('t_oauth_users')
                    ->where('username', $username));

                if ($affectedRows !== 1) {
                    throw new RuntimeException('failed to update oauth users[username:'.$username.']');
                }
                return true;
            }

            $affectedRows = $this->rdb->executeQuery($this->rdb->getInsert()
                ->insert('username', $username)
                ->insert('password', $password)
                ->insert('first_name', $firstName)
                ->insert('last_name', $lastName)
                ->table('t_oauth_users'));

            if ($affectedRows !== 1) {
                throw new RuntimeException('failed to add oauth users[username:'.$username.']');
            }

            return true;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $username
     * @param string $password
     * @return bool
     * @throws Throwable
     */
    public function executeCheckOAuthUser(string $username, string $password): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            return $this->rdb->exist($this->rdb->getSelect()
                ->selectAll()
                ->table('t_oauth_users')
                ->where('username', $username)
                ->whereAnd('password', $password));
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $scopes
     * @return int
     * @throws Throwable
     */
    public function executeGetOAuthScopesCount(string $scopes): int
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->selectCount('count', 'scope')
                ->table('t_oauth_scopes')
                ->where($this->rdb->getOperatorNFunc()->in('scope', $scopes)));

            return $resData[0]['count'];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @return array
     * @throws Throwable
     */
    public function executeGetOAuthScopesDefault(): array
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('scope')
                ->table('t_oauth_scopes')
                ->where('is_default', 1));

            return !empty($resData) ? $resData[0]['count'] : [];
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $clientID
     * @return string
     * @throws Throwable
     */
    public function executeGetOAuthPublicKey(string $clientID): string
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('public_key')
                ->table('t_oauth_public_keys')
                ->where('client_id', $clientID));

            return !empty($resData) ? $resData[0]['public_key'] : '';
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $clientID
     * @param string $token
     * @return bool
     * @throws Throwable
     */
    public function executeRevokeAccessToken(string $clientID, string $token): bool
    {
        try {
            // make connection
            $this->rdb->makeConnection();

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('access_token_id')
                ->table('t_oauth_access_tokens')
                ->where('access_token', $token)
                ->limit(1));

            if (count($resData) < 1) {
                return false;
            }
            $access_token_id = $resData[0]['access_token_id'];

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('token_match_id')
                ->select('access_token_id')
                ->select('refresh_token_id')
                ->where('access_token_id',$access_token_id)
                ->table('oauth_token_match')
                ->limit(1)
            );

            if (count($resData) > 0) {
                $tokenPair = $resData[0];

                $affectedRows = $this->rdb->executeQuery($this->rdb->getDelete()
                    ->table('oauth_token_match')
                    ->where('token_match_id', $tokenPair['token_match_id']));

                $affectedRows += $this->rdb->executeQuery($this->rdb->getDelete()
                    ->table('t_oauth_access_tokens')
                    ->where('client_id', $clientID)
                    ->whereAnd('access_token_id', $access_token_id));

                $affectedRows += $this->rdb->executeQuery($this->rdb->getDelete()
                    ->table('t_oauth_refresh_tokens')
                    ->where('client_id', $clientID)
                    ->whereAnd('refresh_token_id', $tokenPair['refresh_token_id'])
                );
                return $affectedRows === 3;
            }

            $affectedRows = $this->rdb->executeQuery($this->rdb->getDelete()
                ->table('t_oauth_access_tokens')
                ->where('client_id', $clientID)
                ->whereAnd('access_token_id', $access_token_id)
            );
            return $affectedRows === 1;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @param string $clientID
     * @param string $refresh_token
     * @return bool
     * @throws Throwable
     */
    public function executeRevokeRefreshToken(string $clientID, string $refresh_token): bool
    {
        try {
            // make connection
            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
            ->select('refresh_token_id')
            ->table('t_oauth_refresh_tokens')
            ->where('refresh_token', $refresh_token)
            ->limit(1));

            if (count($resData) < 1) {
                return false;
            }

            $refresh_token_id = $resData[0]['refresh_token_id'];

            $resData = $this->rdb->executeQuery($this->rdb->getSelect()
            ->select('token_match_id')
            ->select('access_token_id')
            ->select('refresh_token_id')
            ->where('refresh_token_id', $refresh_token_id)
            ->table('oauth_token_match')
            ->limit(1));

            if (count($resData) > 0) {
                $tokenPair = $resData[0];
                $affectedRows = $this->rdb->executeQuery($this->rdb->getDelete()
                ->table('oauth_token_match')
                ->where('token_match_id', $tokenPair['token_match_id']));

                $affectedRows += $this->rdb->executeQuery($this->rdb->getDelete()
                    ->table('t_oauth_refresh_tokens')
                    ->where('client_id', $clientID)
                    ->whereAnd('refresh_token_id', $refresh_token_id)
                );

                $affectedRows += $this->rdb->executeQuery($this->rdb->getDelete()
                    ->table('t_oauth_access_tokens')
                    ->where('client_id', $clientID)
                    ->whereAnd('access_token_id', $tokenPair['access_token_id'])
                );
                return $affectedRows === 3;
            }

            $affectedRows = $this->rdb->executeQuery($this->rdb->getDelete()
                ->table('t_oauth_refresh_tokens')
                ->where('client_id', $clientID)
                ->whereAnd('refresh_token_id', $refresh_token_id)
            );
            return $affectedRows === 1;

        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }

    /**
     * @throws Exception
     */
    public function executeSetOAuthTokenMatch(SetOAuthTokenMatchDto $dto): bool
    {
        $data = $dto->getData();

        $access_token_id = $data['access_token_id'];
        $refresh_token_id = $data['refresh_token_id'];
        $access_token = $data['access_token'];
        $refresh_token = $data['refresh_token'];

        try {
            $this->rdb->makeConnection();

            if (!$access_token_id) {
                $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('access_token_id')
                ->table('t_oauth_access_tokens')
                ->where('access_token', $access_token)
                ->limit(1));

                if (count($resData) < 1) {
                    return false;
                }
                $access_token_id = $resData[0]['access_token_id'];
            }

            if (!$refresh_token_id) {
                $resData = $this->rdb->executeQuery($this->rdb->getSelect()
                ->select('refresh_token_id')
                ->table('t_oauth_refresh_tokens')
                ->where('refresh_token', $refresh_token)
                ->limit(1));

                if (count($resData) < 1) {
                    return false;
                }
                $refresh_token_id = $resData[0]['refresh_token_id'];
            }

            $this->rdb->executeQuery($this->rdb->getInsert()
                ->insert('refresh_token_id', $refresh_token_id)
                ->insert('access_token_id', $access_token_id)
                ->table('oauth_token_match'));
            return true;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            return false;
        }
    }
}
