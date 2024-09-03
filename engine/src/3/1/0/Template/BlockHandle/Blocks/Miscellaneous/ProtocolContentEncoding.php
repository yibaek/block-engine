<?php declare(strict_types=1);
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous;

use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

/**
 * HTTP 및 관련 프로토콜에 사용되는 Content-Encoding 헤더에 적용 가능한 값을 열거한다.
 *
 * @since SRT-231
 */
class ProtocolContentEncoding implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'miscellaneous';
    public const ACTION = 'protocol-content-encoding';

    /**
     * @var string[]
     * @see https://www.iana.org/assignments/http-parameters/http-parameters.xhtml#content-coding
     */
    public const OPTIONS = [
        'aes128gcm',
        'br',
        'compress',
        'deflate',
        'exi',
        'gzip',
        'identity',
        'pack200-gzip',
        'zstd'
    ];

    /** @var string */
    private $type;
    /** @var string */
    private $action;

    /** @var PlanStorage */
    private $storage;

    /** @var ExtraManager */
    private $extra;

    /** @var string|null  */
    private $value;

    /**
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param string|null $value
     */
    public function __construct(
        PlanStorage $storage,
        ?ExtraManager $extra = null,
        ?string $value = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->value = $value;
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
        $this->value = $data['template']['value'];

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
                'value' => $this->value
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return string
     */
    public function do(array &$blockStorage): string
    {
        return $this->value;
    }
}