<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Authorization\SAML\Assertion\Subject;

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

class NameID implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'authorization';
    public const ACTION = 'saml-assertion-subject-nameid';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $value;
    private $format;

    /**
     * NameID constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $value
     * @param IBlock|null $format
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $value = null, IBlock $format = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->value = $value;
        $this->format = $format;
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
        $this->value = $this->setBlock($this->storage, $data['template']['value']);
        $this->format = $this->setBlock($this->storage, $data['template']['format']);

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
                'value' => $this->value->getTemplate(),
                'format' => $this->format->getTemplate()
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
                'value' => $this->getValue($blockStorage),
                'format' => $this->getFormat($blockStorage),
            ];
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Authorization-SAML-Assertion-Subject-NameID'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getValue(array &$blockStorage): ?string
    {
        $value = $this->value->do($blockStorage);
        if (!is_null($value)) {
            if (!is_string($value)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Subject-NameID: Invalid value: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $value;
    }

    /**
     * @param array $blockStorage
     * @return string|null
     * @throws ISynctreeException
     */
    private function getFormat(array &$blockStorage): ?string
    {
        $format = $this->format->do($blockStorage);
        if (!is_null($format)) {
            if (!is_string($format)) {
                throw (new InvalidArgumentException('Authorization-SAML-Assertion-Subject-NameID: Invalid format: Not a string type'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }

        return $format;
    }
}