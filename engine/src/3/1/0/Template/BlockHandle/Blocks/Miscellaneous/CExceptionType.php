<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous;

use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class CExceptionType implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'miscellaneous';
    public const ACTION = 'exception-type';

    public const EXCEPTION_TYPE_BASE_EXCEPTION = 'RuntimeException';
    public const EXCEPTION_TYPE_RUNTIME = 'RuntimeException';
    public const EXCEPTION_TYPE_AUTHORIZATION_JWT = 'JWTException';
    public const EXCEPTION_TYPE_AUTHORIZATION_OAUTH = 'OAuthException';
    public const EXCEPTION_TYPE_AUTHORIZATION_SIMPLEKEY = 'SimpleKeyException';
    public const EXCEPTION_TYPE_LIMIT_EXCEEDED = 'LimitExceededException';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $exceptionType;

    /**
     * CExceptionType constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param string|null $exceptionType
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, string $exceptionType = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->exceptionType = $exceptionType;
    }

    /**
     * @param array $data
     * @return IBlock
     */
    public function setData(array $data): IBlock
    {
        $this->type = $data['type'];
        $this->action = $data['action'];
        $this->extra = $this->setExtra($this->storage, $data['extra'] ?? []);
        $this->exceptionType = $data['template']['exception-type'];

        return $this;
    }

    /**
     * @return array
     */
    public function getTemplate(): array
    {
        return [
            'type' => $this->type,
            'action' => $this->action,
            'extra' => $this->extra->getData(),
            'template' => [
                'exception-type' => $this->exceptionType
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return string
     */
    public function do(array &$blockStorage): string
    {
        return $this->exceptionType;
    }
}