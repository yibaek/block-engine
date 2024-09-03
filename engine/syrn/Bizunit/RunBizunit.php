<?php
namespace Ntuple\Synctree\Syrn\Bizunit;

use DateTime;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Ntuple\Synctree\Syrn\Command;
use Ntuple\Synctree\Log\LogMessage;
use Ntuple\Synctree\Models\Rdb\RDbManager;
use Ntuple\Synctree\Protocol\Http\HttpExecutor;
use Ntuple\Synctree\Protocol\Http\HttpHandler;
use Ntuple\Synctree\Syrn\Log\CreateLogger;
use Ntuple\Synctree\Util\CommonUtil;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class RunBizunit extends Command
{
    private $rdb;
    private $logger;
    private $runMode;
    private $processId;

    protected function configure(): void
    {
        $this
            ->setName('bizunit:run')
            ->setDescription('Executes a bizunit as a process.')
            ->setDefinition(
                [
                    new InputArgument(
                        'scheduleId',
                        InputArgument::REQUIRED,
                        'Sets the value of a schedule id.'
                    ),
                    new InputArgument(
                        'env',
                        InputArgument::REQUIRED,
                        'Sets the value of an environment variable.'
                    ),
                    new InputArgument(
                        'processId',
                        InputArgument::OPTIONAL,
                        'Sets the value of a schedule process id.'
                    )
                ]
            )
            ->addOption(
                'mode',
                'm',
                InputOption::VALUE_REQUIRED,
                'Sets the option of a mode.',
                'default'
            )
            ->addOption(
                'sleep',
                's',
                InputOption::VALUE_REQUIRED,
                'Sets the option of a sleep times.',
                5
            )
            ->setHelp('This command executes a bizunit as a separate process.')
            ->setHidden(true);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Throwable
     * @throws GuzzleException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startTime = time();

        try {
            $this->options = $input->getOptions();
            $this->arguments = $input->getArguments();
            $this->runMode = $input->getOption('mode');
            $this->processId = $this->makeProcessId();

            if (!defined('APP_ENV')) {
                define('APP_ENV', $this->arguments['env']);
            }

            // logging
            $this->consoleLog($output, '<info>---------------- Start Run Bizunit ----------------</info>');

            $this->logger = new LogMessage(new CreateLogger());
            $this->rdb = (new RDbManager($this->logger))->getRdbMgr('studio');

            // query execute
            $batchInfo = $this->rdb->getHandler()->executeGetBatchInfo($this->arguments['scheduleId']);

            // run bizunit each mode
            switch ($this->runMode) {
                case 'schedule':
                    return $this->runBizunitBySchedule($output, $batchInfo);

                case 'replay':
                    $this->exectueBatch($output, $batchInfo, (int)$batchInfo['redo_count'],-1);
                    return 0;

                case 'immediately':
                    $this->exectueBatch($output, $batchInfo);
                    return 0;

                default:
                    $this->exectueBatch($output, $batchInfo);
            }

            return 0;
        } catch (Throwable $ex) {
            $this->loggingException($output, $ex);
            return 0;
        } finally {
            $endTime = time();
            $this->consoleLog($output, '<info>Start:'.date('Y-m-d H:i:s', $startTime).', End:'.date('Y-m-d H:i:s', $endTime).', Duration of total time:'.(int)($endTime-$startTime).'sec</info>');
            $this->consoleLog($output, '<info>---------------- End Run Bizunit ----------------</info>');
        }
    }

    /**
     * @param OutputInterface $output
     * @param array $batchInfo
     * @param int $redoCount
     * @param int $execCount
     * @return bool
     * @throws GuzzleException
     * @throws \JsonException
     */
    private function exectueBatch(OutputInterface $output, array $batchInfo, int $redoCount = 0, int $execCount = 0): bool
    {
        $startTime = time();
        $batchHistoryID = null;

        try {
            $this->consoleLog($output, '<info>---------------- Start Batch ----------------</info>');

            // query execute
            $batchHistoryID = $this->rdb->getHandler()->executeAddBatchHistory(
                $this->processId,
                $batchInfo['batch_id'],
                $this->arguments['scheduleId'],
                $batchInfo['bizunit_sno'],
                $batchInfo['revision_sno'],
                $this->runMode,
                $redoCount,
                $execCount);

            // http execute
            $executor = (new HttpExecutor($this->logger, (new HttpHandler($this->logger))->enableLogging()->getHandlerStack()))
                ->setEndPoint($this->getEndpoint())
                ->setMethod(HttpExecutor::HTTP_METHOD_POST)
                ->setHeaders($this->makeHeader($batchInfo))
                ->setBodys($this->makeBody($batchInfo))
                ->isConvertJson(true);

            [$resStatusCode, $resHeader, $resBody] = $executor->execute();

            // check success
            $isBatchSuccess = $this->isBatchSuccess((int)$resStatusCode);

            // logging
            $this->consoleLog($output, '<info>Code:'.$resStatusCode.'</info>');
            $this->consoleLog($output, '<info>Header:'. json_encode($resHeader, JSON_THROW_ON_ERROR) .'</info>');
            $this->consoleLog($output, '<info>Body:'. json_encode($resBody, JSON_THROW_ON_ERROR) .'</info>');

            // query execute
            $this->rdb->getHandler()->executeUpdateBatchHistory(
                $batchHistoryID,
                $this->makeBatchSuccess($isBatchSuccess),
                $this->makeBatchSuccessMessage($output, (int)$resStatusCode, $resHeader, $resBody));

            return $isBatchSuccess;
        } catch (Throwable $ex) {
            $this->loggingException($output, $ex);

            if ($batchHistoryID) {
                // query execute
                $this->rdb->getHandler()->executeUpdateBatchHistory(
                    $batchHistoryID,
                    $this->makeBatchSuccess(false),
                    $this->makeBatchSuccessMessage($output, 500, [], 'An Exception Occurred.'));
            }

            return false;
        } finally {
            $endTime = time();
            $this->consoleLog($output, '<info>Start:'.date('Y-m-d H:i:s', $startTime).', End:'.date('Y-m-d H:i:s', $endTime).', Duration of time:'.(int)($endTime-$startTime).'sec</info>');
            $this->consoleLog($output, '<info>-------------- End Batch ----------------</info>');
        }
    }

    /**
     * @return string
     */
    private function getEndpoint(): string
    {
        // load credential
        $credential = CommonUtil::getCredentialConfig('end-point');
        return $credential['synctree-tool'];
    }

    /**
     * @param array $batchInfo
     * @return array
     */
    private function makeHeader(array $batchInfo): array
    {
        // get bizunit info
        $bizunitInfo = $this->getBizunit($batchInfo);

        // get header argument
        $argument = $this->getArgumentHeader($batchInfo);

        return array_merge([
            'Content-Type' => 'application/json'
        ], $argument, $bizunitInfo);
    }

    /**
     * @param array $batchInfo
     * @return mixed
     */
    private function makeBody(array $batchInfo)
    {
        return $this->getArgumentBody($batchInfo);
    }

    /**
     * @param array $batchInfo
     * @return array
     */
    private function getBizunit(array $batchInfo): array
    {
        $bizunitInfo = $this->rdb->getHandler()->executeGetBatchBizunitInfo((int)$batchInfo['bizunit_sno'], (int)$batchInfo['revision_sno']);

        return [
            'X-Synctree-Plan-ID' => $bizunitInfo['bizunit_id'],
            'X-Synctree-Plan-Environment' => $bizunitInfo['revision_environment'],
            'X-Synctree-Bizunit-Version' => $bizunitInfo['bizunit_version'],
            'X-Synctree-Revision-ID' => $bizunitInfo['revision_id']
        ];
    }

    /**
     * @param array $batchInfo
     * @return array|mixed|string|string[]
     */
    private function getArgumentHeader(array $batchInfo): array
    {
        $argument = $batchInfo['argument_header'];
        try {
            if (empty($argument)) {
                return [];
            }

            return json_decode($argument, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $ex) {
            return $argument;
        }
    }

    /**
     * @param array $batchInfo
     * @return mixed|string|string[]|null
     */
    private function getArgumentBody(array $batchInfo)
    {
        $argument = $batchInfo['argument_body'];
        try {
            if (empty($argument)) {
                return [];
            }

            return json_decode($argument, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $ex) {
            return $argument;
        }
    }

    /**
     * @param int $statusCode
     * @return bool
     */
    private function isBatchSuccess(int $statusCode): bool
    {
        return $statusCode === 200;
    }

    /**
     * @param bool $isSuccess
     * @return string
     */
    private function makeBatchSuccess(bool $isSuccess): string
    {
        return $isSuccess ?'Y' :'N';
    }

    /**
     * @param OutputInterface $output
     * @param int $statuscode
     * @param array $header
     * @param $body
     * @return string
     * @throws \JsonException
     */
    private function makeBatchSuccessMessage(OutputInterface $output, int $statuscode, array $header, $body): string
    {
        $resData = json_encode([
            'Code' => $statuscode,
            'Response' => $body
        ], JSON_THROW_ON_ERROR);

        // logging
        $this->consoleLog($output, '<info>BatchMessage:'.$resData.'</info>');

        return $resData;
    }

    /**
     * @return string
     * @throws Exception
     */
    private function makeProcessId(): string
    {
        if (isset($this->arguments['processId']) && !empty($this->arguments['processId'])) {
            return substr($this->arguments['processId'], 0,40);
        }

        return hash('md5', (new DateTime())->format('Uu').random_bytes(32));
    }

    /**
     * @param OutputInterface $output
     * @param array $batchInfo
     * @return int
     * @throws GuzzleException
     * @throws \JsonException
     */
    private function runBizunitBySchedule(OutputInterface $output, array $batchInfo): int
    {
        $execCnt = 0;
        $redoCnt = (int)$batchInfo['redo_count'];

        do {
            $isSuccess = $this->exectueBatch($output, $batchInfo, (int)$batchInfo['redo_count'], $execCnt);
            if ($isSuccess === true) {
                break;
            }

            --$redoCnt;
            $execCnt++;

            if ($redoCnt >= 0) {
                $sleep = $this->getSleepTime();
                $this->consoleLog($output, '<info>Sleep : '.$sleep.'sec</info>');
                sleep($sleep);
            }
        } while($redoCnt >= 0);

        return 0;
    }

    /**
     * @return int
     */
    private function getSleepTime(): int
    {
        $sleep = (int)$this->options['sleep'];
        $sleep = $sleep > 0 ?$sleep :5;

        return $sleep;
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
