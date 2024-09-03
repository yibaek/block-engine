<?php declare(strict_types=1);
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\System\Process;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

/**
 * sleep(seconds: int): void
 *
 * @since SRT-127
 */
class Sleep implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'system';
    public const ACTION = 'process-sleep';

    private $type;
    private $action;

    /** @var PlanStorage */
    private $storage;

    /** @var ExtraManager|null */
    private $extra;

    /** @var IBlock|null block returns positive int */
    private $delay;

    /**
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $delay block returns positive int
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $delay = null)
    {
        $this->type = self::TYPE;
        $this->action = self::ACTION;

        $this->storage = $storage;
        $this->extra = $extra ?? new ExtraManager($storage);

        $this->delay = $delay;
    }

    /**
     * @throws Exception
     */
    public function setData(array $data): IBlock
    {
        $this->type = $data['type'];
        $this->action = $data['action'];
        $this->extra = $this->setExtra($this->storage, $data['extra'] ?? []);
        $this->delay = $this->setBlock($this->storage, $data['template']['delay']);

        return $this;
    }

    public function getTemplate(): array
    {
        return [
            'type' => $this->type,
            'action' => $this->action,
            'extra' => $this->extra->getData(),
            'template' => [
                'delay' => $this->delay->getTemplate()
            ]
        ];
    }

    /**
     * @throws ISynctreeException
     * @throws Exception
     */
    public function do(array &$blockStorage): void
    {
        try {
            $duration = $this->getDelay($blockStorage);
            sleep($duration);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('System-Process-Sleep'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }
    }

    /**
     * @throws ISynctreeException
     */
    public function getDelay(array &$blockStorage): int
    {
        $delay = $this->delay->do($blockStorage);

        if (!is_int($delay) || 0 > $delay) {
            throw (new InvalidArgumentException('System-Process-Sleep: delay must be a positive integer.'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }

        return $delay;
    }
}