<?php
namespace Ntuple\Synctree\Util\Support\DateTime;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use RuntimeException;

class DateTimeSupport
{
    private $time;
    private $timezone;
    private $datetime;
    private $interval;

    /**
     * DateTimeSupport constructor.
     * @param string|null $time
     * @param string|null $timezone
     * @throws Exception
     */
    public function __construct(string $time = null, string $timezone = null)
    {
        $this->time = $time ?? 'now';
        $this->timezone = $timezone !== null ?new DateTimeZone($timezone) :null;
        $this->interval = null;

        // create datetime object
        $this->datetime = $this->createDateTime();
    }

    /**
     * @return int
     */
    public function getUnixTimestamp(): int
    {
        return $this->datetime->getTimestamp();
    }

    /**
     * @return int
     */
    public function getOffset(): int
    {
        return $this->datetime->getOffset();
    }

    /**
     * @param string $format
     * @return string
     */
    public function format(string $format): string
    {
        return $this->datetime->format($format);
    }

    /**
     * @param string $format
     * @return string
     */
    public function diffFormat(string $format): string
    {
        return $this->interval->format($format);
    }

    /**
     * @param mixed $interval
     * @param bool $isArrange
     * @return DateTimeSupport
     * @throws Exception
     */
    public function add($interval, bool $isArrange = false): DateTimeSupport
    {
        $this->datetime->add(new DateInterval($isArrange === true ?$this->arrangIntervalSpec($interval) :$interval));
        return $this;
    }

    /**
     * @param mixed $interval
     * @param bool $isArrange
     * @return DateTimeSupport
     * @throws Exception
     */
    public function sub($interval, bool $isArrange = false): DateTimeSupport
    {
        $this->datetime->sub(new DateInterval($isArrange === true ?$this->arrangIntervalSpec($interval) :$interval));
        return $this;
    }

    /**
     * @param DateTimeSupport $diff
     * @return DateTimeSupport
     */
    public function diff(DateTimeSupport $diff): DateTimeSupport
    {
        $this->interval = $this->datetime->diff($diff->datetime);
        return $this;
    }

    /**
     * @return DateTime
     * @throws Exception
     */
    private function createDateTime(): DateTime
    {
        return new DateTime($this->reCreateTime(), $this->timezone);
    }

    /**
     * @return string
     */
    private function reCreateTime(): string
    {
        try {
            if ('now' === $this->time) {
                return $this->time;
            }

            $time = '@' . $this->time;
            new DateTime($time);
            return $time;
        } catch(Exception $e) {
            return $this->time;
        }
    }

    /**
     * @param array $intervals
     * @return string
     */
    private function arrangIntervalSpec(array $intervals): string
    {
        // sort interval spec
        [$date, $time] = $this->sortIntervalSpec($intervals);

        // set each spec
        $addDateSpec = !empty($date) ?implode('', $date) :'';
        $addTimeSpec = !empty($time) ?'T'.implode('', $time) :'';

        return 'P'.$addDateSpec.$addTimeSpec;
    }

    /**
     * @param array $intervals
     * @return array|array[]
     */
    private function sortIntervalSpec(array $intervals): array
    {
        $date = [];
        $time = [];
        foreach ($intervals as $key=>$value) {
            switch ($key) {
                case 'DY':
                case 'DM':
                case 'DD':
                    $date[] = $value.substr($key, 1);
                    break;
                case 'TH':
                case 'TM':
                case 'TS':
                    $time[] = $value.substr($key, 1);
                    break;
                default:
                    throw new RuntimeException('invalid datetime interval spec['.$value.$key.']');
            }
        }

        return [$date, $time];
    }
}