<?php

declare(strict_types=1);

namespace D2L\DataHub\Command;

use D2L\DataHub\Repository\DatasetRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'schema:fetch',
    description: 'Fetch dataset schema'
)]
class SchemaFetchCommand extends Command
{
    /**
     * @param DatasetRepositoryInterface $datasetRepo
     */
    public function __construct(private DatasetRepositoryInterface $datasetRepo)
    {
        parent::__construct();
    }


    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'init',
                description: 'Clear cache and fetch schema from documentation'
            )
            ->addOption(
                name: 'output',
                mode: InputOption::VALUE_OPTIONAL,
                description: 'Output file',
                default: 'php://stdout'
            );
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        /** @var string $outputFile */
        $outputFile = $input->getOption('output');
        $init = $input->getOption('init') === true;

        if ($init) {
            $this->datasetRepo->setDatasets(null);
        }

        file_put_contents(
            $outputFile,
            json_encode(
                $this->datasetRepo->getDatasets(null, false),
                JSON_PRETTY_PRINT
            ) . "\n"
        );

        return Command::SUCCESS;
    }
}
