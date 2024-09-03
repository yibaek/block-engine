<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous;

use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class CSamlSubjectNameIdFormat implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'miscellaneous';
    public const ACTION = 'saml-assertion-subject-nameid';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $format;

    /**
     * CSamlSubjectNameIdFormat constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param string|null $format
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, string $format = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->format = $format;
    }

    /**
     * @param array $data
     * @return IBlock
     */
    public function setData(array $data): IBlock
    {
        $this->type = $data['type'];
        $this->action = $data['action'];
        $this->extra = $this->setExtra($this->storage, $data['extra'] ?? []);
        $this->format = $data['template']['format'];

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
                'format' => $this->format
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return string
     */
    public function do(array &$blockStorage): string
    {
        return $this->format;
    }
}