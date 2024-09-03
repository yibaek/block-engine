<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SimpleKey;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Authorization\SimpleKey\ValidateSimpleKey;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class SimpleKeyVerify implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'simplekey-verify';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $key;

    /**
     * SimpleKeyVerify constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $key
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $key = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->key = $key;
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
        $this->key = $this->setBlock($this->storage, $data['template']['key']);

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
                'key' => $this->key->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws GuzzleException
     * @throws ISynctreeException
     * @throws Throwable
     */
    public function do(array &$blockStorage): array
    {
        try {
            return (new ValidateSimpleKey($this->storage, $this->getKey($blockStorage)))
                ->run($this->storage->getOrigin()->getHeaders(), $this->storage->getOrigin()->getBodys());
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-SimpleKey'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getKey(array &$blockStorage): ?string
    {
        $key = $this->key->do($blockStorage);
        if (!is_null($key)) {
            if (!is_string($key)) {
                throw (new InvalidArgumentException('Authorization-SimpleKey: Invalid key: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $key;
    }
}