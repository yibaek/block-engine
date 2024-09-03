<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Property;

use Exception;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class Property implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'property';
    public const ACTION = 'basic';

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
     * Property constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param string|null $id
     * @param string|null $name
     * @param string|null $description
     * @param string|null $createdDate
     * @param string|null $updatedDate
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, string $id = null, string $name = null, string $description = null, string $createdDate = null, string $updatedDate = null)
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
        $this->id = $data['template']['id'];
        $this->name = $data['template']['name'];
        $this->description = $data['template']['description'];
        $this->createdDate = $data['template']['created-date'];
        $this->updatedDate = $data['template']['updated-date'];

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
                'id' => $this->id,
                'name' => $this->name,
                'description' => $this->description,
                'created-date' => $this->createdDate,
                'updated-date' => $this->updatedDate,
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
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'created-date' => $this->createdDate,
            'updated-date' => $this->updatedDate
        ];
    }
}