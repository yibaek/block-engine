<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion\Subject;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockAggregator;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class SubjectCreate implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'saml-assertion-subject-create';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $nameID;
    private $confirmations;

    /**
     * SubjectCreate constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $nameID
     * @param BlockAggregator|null $confirmations
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $nameID = null, BlockAggregator $confirmations = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->nameID = $nameID;
        $this->confirmations = $confirmations;
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
        $this->nameID = $this->setBlock($this->storage, $data['template']['nameid']);
        $this->confirmations = $this->setBlocks($this->storage, $data['template']['confirmation']);

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
                'nameid' => $this->nameID->getTemplate(),
                'confirmation' => $this->getTemplateEachConfirmation()
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
            return [
                'name_id' => $this->getNameId($blockStorage),
                'confirmation' => $this->getConfirmations($blockStorage),
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-SAML-Assertion-Subject-Create'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @return array
     */
    private function getTemplateEachConfirmation(): array
    {
        $resData = [];
        foreach ($this->confirmations as $confirmation) {
            $resData[] = $confirmation->getTemplate();
        }

        return $resData;
    }

    /**
     * @param array $blockStorage
     * @return array|null
     * @throws ISynctreeException
     */
    private function getNameId(array &$blockStorage): ?array
    {
        $nameID = $this->nameID->do($blockStorage);
        if (!is_null($nameID)) {
            if (!is_array($nameID)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Subject-Create: Invalid nameid: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $nameID;
    }

    /**
     * @param array $blockStorage
     * @return array
     * @throws ISynctreeException
     */
    private function getConfirmations(array &$blockStorage): array
    {
        $resData = [];
        foreach ($this->confirmations as $confirmation) {
            $data = $confirmation->do($blockStorage);
            if (!is_array($data)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Subject-Create: Invalid confirmation: Not a array type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
            $resData[] = $data;
        }

        return $resData;
    }
}