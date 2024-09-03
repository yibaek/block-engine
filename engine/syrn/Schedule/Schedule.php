<?php
namespace Ntuple\Synctree\Syrn\Schedule;

use Closure;

class Schedule extends \Crunz\Schedule
{
    private $eventType;
    private $environment;
    private $scheduleId;

    /**
     * @param Closure|string|null $command
     * @param array $parameters
     * @return Event
     */
    public function run($command = null, array $parameters = []): Event
    {
        if (\is_string($command) && \count($parameters)) {
            $command .= ' ' . $this->compileParameters($parameters);
        }

        $this->events[] = $event = (new Event($this->id(), $command))->setEventType($this->eventType)->setEnvironment($this->environment)->setScheduleId($this->scheduleId);
        return $event;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setEventType(string $type): self
    {
        $this->eventType = $type;
        return $this;
    }

    /**
     * @param string $env
     * @return $this
     */
    public function setEnvionment(string $env): self
    {
        $this->environment = $env;
        return $this;
    }

    /**
     * @param string $key
     * @return $this
     */
    public function setScheduleId(string $key): self
    {
        $this->scheduleId = $key;
        return $this;
    }
}