<?php
namespace Ntuple\Synctree\Template\BlockHandle;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\BizunitResponseException;
use Ntuple\Synctree\Exceptions\Inner\DebugBreakPointException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit\Protocol\Context\ProtocolContext;
use Throwable;

class BlockHandler
{
    use BlockHandleTrait;

    public const BLOCK_HANDLE_TYPE = 'block-handle';

    private $storage;
    private $type;
    private $payload;

    /**
     * BlockHandler constructor.
     * @param PlanStorage $storage
     * @param BlockAggregator|null $payload
     */
    public function __construct(PlanStorage $storage, BlockAggregator $payload = null)
    {
        $this->storage = $storage;
        $this->type = self::BLOCK_HANDLE_TYPE;
        $this->payload = $payload;
    }

    /**
     * @param array $data
     * @return BlockHandler
     * @throws Exception
     */
    public function setData(array $data): BlockHandler
    {
        $this->type = $data['type'];
        $this->payload = $this->setBlocks($this->storage, $data['template']['payload']);

        return $this;
    }

    /**
     * @return array
     */
    public function getTemplate(): array
    {
        return [
            'type' => $this->type,
            'template' => [
                'payload' => $this->getTemplateEachBlock()
            ]
        ];
    }

    /**
     * @return array
     * @throws Throwable
     */
    public function do(): array
    {
        $result = [];
        try {
            foreach ($this->payload as $block) {
                $block->do($result);
            }
            return $result;
        } catch (BizunitResponseException | DebugBreakPointException $ex) {
            return (new ProtocolContext($ex->getHeader(), $ex->getBody(), $ex->getStatusCode()))->getData();
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachBlock(): array
    {
        $resData = [];
        foreach ($this->payload as $block) {
            $resData[] = $block->getTemplate();
        }

        return $resData;
    }
}