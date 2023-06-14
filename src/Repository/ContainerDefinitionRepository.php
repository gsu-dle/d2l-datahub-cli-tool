<?php

declare(strict_types=1);

namespace D2L\DataHub\Repository;

use D2L\DataHub\Console;
use D2L\DataHub\ETL\ExtractDownloader;
use D2L\DataHub\ETL\ExtractDownloaderInterface;
use D2L\DataHub\ETL\ExtractProcessor;
use D2L\DataHub\ETL\ExtractProcessorInterface;
use D2L\DataHub\ETL\ProcessFileLoader;
use D2L\DataHub\ETL\ProcessFileLoaderInterface;
use D2L\DataHub\ETL\SchemaDownloader;
use D2L\DataHub\ETL\SchemaDownloaderInterface;
use D2L\DataHub\ETL\SQLTableGenerator;
use D2L\DataHub\ETL\SQLTableGeneratorInterface;
use D2L\DataHub\Repository\DatasetExtractRepository;
use D2L\DataHub\Repository\DatasetExtractRepositoryInterface;
use D2L\DataHub\Repository\DatasetRepository;
use D2L\DataHub\Repository\DatasetRepositoryInterface;
use D2L\DataHub\Repository\ValenceAPIRepository;
use D2L\DataHub\Repository\ValenceAPIRepositoryInterface;
use D2L\DataHub\Util\StringUtils;
use DI\Definition\Definition;
use DI\Definition\Helper\AutowireDefinitionHelper;
use DI\Definition\Helper\FactoryDefinitionHelper;
use DI\Definition\Source\AttributeBasedAutowiring;
use DI\Definition\Source\DefinitionArray;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use mysqli;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\SemaphoreStore;

use function DI\autowire;
use function DI\factory;
use function DI\get;

class ContainerDefinitionRepository extends DefinitionArray implements ContainerDefinitionRepositoryInterface
{
    public function __construct(string $envPath)
    {
        (new Dotenv())->loadEnv($envPath);

        parent::__construct(
            $this->getContainerDefinitions(),
            new AttributeBasedAutowiring()
        );
    }


    /**
     * @return array<string,mixed>
     */
    public function getContainerDefinitions(): array
    {
        return $this->getAliases()
            + $this->getAutowireHints()
            + $this->getFactories();
    }


    /**
     * @return array<string,Definition>
     */
    protected function getAliases(): array
    {
        return
            // App
            [
                ExtractDownloaderInterface::class        => get(ExtractDownloader::class),
                ExtractProcessorInterface::class         => get(ExtractProcessor::class),
                ProcessFileLoaderInterface::class        => get(ProcessFileLoader::class),
                SchemaDownloaderInterface::class         => get(SchemaDownloader::class),
                SQLTableGeneratorInterface::class        => get(SQLTableGenerator::class),
                DatasetExtractRepositoryInterface::class => get(DatasetExtractRepository::class),
                DatasetRepositoryInterface::class        => get(DatasetRepository::class),
                ValenceAPIRepositoryInterface::class     => get(ValenceAPIRepository::class)
            ]
            // External lib
            + [
                CacheItemPoolInterface::class            => get(FilesystemAdapter::class),
                ClientInterface::class                   => get(Client::class),
                RequestFactoryInterface::class           => get(HttpFactory::class),
                ServerRequestFactoryInterface::class     => get(HttpFactory::class),
                StreamFactoryInterface::class            => get(HttpFactory::class),
                UriFactoryInterface::class               => get(HttpFactory::class),
                InputInterface::class                    => get(ArgvInput::class),
                OutputInterface::class                   => get(ConsoleOutput::class),
                PersistingStoreInterface::class          => get(SemaphoreStore::class),
                CommandLoaderInterface::class            => get(ContainerCommandLoader::class),
            ];
    }


    /**
     * @return array<string,AutowireDefinitionHelper>
     */
    protected function getAutowireHints(): array
    {
        global $_ENV;

        return
            [
                ExtractDownloader::class => autowire()
                    ->constructorParameter('extractsDir', $_ENV['EXTRACTS_DIR']),
                ExtractProcessor::class => autowire()
                    ->constructorParameter('extractsDir', $_ENV['EXTRACTS_DIR'])
                    ->constructorParameter('processFilesDir', $_ENV['PROCESS_FILES_DIR'])
                    ->constructorParameter('chunkSize', intval($_ENV['PROCESS_FILE_CHUNK_SIZE'] ?? 50000)),
                SchemaDownloader::class => autowire()
                    ->constructorParameter('schemaURL', $_ENV['SCHEMA_URL'])
                    ->constructorParameter('pageList', SchemaPages::PAGES),
                ValenceAPIRepository::class => autowire()
                    ->constructorParameter('apiURL', $_ENV['API_URL'])
                    ->constructorParameter('authURL', $_ENV['AUTH_URL']),
            ]
            + [
                \mysqli::class => autowire()
                    ->constructor(
                        hostname: $_ENV['MYSQL_HOST'] ?? '',
                        username: $_ENV['MYSQL_USER'] ?? '',
                        password: $_ENV['MYSQL_PASS'] ?? '',
                        database: $_ENV['MYSQL_DB'] ?? '',
                        port: intval($_ENV['MYSQL_PORT'] ?? 3306)
                    ),
                ContainerCommandLoader::class => autowire()
                    ->constructorParameter('commandMap', $this->getCommands($_ENV['APP_NAMESPACE'], $_ENV['SRC_DIR'])),
                OutputFormatter::class => autowire()
                    ->constructor(false, ['debug' => new OutputFormatterStyle('gray')]),
                ConsoleOutput::class => autowire()
                    ->constructorParameter('formatter', get(OutputFormatter::class)),
                Client::class => autowire()
                    ->constructor(['stream' => true]),
            ];
    }


    /**
     * @return array<string,FactoryDefinitionHelper>
     */
    protected function getFactories(): array
    {
        global $_ENV;

        return [
            LoggerInterface::class => $this->factory(
                function (
                    ContainerInterface $container,
                    InputInterface $in,
                    OutputInterface $out
                ) {
                    $startTime = microtime(true);

                    if (true === $in->hasParameterOption(['--disable-logger'], true)) {
                        return $container->get(NullLogger::class);
                    }

                    // $stream = ($out instanceof ConsoleOutput) ? $out->getStream() : 'php://stdout';
                    $stream = $_ENV['LOG_DIR'] . '/' . $_ENV['LOG_NAME'] . '.' . date('Y_m_d') . '.log';
                    $logLevel = ($out->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE)
                        ? Logger::DEBUG
                        : Logger::INFO;

                    $handler = (new StreamHandler($stream, $logLevel))
                        ->setFormatter(
                            new LineFormatter(
                                "[%datetime%][%extra.elapsed%][%level_name%]: %message%\n",
                            )
                        );

                    return (new Logger('console'))
                        ->pushHandler($handler)
                        ->pushProcessor(function (LogRecord $entry) use ($startTime) {
                            $entry->extra['elapsed'] = StringUtils::formatElapsedTime($startTime);
                            return $entry;
                        });
                }
            ),
            FilesystemAdapter::class => $this->factory(
                function (string $cacheDir): FilesystemAdapter {
                    $cache = new FilesystemAdapter(
                        namespace: '',
                        defaultLifetime: 0,
                        directory: $cacheDir,
                        marshaller: null
                    );
                    $cache->prune();
                    return $cache;
                },
                [
                    'cacheDir' => $_ENV['CACHE_DIR']
                ]
            ),
            Application::class => $this->factory(
                function (
                    CommandLoaderInterface $commandLoader,
                    string $appName,
                    string $appVersion
                ): Application {
                    $application = new Application($appName, $appVersion);
                    $application->setCommandLoader($commandLoader);
                    $application->setAutoExit(false);
                    $application->setCatchExceptions(false);
                    return $application;
                },
                [
                    'appName' => $_ENV['APP_NAME'],
                    'appVersion' => $_ENV['APP_VERSION']
                ]
            )
        ];
    }


    /**
     * @param callable $factory
     * @param array<string,mixed> $parameters
     * @return FactoryDefinitionHelper
     */
    protected function factory(
        callable $factory,
        array $parameters = []
    ): FactoryDefinitionHelper {
        $_factory = factory($factory);
        foreach ($parameters as $name => $value) {
            $_factory = $_factory->parameter($name, $value);
        }
        return $_factory;
    }



    /**
     * @param string $namespace
     * @param string $srcDir
     * @return array<string,class-string<object>>
     */
    protected function getCommands(
        string $namespace,
        string $srcDir
    ): array {
        $commands = [];
        $searchList = [];
        array_push($searchList, $srcDir);

        while (count($searchList) > 0) {
            $dirPath = array_shift($searchList);
            $dirFiles = scandir($dirPath);
            if (!is_array($dirFiles)) {
                continue;
            }
            foreach ($dirFiles as $dirFile) {
                if ($dirFile === '..' || $dirFile === '.') {
                    continue;
                }
                $filePath = "{$dirPath}/{$dirFile}";
                if (is_dir($filePath)) {
                    array_push($searchList, $filePath);
                } elseif (str_ends_with($filePath, '.php')) {
                    /** @var class-string<object> $className */
                    $className = $namespace . str_replace('/', '\\', substr($filePath, strlen($srcDir), -4));
                    $refClass = new \ReflectionClass($className);
                    $classAttrs = $refClass->getAttributes(
                        AsCommand::class,
                        \ReflectionAttribute::IS_INSTANCEOF
                    );
                    if (count($classAttrs) > 0) {
                        $inst = $classAttrs[0]->newInstance();
                        $commands[$inst->name] = $className;
                    }
                }
            }
        }

        return $commands;
    }
}
