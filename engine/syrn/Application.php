<?php
namespace Ntuple\Synctree\Syrn;

use Crunz\Exception\CrunzException;
use Crunz\Path\Path;
use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class Application extends SymfonyApplication
{
    private const COMMANDS = [
        \Ntuple\Synctree\Syrn\Schedule\ScheduleRunBizunit::class,
        \Ntuple\Synctree\Syrn\Bizunit\RunBizunit::class
    ];

    private $container;

    /**
     * Application constructor.
     * @param string $appName
     * @param string $appVersion
     * @throws CrunzException
     */
    public function __construct(string $appName, string $appVersion)
    {
        parent::__construct($appName, $appVersion);

        $this->initializeContainer();
        $this->addCommandMap();
    }

    /**
     * @param InputInterface|null $input
     * @param OutputInterface|null $output
     * @return int
     * @throws Exception
     */
    public function run(InputInterface $input = null, OutputInterface $output = null): int
    {
        if (null === $output) {
            /** @var OutputInterface $outputObject */
            $outputObject = $this->container->get(OutputInterface::class);
            $output = $outputObject;
        }

        if (null === $input) {
            /** @var InputInterface $inputObject */
            $inputObject = $this->container->get(InputInterface::class);
            $input = $inputObject;
        }

        return parent::run($input, $output);
    }

    /**
     * @throws CrunzException
     */
    private function initializeContainer(): void
    {
        $containerBuilder = $this->buildContainer();
        $containerBuilder->compile();

        $this->container = $containerBuilder;
    }

    /**
     * @return ContainerBuilder
     * @throws CrunzException
     * @throws Exception
     */
    private function buildContainer(): ContainerBuilder
    {
        $containerBuilder = new ContainerBuilder();
        $configDir = Path::create(
            [
                __DIR__
            ]
        );

        $phpLoader = new PhpFileLoader($containerBuilder, new FileLocator($configDir->toString()));
        $phpLoader->load('containers.php');

        return $containerBuilder;
    }

    private function addCommandMap(): void
    {
        foreach (self::COMMANDS as $commandClass) {
            /** @var Command $command */
            $command = $this->container->get($commandClass);
            $this->add($command);
        }
    }
}
