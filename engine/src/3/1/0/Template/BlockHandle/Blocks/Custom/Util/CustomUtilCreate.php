<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Custom\Util;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Plan\Stack\Stack;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Ntuple\Synctree\Util\ValidationUtil;
use Throwable;

class CustomUtilCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'custom-util';
    public const ACTION = 'create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $arguments;
    private $returnValue;
    private $payload;
    private $property;

    /**
     * CustomUtilCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param BlockAggregator|null $arguments
     * @param IBlock|null $returnValue
     * @param BlockAggregator|null $payload
     * @param IBlock|null $property
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, BlockAggregator $arguments = null, IBlock $returnValue = null, BlockAggregator $payload = null, IBlock $property = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->arguments = $arguments;
        $this->returnValue = $returnValue;
        $this->payload = $payload;
        $this->property = $property;
    }

    /**
     * @param array $data
     * @return IBlock
     * @throws Exception
     */
    public function setData(array $data): IBlock
    {
        $this->type = $data['type'];
        $this->action = $data['action'];
        $this->extra = $this->setExtra($this->storage, $data['extra'] ?? []);
        $this->arguments = $this->setBlocks($this->storage, $data['template']['arguments']);
        $this->returnValue = $this->setBlock($this->storage, $data['template']['return-value']);
        $this->payload = $this->setBlocks($this->storage, $data['template']['payload']);
        $this->property = $this->setBlock($this->storage, $data['template']['property']);

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
                'arguments' => $this->getTemplateEachArgument(),
                'return-value' => $this->returnValue->getTemplate(),
                'payload' => $this->getTemplateEachPayload(),
                'property' => $this->property->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return mixed
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage)
    {
        try {
            // make stack init data
            $utilBlockStorage = [];
            foreach ($this->arguments as $argument) {
                $data = $this->getArgument($blockStorage, $argument);
                $key = key($data);
                $utilBlockStorage[$key] = $data[$key];
            }

            // push stack
            $utilStack = (new Stack($utilBlockStorage))->setCustomUtilId(bin2hex(random_bytes(8)));
            $this->storage->getStackManager()->push($utilStack);

            // execute payload
            foreach ($this->payload as $statement) {
                $statement->do($utilBlockStorage);
            }

            // get return key
            $returnKey = $this->getReturnValue($utilBlockStorage);
            if (($returnValue=$utilStack->getData($returnKey)) === null) {
                throw (new InvalidArgumentException('Library: Not found data: '.$returnKey))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }

            return $returnValue;
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Library'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        } finally {
            $this->storage->getStackManager()->pop();
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachArgument(): array
    {
        $resData = [];
        foreach ($this->arguments as $argument) {
            $resData[] = $argument->getTemplate();
        }

        return $resData;
    }

    /**
     * @return array
     */
    private function getTemplateEachPayload(): array
    {
        $resData = [];
        foreach ($this->payload as $value) {
            $resData[] = $value->getTemplate();
        }

        return $resData;
    }

    /**
     * @param array $blockStorage
     * @param mixed $argument
     * @return array
     * @throws ISynctreeException
     */
    private function getArgument(array &$blockStorage, $argument): array
    {
        $data = $argument->do($blockStorage);
        if (!is_array($data)) {
            throw (new InvalidArgumentException('Library: Invalid argument: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $data;
    }

    /**
     * @param array $result
     * @return int|string
     * @throws ISynctreeException
     */
    private function getReturnValue(array &$result)
    {
        try {
            return ValidationUtil::validateArrayKey($this->returnValue->do($result));
        } catch (\InvalidArgumentException $ex) {
            throw (new InvalidArgumentException('Library: Invalid return value: '.$ex->getMessage()))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }
}