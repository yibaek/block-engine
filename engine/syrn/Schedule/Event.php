<?php
namespace Ntuple\Synctree\Syrn\Schedule;

use DateTime;
use Exception;

class Event extends \Crunz\Event
{
    private $eventType;
    private $environment;
    private $scheduleId;

    /**
     * @return string
     * @throws Exception
     */
    public function buildCommand(): string
    {
        if ($this->eventType === 'bizunit') {
            return $this->serializeBizunit($this->scheduleId, $this->environment);
        }

        $command = '';
        if ($this->cwd) {
            if ($this->user) {
                $command .= $this->sudo($this->user);
            }

            // Support changing drives in Windows
            $cdParameter = $this->isWindows() ? '/d ' : '';
            $andSign = $this->isWindows() ? ' &' : ';';

            $command .= "cd {$cdParameter}{$this->cwd}{$andSign} ";
        }

        if ($this->user) {
            $command .= $this->sudo($this->user);
        }

        $command .= \is_string($this->command) ? $this->command : $this->serializeClosure($this->command);

        return \trim($command, '& ');
    }

    /**
     * @param string|null $type
     * @return $this
     */
    public function setEventType(string $type = null): self
    {
        $this->eventType = $type;
        return $this;
    }

    /**
     * @param string $env
     * @return $this
     */
    public function setEnvironment(string $env): self
    {
        $this->environment = $env;
        return $this;
    }

    /**
     * @param string|null $key
     * @return $this
     */
    public function setScheduleId(string $key = null): self
    {
        $this->scheduleId = $key;
        return $this;
    }

    /**
     * @param string $scheduleId
     * @param string $environment
     * @return string
     * @throws Exception
     */
    private function serializeBizunit(string $scheduleId, string $environment): string
    {
        $crunzRoot = SYRN_BIN;
        $options = implode(' ', ['--mode=schedule']);

        return PHP_BINARY . " {$crunzRoot} bizunit:run {$scheduleId} {$environment} {$options}";
    }

    /**
     * @return bool
     */
    private function isWindows(): bool
    {
        $osCode = \mb_substr(
            PHP_OS,
            0,
            3
        );

        return 'WIN' === $osCode;
    }
}