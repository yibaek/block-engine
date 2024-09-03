<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous;

use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;

class CRSACryptoOption implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'miscellaneous';
    public const ACTION = 'crypto-option-rsa';

    public const CRYPTO_OPTION_RSA_OPENSSL_PKCS1_PADDING = 1;
    public const CRYPTO_OPTION_RSA_OPENSSL_SSLV23_PADDING = 2;
    public const CRYPTO_OPTION_RSA_OPENSSL_NO_PADDING = 3;
    public const CRYPTO_OPTION_RSA_OPENSSL_PKCS1_OAEP_PADDING = 4;

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $option;

    /**
     * CRSACryptoOption constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param int|null $option
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, int $option = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->option = $option;
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
        $this->option = $data['template']['option'];

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
                'option' => $this->option
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return int
     */
    public function do(array &$blockStorage): int
    {
        return $this->option;
    }
}