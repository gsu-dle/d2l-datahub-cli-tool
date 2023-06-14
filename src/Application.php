<?php

declare(strict_types=1);

namespace D2L\DataHub;

use D2L\DataHub\Repository\ContainerDefinitionRepositoryInterface;
use DI\Container;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Application
{
    private Container $container;


    /**
     * @param ContainerDefinitionRepositoryInterface $containerDefRepo
     */
    public function __construct(ContainerDefinitionRepositoryInterface $containerDefRepo)
    {
        $this->container = (new \DI\ContainerBuilder())
            ->useAttributes(true)
            ->addDefinitions($containerDefRepo)
            ->build();
    }


    /**
     * @return void
     */
    public function run(): void
    {
        $exitValue = $this->container->call($this);
        exit(is_int($exitValue) ? $exitValue : 1);
    }


    /**
     * @param SymfonyApplication $app
     * @param InputInterface $in
     * @param OutputInterface $out
     * @param LoggerInterface $logger
     * @return int
     */
    public function __invoke(
        SymfonyApplication $app,
        InputInterface $in,
        OutputInterface $out,
        LoggerInterface $logger
    ): int {
        /** @var string[] $cmd */
        $cmd = $_SERVER['argv'] ?? [];
        $cmd = array_shift($cmd) . " \"" . implode("\" \"", $cmd) . "\"";

        $logger->info("Started: '{$cmd}'");

        try {
            $exitCode = $app->run($in, $out);
        } catch (\Throwable $t) {
            if ($out instanceof ConsoleOutputInterface) {
                $app->renderThrowable($t, $out->getErrorOutput());
            } else {
                $app->renderThrowable($t, $out);
            }

            $logger->error($t->getMessage(), [$t]);
            $logger->debug($t->getTraceAsString());

            $exitCode = $t->getCode();

            if (is_numeric($exitCode)) {
                $exitCode = (int) $exitCode;
                if ($exitCode <= 0) {
                    $exitCode = 1;
                }
            } else {
                $exitCode = 1;
            }
        }

        if ($exitCode > 255) {
            $exitCode = 255;
        }

        $logger->info("Finished: '{$cmd}', code => {$exitCode}");

        return $exitCode;
    }
}
