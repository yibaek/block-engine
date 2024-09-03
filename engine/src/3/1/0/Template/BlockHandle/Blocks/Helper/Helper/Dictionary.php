<?php declare(strict_types=1);
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Helper\Helper;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class Dictionary implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'helper';
    public const ACTION = 'dictionary';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $id;
    private $name;
    private $value;

    /**
     * Dictionary constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $id
     * @param IBlock|null $name
     * @param IBlock|null $value
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $id = null, IBlock $name = null, IBlock $value = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->id = $id;
        $this->name = $name;
        $this->value = $value;
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
        $this->id = $this->setBlock($this->storage, $data['template']['id']);
        $this->name = $this->setBlock($this->storage, $data['template']['name']);
        $this->value = $this->setBlock($this->storage, $data['template']['value']);

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
                'id' => $this->id->getTemplate(),
                'name' => $this->name->getTemplate(),
                'value' => $this->value->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return string|int|float
     */
    public function do(array &$blockStorage)
    {
        return $this->getValue($blockStorage);
    }

    /**
     * @param array $blockStorage
     * @return string|int|float
     */
    private function getValue(array &$blockStorage)
    {
        return $this->getDictionaryValue((int) $this->id->do($blockStorage));
    }

    /**
     * @param int $dictionaryDetailId
     * @return string|int|float
     */
    private function getDictionaryValue(int $dictionaryDetailId)
    {
        $dictionaryData= $this->storage->getDictionaryDataManager()->getDictionaryData($dictionaryDetailId);

        return $dictionaryData['key_value'];
    }
}