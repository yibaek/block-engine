<?php declare(strict_types=1);
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\Parameters;

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
 * @since SRT-231
 */
class S3ObjectParamContentEncoding implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'storage';
    public const ACTION = 'driver-s3-object-param-content-encoding';

    /**
     * @var string S3 param key
     * @see https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-s3-2006-03-01.html#putobject
     */
    public const PARAM_KEY = 'ContentEncoding';

    /** @var string */
    private $type;
    /** @var string */
    private $action;

    /** @var PlanStorage */
    private $storage;
    /** @var ExtraManager */
    private $extra;

    /** @var IBlock */
    private $value;

    /**
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $value
     */
    public function __construct(
        PlanStorage $storage,
        ExtraManager $extra = null,
        IBlock $value = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);

        $this->value = $value;
    }

    /**
     * @throws Exception
     */
    public function setData(array $data): IBlock
    {
        $this->type = $data['type'];
        $this->action = $data['action'];
        $this->extra = $this->setExtra($this->storage, $data['extra'] ?? []);
        $this->value = $this->setBlock($this->storage, $data['template']['value']);

        return $this;
    }

    public function getTemplate(): array
    {
        return [
            'type' => $this->type,
            'action' => $this->action,
            'extra' => $this->extra->getData(),
            'template' => [
                'value' => $this->value->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return array (param key, value)
     * @throws ISynctreeException
     * @throws Exception Logging failure
     */
    public function do(array &$blockStorage): array
    {
        try {
            return [self::PARAM_KEY, $this->getValue($blockStorage)];
        } catch (SynctreeException | SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE . ':' . self::ACTION);
            throw (new RuntimeException(self::getLabel()))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getValue(array &$blockStorage): string
    {
        $result = $this->value->do($blockStorage);

        if (!is_string($result)) {
            throw (new InvalidArgumentException(self::getLabel().': Not a string type.'))
                ->setExceptionKey(self::TYPE, self::ACTION)
                ->setExtraData($this->extra->getData());
        }

        return $result;
    }
}