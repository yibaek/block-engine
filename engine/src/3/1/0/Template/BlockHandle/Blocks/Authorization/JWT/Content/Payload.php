<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\JWT\Content;

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

class Payload implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'jwt-content-payload';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $claim;
    private $addClaim;

    /**
     * Payload constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $claim
     * @param IBlock|null $addClaim
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $claim = null, IBlock $addClaim = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->claim = $claim;
        $this->addClaim = $addClaim;
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
        $this->claim = $this->setBlock($this->storage, $data['template']['claim']);
        $this->addClaim = $this->setBlock($this->storage, $data['template']['add-claim']);

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
                'claim' => $this->claim->getTemplate(),
                'add-claim' => $this->addClaim->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): array
    {
        try {
            return array_merge($this->getClaim($blockStorage), $this->getAddClaim($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-JWT-Content-Payload'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getClaim(array &$blockStorage): array
    {
        $claim = $this->claim->do($blockStorage);
        if (!is_array($claim)) {
            throw (new InvalidArgumentException('Authorization-JWT-Content-Payload: Invalid claim: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $claim;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getAddClaim(array &$blockStorage): array
    {
        $addClaim = $this->addClaim->do($blockStorage);
        if (is_null($addClaim)) {
            return [];
        }

        if (!is_array($addClaim)) {
            throw (new InvalidArgumentException('Authorization-JWT-Content-Payload: Invalid add claim: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }

        return $addClaim;
    }
}