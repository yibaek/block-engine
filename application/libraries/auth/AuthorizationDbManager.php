<?php
namespace libraries\auth;

use Exception;
use Throwable;
use OAuth2\Storage\ScopeInterface;
use OAuth2\Storage\JwtBearerInterface;
use OAuth2\Storage\PublicKeyInterface;
use OAuth2\Storage\AccessTokenInterface;
use OAuth2\Storage\RefreshTokenInterface;
use OAuth2\Storage\UserCredentialsInterface;
use OAuth2\Storage\AuthorizationCodeInterface;
use OAuth2\Storage\ClientCredentialsInterface;
use OAuth2\OpenID\Storage\UserClaimsInterface;
use OAuth2\OpenID\Storage\AuthorizationCodeInterface as OpenIDAuthorizationCodeInterface;

use models\rdb\IRdbMgr;
use models\rdb\dtos\SetOAuthTokenMatchDto;
use libraries\auth\simplekey\SimpleKey;

class AuthorizationDbManager implements
    AuthorizationCodeInterface,
    AccessTokenInterface,
    ClientCredentialsInterface,
    UserCredentialsInterface,
    RefreshTokenInterface,
    JwtBearerInterface,
    ScopeInterface,
    PublicKeyInterface,
    UserClaimsInterface,
    OpenIDAuthorizationCodeInterface
{
    private $db;
    private $clientManager;

    /**
     * AuthorizationDbManager constructor.
     * @param IRdbMgr $rdb
     */
    public function __construct(IRdbMgr $rdb)
    {
        $this->db = $rdb;
    }

    /**
     * @param AuthorizationClientManager $clientManager
     * @return $this
     */
    public function setClientManager(AuthorizationClientManager $clientManager): self
    {
        $this->clientManager = $clientManager;
        return $this;
    }

    /**
     * @param int $appID
     * @param int $credentialTarget
     * @param int $credentialID
     * @return array
     * @throws Throwable
     */
    public function getCertificationMatchData(int $appID, int $credentialTarget, int $credentialID): array
    {
        return $this->db->getHandler()->executeGetCredential($appID, $credentialTarget, $credentialID);
    }

    /**
     * @param int $credentialTarget
     * @param int $credentialID
     * @return array
     */
    public function getCertificationMatchDataForTransactionLog(int $credentialTarget, int $credentialID): array
    {
        return $this->db->getHandler()->executeGetCredentialWithoutAppID($credentialTarget, $credentialID);
    }

    /**
     * @param $client_id
     * @param null $client_secret
     * @return bool
     */
    public function checkClientCredentials($client_id, $client_secret = null): bool
    {
        $clients = $this->db->getHandler()->executeGetOAuthClients($client_id);
        if (empty($clients)) {
            return false;
        }

        // make this extensible
        return $clients['client_secret'] === $client_secret;
    }

    /**
     * @param $client_id
     * @return bool
     */
    public function isPublicClient($client_id): bool
    {
        $clients = $this->db->getHandler()->executeGetOAuthClients($client_id);
        if (empty($clients)) {
            return false;
        }

        return empty($clients['client_secret']);
    }

    /**
     * @param $client_id
     * @return array
     */
    public function getClientDetails($client_id): array
    {
        $clients = $this->db->getHandler()->executeGetOAuthClients($client_id);
        if (empty($clients)) {
            return [];
        }

        return $clients;
    }

    /**
     * @param string|null $clientID
     * @return array
     */
    public function getKey(string $clientID = null): array
    {
        return $this->db->getHandler()->executeGetAuthorization($clientID ?? $this->clientManager->getClientID());
    }

    /**
     * @param $client_id
     * @param null $client_secret
     * @param null $redirect_uri
     * @param null $grant_types
     * @param string|null $scope
     * @param null $user_id
     * @return bool
     */
    public function setClientDetails($client_id, $client_secret = null, $redirect_uri = null, $grant_types = null, $scope = null, $user_id = null): bool
    {
        return $this->db->getHandler()->executeSetOAuthClients($client_id, $client_secret, $redirect_uri, $grant_types, $scope, $user_id);
    }

    /**
     * @param $client_id
     * @return bool
     */
    public function deleteClientDetails($client_id): bool
    {
        return $this->db->getHandler()->executeDelOAuthClients($client_id);
    }

    /**
     * @param $client_id
     * @param $client_secret
     * @return bool
     */
    public function setKey($client_id, $client_secret): bool
    {
        $this->db->getHandler()->executeAddAuthorization($this->clientManager->getTypeCode(), $this->clientManager->getCredentialID(), $this->clientManager->getEnvironment(), $client_id, $client_secret);
        if ($this->clientManager->getType() === SimpleKey::AUTHORIZATION_TYPE) {
            return true;
        }

        return $this->setClientDetails($client_id, $client_secret, null, null, null, $this->clientManager->getCredentialID());
    }

    /**
     * @return bool
     * @throws Throwable
     */
    public function deleteKey(): bool
    {
        $this->db->getHandler()->executeDeleteAuthorization($this->clientManager->getClientID());

        if ($this->clientManager->getType() === SimpleKey::AUTHORIZATION_TYPE) {
            return true;
        }

        return $this->deleteClientDetails($this->clientManager->getClientID());
    }

    /**
     * @param $client_id
     * @param $grant_type
     * @return bool
     */
    public function checkRestrictedGrantType($client_id, $grant_type): bool
    {
        $details = $this->getClientDetails($client_id);
        if (isset($details['grant_types'])) {
            $grant_types = explode(' ', $details['grant_types']);

            return in_array($grant_type, (array)$grant_types, true);
        }

        // if grant_types are not defined, then none are restricted
        return true;
    }

    /**
     * @param $oauth_token
     * @return array
     */
    public function getAccessToken($oauth_token): array
    {
        $resultSet = $this->db->getHandler()->executeGetOAuthAccessTokens($oauth_token);
        if (count($resultSet) < 1) {
            return [];
        }

        // convert date string back to timestamp
        $resultSet['expires'] = strtotime($resultSet['expires']);

        return $resultSet;
    }

    /**
     * @param $oauth_token
     * @param $client_id
     * @param $user_id
     * @param $expires
     * @param null $scope
     * @return bool
     */
    public function setAccessToken($oauth_token, $client_id, $user_id, $expires, $scope = null): bool
    {
        return $this->db->getHandler()->executeSetOAuthAccessTokens($oauth_token, $client_id, date('Y-m-d H:i:s', $expires), $user_id ?? null, $scope);
    }

    /**
     * @param $oauth_token
     * @return bool
     */
    public function unsetAccessToken($oauth_token): bool
    {
        $this->db->getHandler()->executeDelOAuthAccessTokens($oauth_token);
        return true;
    }

    /**
     * 생성된 Access token과 Refresh token 를 pair 로 만들어 준다.
     * @throws Exception
     */
    public function makeAccessTokenPair($access_token, $refresh_token): bool
    {
        $dto = new SetOAuthTokenMatchDto([
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
        ]);
        return $this->db->getHandler()->executeSetOAuthTokenMatch($dto);
    }

    /**
     * @param $client_id
     * @return string|bool
     */
    public function getClientScope($client_id)
    {
        if (!$clientDetails = $this->getClientDetails($client_id)) {
            return false;
        }

        return $clientDetails['scope'] ?? false;
    }

    public function getPrivateKey($client_id = null)
    {
        return $this->db->getHandler()->executeGetPrivateKey($client_id);
    }

    /**
     * @param null $client_id
     * @return string
     */
    public function getEncryptionAlgorithm($client_id = null): string
    {
        return $this->db->getHandler()->executeGetEncryptionAlgorithm($client_id);
    }

    /**
     * @param $refresh_token
     * @param $client_id
     * @param $user_id
     * @param $expires
     * @param null $scope
     * @return bool
     */
    public function setRefreshToken($refresh_token, $client_id, $user_id, $expires, $scope = null): bool
    {
        return $this->db->getHandler()->executeSetOAuthRefreshTokens($refresh_token, $client_id, $user_id ?? null, date('Y-m-d H:i:s', $expires), $scope);
    }

    /**
     * @param $code
     * @return array
     */
    public function getAuthorizationCode($code): array
    {
        $codes = $this->db->getHandler()->executeGetOAuthAuthorizationCodes($code);
        if (empty($codes)) {
            return [];
        }

        // convert date string back to timestamp
        $codes['expires'] = strtotime($codes['expires']);

        return $codes;
    }

    /**
     * @param $code
     * @param $client_id
     * @param $user_id
     * @param $redirect_uri
     * @param $expires
     * @param null $scope
     * @param null $id_token
     * @return bool
     */
    public function setAuthorizationCode($code, $client_id, $user_id, $redirect_uri, $expires, $scope = null, $id_token = null): bool
    {
        if ($id_token !== null) {
            return $this->db->getHandler()->executeSetOAuthAuthorizationCodesWithIdToken($code, $client_id, $user_id, $redirect_uri, date('Y-m-d H:i:s', $expires), $scope, $id_token);
        }

        return $this->db->getHandler()->executeSetOAuthAuthorizationCodes($code, $client_id, $user_id, $redirect_uri, date('Y-m-d H:i:s', $expires), $scope);
    }

    /**
     * @param $code
     * @return bool
     */
    public function expireAuthorizationCode($code): bool
    {
        return $this->db->getHandler()->executeDelOAuthAuthorizationCodes($code);
    }

    /**
     * @param $username
     * @return array
     * @throws Throwable
     */
    public function getUser($username): array
    {
        $user = $this->db->getHandler()->executeGetOAuthUser($username);
        if (empty($user)) {
            return [];
        }

        // the default behavior is to use "username" as the user_id
        $user['user_id'] = $username;

        return $user;
    }

    /**
     * @param $username
     * @param $password
     * @param null $firstName
     * @param null $lastName
     * @return bool
     */
    public function setUser($username, $password, $firstName = null, $lastName = null): bool
    {
        return $this->db->getHandler()->executeSetOAuthUser($username, $this->hashPassword($password), $firstName, $lastName);
    }

    /**
     * @param $user
     * @param $password
     * @return bool
     */
    protected function checkPassword($user, $password): bool
    {
        return $user['password'] === $this->hashPassword($password);
    }

    /**
     * @param $password
     * @return string
     */
    protected function hashPassword($password): string
    {
        return hash('sha256', $password);
    }

    /**
     * @param $username
     * @param $password
     * @return bool
     * @throws Throwable
     */
    public function checkUserCredentials($username, $password): bool
    {
        return $this->db->getHandler()->executeCheckOAuthUser($username, $this->hashPassword($password));
    }

    /**
     * @param $username
     * @return array
     * @throws Throwable
     */
    public function getUserDetails($username): array
    {
        return $this->getUser($username);
    }

    /**
     * @param $refresh_token
     * @return array
     */
    public function getRefreshToken($refresh_token): array
    {
        $token = $this->db->getHandler()->executeGetOAuthRefreshTokens($refresh_token);
        if (empty($token)) {
            return [];
        }

        // convert date string back to timestamp
        $token['expires'] = strtotime($token['expires']);

        return $token;
    }

    /**
     * @param $refresh_token
     * @return bool
     */
    public function unsetRefreshToken($refresh_token): bool
    {
        return $this->db->getHandler()->executeDelOAuthRefreshTokens($refresh_token);
    }

    /**
     * @param $scope
     * @return bool
     * @throws Throwable
     */
    public function scopeExists($scope): bool
    {
        $scope = explode(' ', $scope);

        $scopeValue = [];
        foreach ($scope as $value) {
            $scopeValue[] = '\''.$value.'\'';
        }

        $existCount = $this->db->getHandler()->executeGetOAuthScopesCount(implode(',', $scopeValue));
        return $existCount === count($scope);
    }

    public function getDefaultScope($client_id = null)
    {
         $scopes = $this->db->getHandler()->executeGetOAuthScopesDefault();
         if (empty($scopes)) {
             return false;
         }

        $defaultScopes = array_map(static function ($row) {
            return $row['scope'];
            }, $scopes);

        return implode(' ', $defaultScopes);
    }

    public function getPublicKey($client_id = null)
    {
        return $this->db->getHandler()->executeGetOAuthPublicKey($client_id);
    }

    /**
     * @param string $client_id
     * @param string $oauth_token
     * @return bool
     */
    public function revokeAccessToken(string $client_id, string $oauth_token): bool
    {
        return $this->db->getHandler()->executeRevokeAccessToken($client_id, $oauth_token);
    }

    /**
     * @param string $client_id
     * @param string $refresh_token
     * @return bool
     */
    public function revokeRefreshToken(string $client_id, string $refresh_token): bool
    {
        return $this->db->getHandler()->executeRevokeRefreshToken($client_id, $refresh_token);
    }



//
//    /**
//     * @param $userID
//     * @param $claims
//     * @return array
//     */
//    public function getUserClaims($userID, $claims): array
//    {
//        if (!$userDetails = $this->getUserDetails($userID)) {
//            return false;
//        }
//
//        $claims = explode(' ', trim($claims));
//        $userClaims = array();
//
//        // for each requested claim, if the user has the claim, set it in the response
//        $validClaims = explode(' ', self::VALID_CLAIMS);
//        foreach ($validClaims as $validClaim) {
//            if (in_array($validClaim, $claims)) {
//                if ($validClaim == 'address') {
//                    // address is an object with subfields
//                    $userClaims['address'] = $this->getUserClaim($validClaim, $userDetails['address'] ?: $userDetails);
//                } else {
//                    $userClaims = array_merge($userClaims, $this->getUserClaim($validClaim, $userDetails));
//                }
//            }
//        }
//
//        return $userClaims;
//    }
//
//    /**
//     * @param string $claim
//     * @param array $userDetails
//     * @return array
//     */
//    protected function getUserClaim(string $claim, array $userDetails): array
//    {
//        $userClaims = array();
//        $claimValuesString = constant(sprintf('self::%s_CLAIM_VALUES', strtoupper($claim)));
//        $claimValues = explode(' ', $claimValuesString);
//
//        foreach ($claimValues as $value) {
//            $userClaims[$value] = isset($userDetails[$value]) ? $userDetails[$value] : null;
//        }
//
//        return $userClaims;
//    }
//

//    /**
//     * @param $clientID
//     * @param $subject
//     * @return bool|mixed
//     * @throws Throwable
//     */
//    public function getClientKey($clientID, $subject)
//    {
//        [$returnVal, $resultSet] = $this->db->getHandler()->executeGetOAuthJwt($clientID, $subject);
//        if (count($resultSet) < 1) return false;
//
//        return $resultSet[0]['public_key'];
//    }
//
//    /**
//     * @param $clientID
//     * @param $subject
//     * @param $audience
//     * @param $expires
//     * @param $jti
//     * @return array|null
//     * @throws Throwable
//     */
//    public function getJti($clientID, $subject, $audience, $expires, $jti)
//    {
//        [$returnVal, $jtiInfo] = $this->db->getHandler()->executeGetOAuthJti($clientID, $subject, $audience, $expires, $jti);
//
//        // set resdata
//        $resData = null;
//        foreach ($jtiInfo as $data) {
//            $resData = [
//                'issuer' => $data['issuer'],
//                'subject' => $data['subject'],
//                'audience' => $data['audiance'],
//                'expires' => $data['expires'],
//                'jti' => $data['jti']
//            ];
//        }
//
//        return $resData;
//    }
//
//    /**
//     * @param $clientID
//     * @param $subject
//     * @param $audience
//     * @param $expires
//     * @param $jti
//     * @return bool
//     * @throws Throwable
//     */
//    public function setJti($clientID, $subject, $audience, $expires, $jti): bool
//    {
//        [$returnVal, $resultSet] = $this->db->getHandler()->executeSetOAuthJti($clientID, $subject, $audience, $expires, $jti);
//
//        return $returnVal;
//    }


    public function getClientKey($client_id, $subject)
    {
        // TODO: Implement getClientKey() method.
    }

    public function getJti($client_id, $subject, $audience, $expiration, $jti)
    {
        // TODO: Implement getJti() method.
    }

    public function setJti($client_id, $subject, $audience, $expiration, $jti)
    {
        // TODO: Implement setJti() method.
    }

    public function getUserClaims($user_id, $scope)
    {
        // TODO: Implement getUserClaims() method.
    }
}