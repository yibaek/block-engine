<?php declare(strict_types=1);
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Random;

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
 * @since SRT-129
 */
class RandomInteger implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'common-util';
    public const ACTION = 'random-integer';

    private $type;
    private $action;

    /** @var PlanStorage  */
    private $storage;

    /** @var ExtraManager|null */
    private $extra;

    /** @var IBlock|null minimum boundary block returns int */
    private $min;

    /** @var IBlock|null maximum boundary block returns int */
    private $max;

    /**
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $min minimum boundary block returns int
     * @param IBlock|null $max maximum boundary block returns int
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $min = null, IBlock $max = null)
    {
        $this->type = self::TYPE;
        $this->action = self::ACTION;

        $this->storage = $storage;
        $this->extra = $extra ?? new ExtraManager($storage);

        $this->min = $min;
        $this->max = $max;
    }

    /**
     * @throws Exception
     */
    public function setData(array $data): IBlock
    {
        $this->type = $data['type'];
        $this->action = $data['action'];
        $this->extra = $this->setExtra($this->storage, $data['extra'] ?? []);
        $this->min = $this->setBlock($this->storage, $data['template']['min']);
        $this->max = $this->setBlock($this->storage, $data['template']['max']);

        return $this;
    }

    public function getTemplate(): array
    {
        return [
            'type' => $this->type,
            'action' => $this->action,
            'extra' => $this->extra->getData(),
            'template' => [
                'min' => $this->min->getTemplate(),
                'max' => $this->max->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return int
     * @throws ISynctreeException|Exception
     */
    public function do(array &$blockStorage): int
    {
        try {
            $min = $this->getMinValue($blockStorage);
            $max = $this->getMaxValue($blockStorage);

            if ($min > $max) {
                throw (new InvalidArgumentException('Util-RandomInteger: Invalid range. min is greater then max.'))
                    ->setExceptionKey(self::TYPE, self::ACTION)
                    ->setExtraData($this->extra->getData());
            }

            return random_int($min, $max);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Util-RandomInteger', 0, $ex))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return int
     * @throws ISynctreeException
     */
    private function getMinValue(array &$blockStorage): int
    {
        $value = $this->min->do($blockStorage);

        if (!is_int($value)) {
            throw (new InvalidArgumentException('Util-RandomInteger: Invalid minimum boundary: Not a integer type'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }

        return $value;
    }

    /**
     * @param array $blockStorage
     * @return int
     * @throws ISynctreeException
     */
    private function getMaxValue(array &$blockStorage): int
    {
        $value = $this->max->do($blockStorage);

        if (!is_int($value)) {
            throw (new InvalidArgumentException('Util-RandomInteger: Invalid maximum boundary: Not a integer type'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }

        return $value;
    }
}