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
    name: 'schema:load',
    description: 'Create MySQL tables'
)]
class SchemaLoadCommand extends Command
{
    /**
     * @param DatasetRepositoryInterface $datasetRepo
     * @param SQLTableGeneratorInterface $sqlTableGenerator
     * @param \mysqli $mysql
     */
    public function __construct(
        private DatasetRepositoryInterface $datasetRepo,
        private SQLTableGeneratorInterface $sqlTableGenerator,
        private \mysqli $mysql
    ) {
        parent::__construct();
    }


    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
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
        $selected = $input->getArgument('datasets');
        if (!is_array($selected) || count($selected) < 1) {
            $selected = null;
        }

        $datasets = $this->datasetRepo->getDatasets($selected, true);
        foreach ($datasets as $dataset) {
            $tableSql = $this->sqlTableGenerator->renderTable($dataset);
            if ($this->mysql->multi_query($tableSql) === false) {
                throw new \RuntimeException(); // TODO: add message
            }
            while ($this->mysql->more_results()) {
                if ($this->mysql->next_result() === false) {
                    throw new \RuntimeException(); // TODO: add message
                }
            }
        }

        return Command::SUCCESS;
    }
}
