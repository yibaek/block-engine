<?php
namespace controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Container\ContainerInterface;
use Throwable;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Ntuple\Synctree\Syrn\Application;

use libraries\log\LogMessage;

class SyrnCommand
{
    private $ci;
    private $application;

    /**
     * SyrnCommand constructor.
     * @param ContainerInterface $ci
     */
    public function __construct(ContainerInterface $ci)
    {
        $this->ci = $ci;
        $this->application = new Application('Synctree Command Line Interface On Controller', '1.0');
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws Throwable
     */
    public function runBatch(Request $request, Response $response): Response
    {
        try {
            $params = ($request->getAttribute('params'))->getParam();

            // make command
            $command = [
                (new PhpExecutableFinder())->find(),
                (new ExecutableFinder())->find('syrn', '/usr/local/bin/syrn'),
                'bizunit:run',
                $params['schedule_id'],
                $params['env'] ?? APP_ENV,
                $params['process_id'],
                '--mode='.$params['mode'] ?? 'default'
            ];

            // run process
            $process = Process::fromShellCommandline(implode(' ', $command));
            $process->setTimeout($params['timeout'] ?? 5);
            $process->run();

            LogMessage::debug('commandLine:'. $process->getCommandLine());

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            LogMessage::debug('output:'. $process->getOutput());
            return $response;
        } catch (ProcessTimedOutException $ex) {
            LogMessage::exception($ex);
            return $response;
        } catch (Throwable $ex) {
            LogMessage::exception($ex);
            throw $ex;
        }
    }
}