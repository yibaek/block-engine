<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Property;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class CustomUtilProperty implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'property';
    public const ACTION = 'custom-util';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $id;
    private $name;
    private $description;
    private $createdDate;
    private $updatedDate;

    /**
     * CustomUtilProperty constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $id
     * @param IBlock|null $name
     * @param IBlock|null $description
     * @param IBlock|null $createdDate
     * @param IBlock|null $updatedDate
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $id = null, IBlock $name = null, IBlock $description = null, IBlock $createdDate = null, IBlock $updatedDate = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->createdDate = $createdDate;
        $this->updatedDate = $updatedDate;
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
        $this->description = $this->setBlock($this->storage, $data['template']['description']);
        $this->createdDate = $this->setBlock($this->storage, $data['template']['created-date']);
        $this->updatedDate = $this->setBlock($this->storage, $data['template']['updated-date']);

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
                'description' => $this->description->getTemplate(),
                'created-date' => $this->createdDate->getTemplate(),
                'updated-date' => $this->updatedDate->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return array
     */
    public function do(array &$blockStorage): array
    {
        return [
            'id' => $this->id->do($blockStorage),
            'name' => $this->name->do($blockStorage),
            'description' => $this->description->do($blockStorage),
            'created-date' => $this->createdDate->do($blockStorage),
            'updated-date' => $this->updatedDate->do($blockStorage)
        ];
    }
}