<?php declare(strict_types=1);
namespace Ntuple\Synctree\Util\Storage\Driver;

/**
 * @since SRT-10
 */
class RdbSslMode
{
    /** @var string 미사용 */
    public const DISABLED = 'disabled';

    /** @var string SSL 가능하면 사용 */
    public const PREFER = 'prefer';

    /** @var string SSL 옵션 필수 */
    public const REQUIRED = 'required';

    /** @var string `REQUIRED`와는 CA를 통해 server 인증서를 검증한다는 점이 다름. */
    public const VERIFY_CA = 'verify-ca';

    /** @var string VERIFY_CA + 서버 인증서의 CN 까지 검증. CN과 서버 이름(도메인)이 다르면 연결이 거부됨. */
    public const VERIFY_FULL = 'verify-full';
}