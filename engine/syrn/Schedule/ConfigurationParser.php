<?php
namespace Ntuple\Synctree\Syrn\Schedule;

use Crunz\Configuration\ConfigFileNotExistsException;
use Crunz\Configuration\ConfigFileNotReadableException;
use Crunz\Configuration\ConfigurationParserInterface;
use Crunz\Configuration\FileParser;
use Crunz\Exception\CrunzException;
use Crunz\Filesystem\FilesystemInterface;
use Crunz\Logger\ConsoleLoggerInterface;
use Crunz\Path\Path;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationParser implements ConfigurationParserInterface
{
    private $configurationDefinition;
    private $definitionProcessor;
    private $consoleLogger;
    private $fileParser;
    private $filesystem;

    /**
     * ConfigurationParser constructor.
     * @param ConfigurationInterface $configurationDefinition
     * @param Processor $definitionProcessor
     * @param FileParser $fileParser
     * @param ConsoleLoggerInterface $consoleLogger
     * @param FilesystemInterface $filesystem
     */
    public function __construct(ConfigurationInterface $configurationDefinition, Processor $definitionProcessor, FileParser $fileParser, ConsoleLoggerInterface $consoleLogger, FilesystemInterface $filesystem)
    {
        $this->consoleLogger = $consoleLogger;
        $this->configurationDefinition = $configurationDefinition;
        $this->definitionProcessor = $definitionProcessor;
        $this->fileParser = $fileParser;
        $this->filesystem = $filesystem;
    }

    /**
     * @return array
     * @throws CrunzException
     */
    public function parseConfig(): array
    {
        $parsedConfig = [];
        $configFileParsed = false;
        $configFile = '';

        try {
            $configFile = $this->configFilePath();
            $parsedConfig = $this->fileParser->parse($configFile);

            $configFileParsed = true;
        } catch (ConfigFileNotExistsException $exception) {
            $this->consoleLogger->debug("Config file not found, exception message: '<error>{$exception->getMessage()}</error>'.");
        } catch (ConfigFileNotReadableException $exception) {
            $this->consoleLogger->debug("Config file is not readable, exception message: '<error>{$exception->getMessage()}</error>'.");
        }

        if (false === $configFileParsed) {
            $this->consoleLogger->verbose('Unable to find/parse config file, fallback to default values.');
        } else {
            $this->consoleLogger->verbose("Using config file <info>{$configFile}</info>.");
        }

        return $this->definitionProcessor->processConfiguration($this->configurationDefinition, $parsedConfig);
    }

    /**
     * @return string
     * @throws ConfigFileNotExistsException
     * @throws CrunzException
     */
    private function configFilePath(): string
    {
        $paths = [
            BASE_DIR . '/schedule.yml',
            __DIR__ . '/schedule.yml'
        ];

        if (\defined('SCHEDULE_CONFIG')) {
            $paths = array_merge([SCHEDULE_CONFIG], $paths);
        }

        $configPath = '';
        foreach ($paths as $path) {
            $configPath = Path::create([$path])->toString();
            $configExists = $this->filesystem->fileExists($configPath);

            if ($configExists) {
                return $configPath;
            }
        }

        throw new ConfigFileNotExistsException(\sprintf('Unable to find config file "%s".', $configPath));
    }
}
