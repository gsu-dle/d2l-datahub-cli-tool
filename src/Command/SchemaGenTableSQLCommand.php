<?php

declare(strict_types=1);

namespace D2L\DataHub\Command;

use D2L\DataHub\ETL\SQLTableGeneratorInterface;
use D2L\DataHub\Repository\DatasetRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'schema:gen-table-sql',
    description: 'Generate MySQL table SQL'
)]
class SchemaGenTableSQLCommand extends Command
{
    /**
     * @param DatasetRepositoryInterface $datasetRepo
     * @param SQLTableGeneratorInterface $sqlTableGenerator
     */
    public function __construct(
        private DatasetRepositoryInterface $datasetRepo,
        private SQLTableGeneratorInterface $sqlTableGenerator
    ) {
        parent::__construct();
    }


    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'output',
                mode: InputOption::VALUE_OPTIONAL,
                description: 'Output file',
                default: 'php://stdout'
            )
            ->addArgument(
                'datasets',
                InputArgument::IS_ARRAY,
                'Specific dataset(s) to generate table schema for. If no datasets are listed, schema will be generated'
                    . ' for all available datasets.'
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
        $selected = $input->getArgument('datasets');
        if (!is_array($selected) || count($selected) < 1) {
            $selected = null;
        }

        $ddl = [];
        $datasets = $this->datasetRepo->getDatasets($selected, true);
        foreach ($datasets as $dataset) {
            $ddl[$dataset->TableName] = $this->sqlTableGenerator->renderTable($dataset);
        }
        ksort($ddl);

        file_put_contents(
            $outputFile,
            implode("\n\n", $ddl) . "\n"
        );

        return Command::SUCCESS;
    }
}
