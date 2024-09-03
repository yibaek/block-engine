<?php
namespace Ntuple\Synctree\Template\BlockHandle\Blocks\Log;

use Exception;
use Ntuple\Synctree\Exceptions\Inner\SynctreeInnerException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Exceptions\RuntimeException;
use Ntuple\Synctree\Exceptions\SynctreeException;
use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\BlockHandleTrait;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use Throwable;

class Error implements IBlock
{
    use BlockHandleTrait;

    public const TYPE = 'log';
    public const ACTION = 'error';

    private $storage;
    private $type;
    private $action;
    private $extra;
    private $message;

    /**
     * Error constructor.
     * @param PlanStorage $storage
     * @param ExtraManager|null $extra
     * @param IBlock|null $message
     */
    public function __construct(PlanStorage $storage, ExtraManager $extra = null, IBlock $message = null)
    {
        $this->storage = $storage;
        $this->type = self::TYPE;
        $this->action = self::ACTION;
        $this->extra = $extra ?? new ExtraManager($storage);
        $this->message = $message;
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
        $this->message = $this->setBlock($this->storage, $data['template']['message']);

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
                'message' => $this->message->getTemplate()
            ]
        ];
    }

    /**
     * @param array $blockStorage
     * @return mixed
     * @throws Throwable|ISynctreeException
     */
    public function do(array &$blockStorage): void
    {
        try {
            $this->storage->getLogger()->setConsoleLogType(LogMessage::CONSOLE_LOG_TYPE_USER)->errorOnConsoleLog($this->getMessage($blockStorage));
        } catch (SynctreeException|SynctreeInnerException $ex) {
            throw $ex;
        } catch (Throwable $ex) {
            $this->storage->getLogger()->exception($ex, self::TYPE.':'.self::ACTION);
            throw (new RuntimeException('Log-Error'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
        }
    }

    /**
     * @param array $blockStorage
     * @return string
     * @throws ISynctreeException
     */
    private function getMessage(array &$blockStorage): string
    {
        try {
            $message = $this->message->do($blockStorage);
            return (string) $message;
        } catch (Exception $ex) {
            try {
                return json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            } catch (Exception $ex) {
                throw (new RuntimeException('Log-Error: Invalid message'))->setExceptionKey(self::TYPE, self::ACTION)->setExtraData($this->extra->getData());
            }
        }
    }
}