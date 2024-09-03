<?php
namespace models\rdb;

use Exception;
use Throwable;
use models\rdb\dtos\SetOAuthTokenMatchDto;

/**
 * 찾으려는 bizunit 이 없는 경우에 발생하는 예외
 */
class NotFoundBizunit extends Exception {}

interface IRDbHandler
{
    public function executeGetAccountInfo(int $appid = null, string $bizunitID = null): array;
    public function executeGetLibraryAccountInfo(string $libraryID): array;
    public function executeGetPlaygroundAccountInfo(): array;
    /**
     * path 정보와 method 정보를 바탕으로 bizunit 정보를 가져온다
     * @param string $path
     * @param string $method
     * @param string $masterAccountNo
     * @return array
     * @throws NotFoundBizunit
     * @throws Throwable
     */
    public function executeGetBizUnitProxyInfo(string $path, string $method, string $masterAccountNo): array;
    public function executeGetProxyIDByBizunitInfo(array $bizunitInfo): int;
    public function executeGetPlan(string $planEnvironment, string $bizunitID, string $bizunitVersion, string $revisionID): array;
    public function executeSetApiLog(array $accountInfo, array $bizunitInfo, array $logData): bool;
    public function executeGetShardInfo(int $slaveID): string;
    public function executeAddCredential(int $appID, int $credentialID, string $sequenceID = null): int;
    public function executeGetCredential(int $appID, int $credentialTarget, int $credentialID): array;
    public function executeGetCredentialWithoutAppID(int $credentialTarget, int $credentialID): array;
    public function executeDeleteCredential(int $appID, int $credentialID): bool;
    public function executeAddAuthorization(int $type, int $credentialID, string $environment, string $clientID, string $clientSecret, string $sequenceID = null): int;
    public function executeGetAuthorization(string $clientID): array;
    public function executeDeleteAuthorization(string $clientID): bool;
    public function executeGetOAuthClients(string $clientID): array;
    public function executeDelOAuthClients(string $clientID): bool;
    public function executeSetOAuthClients(string $clientID, string $clientSecret = null, string $redirectURI = null, string $grantTypes = null, string $scope = null, $userID = null): bool;
    public function executeGetOAuthAccessTokens(string $accessToken): array;
    public function executeSetOAuthAccessTokens(string $accessToken, string $clientID, string $expires, $userID, string $scope = null): bool;
    public function executeDelOAuthAccessTokens(string $accessToken): bool;
    public function executeVerificationCertificationForPortal(int $portalAppId, int $studioAppId): bool;
    public function executeGetPortalCredential(int $portalCredentialID): array;
    public function executeSetConsoleLog(array $accountInfo, array $bizunitInfo, array $message): bool;
    public function executeGetPrivateKey(string $clientID): string;
    public function executeGetEncryptionAlgorithm(string $clientID): string;
    public function executeGetOAuthRefreshTokens(string $refreshToken): array;
    public function executeSetOAuthRefreshTokens(string $refreshToken , string $clientID, $userID, string $expires, string $scope = null): bool;
    public function executeDelOAuthRefreshTokens(string $refreshToken): bool;
    public function executeGetOAuthAuthorizationCodes(string $code): array;
    public function executeSetOAuthAuthorizationCodes(string $code, string $clientID, $userID, string $redirectUri, string $expires, string $scope = null): bool;
    public function executeSetOAuthAuthorizationCodesWithIdToken(string $code, string $clientID, $userID, string $redirectUri, string $expires, string $scope = null, string $idToken = null): bool;
    public function executeDelOAuthAuthorizationCodes(string $code): bool;
    public function executeGetOAuthUser(string $username): array;
    public function executeSetOAuthUser(string $username, string $password, string $firstName = null, string $lastName = null): bool;
    public function executeCheckOAuthUser(string $username, string $password): bool;
    public function executeGetOAuthScopesCount(string $scopes): int;
    public function executeGetOAuthScopesDefault(): array;
    public function executeGetOAuthPublicKey(string $clientID): string;
    public function executeRevokeAccessToken(string $clientID, string $token): bool;
    public function executeRevokeRefreshToken(string $clientID, string $refresh_token): bool;
    public function executeSetOAuthTokenMatch(SetOAuthTokenMatchDto $dto): bool;
}
