<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\EndPoint;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class EndPointManager implements IBlock
{
    public const TYPE = 'protocol-end-point';

    private $storage;
    private $block;

    /**
     * EndPointManager constructor.
     * @param PlanStorage $storage
     * @param IBlock|null $block
     */
    public function __construct(PlanStorage $storage, IBlock $block = null)
    {
        $this->storage = $storage;
        $this->block = $block;
    }

    /**
     * @param array $data
     * @return IBlock
     * @throws Exception
     */
    public function setData(array $data): IBlock
    {
        switch ($data['action']) {
            case Url::ACTION:
                $this->block = (new Url($this->storage))->setData($data);
                return $this;

            case SecureVerificationCode::ACTION:
                $this->block = (new SecureVerificationCode($this->storage))->setData($data);
                return $this;

            case Wsdl::ACTION:
                $this->block = (new Wsdl($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid protocol end-point block action[action:'.$data['action'].']');
        }
    }

    /**
     * @return array
     */
    public function getTemplate(): array
    {
        return $this->block->getTemplate();
    }

    /**
     * @param array $blockStorage
     * @return mixed
     */
    public function do(array &$blockStorage)
    {
        return $this->block->do($blockStorage);
    }
}