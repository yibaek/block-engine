<?php
namespace Ntuple\Synctree\Syrn\Schedule;

use Crunz\EventRunner;
use Crunz\Exception\EmptyTimezoneException;
use Crunz\Task\TaskException;
use Crunz\Task\Timezone;
use DateTime;
use Exception;
use Ntuple\Synctree\Syrn\Command;
use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Models\Rdb\RDbManager;
use Ntuple\Synctree\Models\Redis\RedisMgr;
use Ntuple\Synctree\Syrn\Constrant\CommonConst;
use Ntuple\Synctree\Syrn\Log\CreateLogger;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Component\Lock\Store\RetryTillSaveStore;
use Throwable;

class ScheduleRunBizunit extends Command
{
    private const EVENT_TYPE = 'bizunit';

    private $rdb;
    private $logger;
    private $eventRunner;
    private $taskTimezone;
    private $blockingStore;
    private $processId;

    /**
     * ScheduleRunBizunit constructor.
     * @param EventRunner $eventRunner
     * @param Timezone $taskTimezone
     */
    public function __construct(EventRunner $eventRunner, Timezone $taskTimezone)
    {
        $this->eventRunner = $eventRunner;
        $this->taskTimezone = $taskTimezone;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('schedule:run:bizunit')
            ->setDescription('Starts the event runner.')
            ->setDefinition(
                [
                    new InputArgument(
                        'env',
                        InputArgument::REQUIRED,
                        'Sets the value of an environment variable.'
                    )
                ]
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Run all tasks regardless of configured run time.'
            )
            ->setHelp('This command starts the Crunz event runner.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws EmptyTimezoneException
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startTime = time();

        try {
            $this->options = $input->getOptions();
            $this->arguments = $input->getArguments();
            $this->processId = $this->makeProcessId();

            // define the current environment
            define('APP_ENV', $this->arguments['env']);

            // logging
            $this->consoleLog($output, '<info>---------------- Start Schedule Run Bizunit ----------------</info>');

            $this->logger = new LogMessage(new CreateLogger());
            $this->rdb = (new RDbManager($this->logger))->getRdbMgr('studio');

            // get schedule list
            $schedules = $this->getSchedules();

            $tasksTimezone = $this->taskTimezone->timezoneForComparisons();
            $forceRun = \filter_var($this->options['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $schedules = \array_map(
                static function (Schedule $schedule) use ($tasksTimezone, $forceRun) {
                    if (false === $forceRun) {
                        // We keep the events which are due and dismiss the rest.
                        $schedule->events($schedule->dueEvents($tasksTimezone));
                    }
                    return $schedule;
                },
                $schedules
            );

            $schedules = \array_filter(
                $schedules,
                static function (Schedule $schedule) {
                    return \count($schedule->events());
                }
            );

            if (!\count($schedules)) {
                $this->consoleLog($output, '<info>No event is due!</info>');
                return 0;
            }

            // Running the events
            $this->eventRunner->handle($output, $schedules);

            return 0;
        } catch (Throwable $ex) {
            $this->loggingException($output, $ex);
            return 0;
        } finally {
            $endTime = time();
            $this->consoleLog($output, '<info>Start:'.date('Y-m-d H:i:s', $startTime).', End:'.date('Y-m-d H:i:s', $endTime).', Duration of total time:'.(int)($endTime-$startTime).'sec</info>');
            $this->consoleLog($output, '<info>---------------- End Schedule Run Bizunit ----------------</info>');
        }
    }

    /**
     * @return BlockingStoreInterface
     * @throws Throwable
     */
    private function getBlockingStore(): BlockingStoreInterface
    {
        if (null !== $this->blockingStore) {
            return $this->blockingStore;
        }

        $key = 'schedule_prevent_overlapping';
        $store = new RedisStore((new RedisMgr($this->logger))->makeConnection($key, CommonConst::REDIS_SCHEDULE));
        $this->blockingStore = new RetryTillSaveStore($store);

        return $this->blockingStore;
    }

    /**
     * @return array
     * @throws Throwable
     */
    private function getSchedules(): array
    {
        $batchList = $this->rdb->getHandler()->executeGetBatchList();

        $schedules = [];
        foreach ($batchList as $batch) {
            $schedules[] = $this->makeSchedule($batch);
        }

        return $schedules;
    }

    /**
     * @param array $batchInfo
     * @return Schedule
     * @throws Throwable
     * @throws TaskException
     */
    private function makeSchedule(array $batchInfo): Schedule
    {
        // create schedule with bizunit
        $schedule = (new Schedule())
            ->setScheduleId($batchInfo['batch_match_id'])
            ->setEventType(self::EVENT_TYPE)
            ->setEnvionment($this->arguments['env']);

        // create event
        $event = $schedule->run();

        // set crontab type
        $event->cron($batchInfo['batch_content']);

        // set prevent overlapping
        if (isset($batchInfo['batch_prevent_overlapping']) && $batchInfo['batch_prevent_overlapping'] === true) {
            $event->preventOverlapping($this->getBlockingStore());
        }

        return $schedule;
    }

    /**
     * @return string
     * @throws Exception
     */
    private function makeProcessId(): string
    {
        return hash('md5', (new DateTime())->format('Uu').random_bytes(32));
    }

    /**
     * @param OutputInterface $output
     * @param Throwable $ex
     */
    private function loggingException(OutputInterface $output, Throwable $ex): void
    {
        $this->logger->exception($ex);
        $this->consoleLog($output, '<error>Exception:'.$ex->getMessage().'</error>');
    }

    /**
     * @param OutputInterface $output
     * @param string $message
     */
    private function consoleLog(OutputInterface $output, string $message): void
    {
        $output->writeln('['.$this->processId.'] '.$message);
    }
}
