<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Bizunit;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class BizunitCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'bizunit';
    public const ACTION = 'create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $request;
    private $response;
    private $statements;

    /**
     * BizunitCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $request
     * @param BlockAggregator|null $statements
     * @param IBlock|null $response
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $request = null, BlockAggregator $statements = null, IBlock $response = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->request = $request;
        $this->response = $response;
        $this->statements = $statements;
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
        $this->request = $this->setBlock($this->storage, $data['template']['request']);
        $this->response = $this->setBlock($this->storage, $data['template']['response']);
        $this->statements = $this->setBlocks($this->storage, $data['template']['statements']);

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
                'request' => $this->request->getTemplate(),
                'statements' => $this->getTemplateEachStatement(),
                'response' => $this->response->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return void
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): void
    {
        try {
            // do request
            $this->request->do($blockStorage);

            // do satements
            foreach ($this->statements as $statement) {
                $statement->do($blockStorage);
            }

            // do response
            $this->response->do($blockStorage);
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Bizunit-Create'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachStatement(): array
    {
        $resData = [];
        foreach ($this->statements as $statement) {
            $resData[] = $statement->getTemplate();
        }

        return $resData;
    }
}