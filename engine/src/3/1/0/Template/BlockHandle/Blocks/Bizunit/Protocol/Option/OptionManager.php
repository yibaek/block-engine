<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Option;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Option\Http\AllowRedirects;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Option\Http\Proxy;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Option\Http\SSLCert;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Option\Http\SSLKey;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Option\Http\Timeout;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Option\Http\Verify;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Option\Soap\ConnectTimeout;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Option\Soap\SSL;
use Ntuple\Synctree\Template\BlockHandle\IBlock;

class OptionManager implements IBlock
{
    public const TYPE = 'protocol-option';

    private $storage;
    private $block;

    /**
     * OptionManager constructor.
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
            case Timeout::ACTION:
                $this->block = (new Timeout($this->storage))->setData($data);
                return $this;

            case Verify::ACTION:
                $this->block = (new Verify($this->storage))->setData($data);
                return $this;

            case SSL::ACTION:
                $this->block = (new SSL($this->storage))->setData($data);
                return $this;

            case ConnectTimeout::ACTION:
                $this->block = (new ConnectTimeout($this->storage))->setData($data);
                return $this;

            case Proxy::ACTION:
                $this->block = (new Proxy($this->storage))->setData($data);
                return $this;

            case SSLCert::ACTION:
                $this->block = (new SSLCert($this->storage))->setData($data);
                return $this;

            case SSLKey::ACTION:
                $this->block = (new SSLKey($this->storage))->setData($data);
                return $this;

            case AllowRedirects::ACTION:
                $this->block = (new AllowRedirects($this->storage))->setData($data);
                return $this;

            default:
                throw new \RuntimeException('invalid protocol option block action[action:'.$data['action'].']');
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
     * @return array
     */
    public function do(array &$blockStorage): ?array
    {
        return $this->block->do($blockStorage);
    }
}