<?php

declare(strict_types=1);

namespace D2L\DataHub\Command;

use D2L\DataHub\ETL\ExtractProcessorInterface;
use D2L\DataHub\Repository\DatasetExtractRepositoryInterface;
use D2L\DataHub\Repository\DatasetRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'datahub:process',
    description: 'Process downloaded datasets'
)]
class DatahubProcessCommand extends Command
{
    /**
     * @param DatasetRepositoryInterface $datasetRepo
     * @param DatasetExtractRepositoryInterface $extractRepo
     * @param ExtractProcessorInterface $extractProcessor
     */
    public function __construct(
        private DatasetRepositoryInterface $datasetRepo,
        private DatasetExtractRepositoryInterface $extractRepo,
        private ExtractProcessorInterface $extractProcessor
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
                name: 'force',
                description: 'Clear cache and process downloaded dataset extracts as specified by command arguments'
            )
            ->addArgument(
                'type',
                InputArgument::OPTIONAL,
                'Type of dataset to process. Valid options are \'Full\', \'Differential\', and \'All\'',
                'All'
            )
            ->addArgument(
                'datasets',
                InputArgument::IS_ARRAY,
                'Specific dataset(s) to process. If this is not specified, all downloaded datasets are processed.'
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
        $force = $input->getOption('force') === true;
        $bdsTypes = $input->getArgument('type');
        if (!is_string($bdsTypes) || !in_array($bdsTypes, ['Full', 'Differential', 'All'], true)) {
            throw new \RuntimeException('Invalid arguments');
        }
        $bdsTypes = match ($bdsTypes) {
            'All' => ['Full','Differential'],
            default => [$bdsTypes]
        };
        $selected = $input->getArgument('datasets');
        if (!is_array($selected) || count($selected) < 1) {
            $selected = null;
        }


        if ($force) {
            $extracts = $this->extractRepo->getExtracts(
                $this->datasetRepo->getDatasets($selected, true),
                'All'
            );
            foreach ($extracts as $extract) {
                foreach ($extract->ProcessFiles as $processFile) {
                    unlink($processFile);
                }
                $extract->ProcessFiles = [];
                $extract->ProcessFilesLoaded = false;
            }
            $this->extractRepo->setExtracts($extracts);
        }

        foreach ($bdsTypes as $bdsType) {
            $extracts = $this->extractRepo->getExtracts(
                $this->datasetRepo->getDatasets($selected, true),
                $bdsType
            );

            foreach ($extracts as $extract) {
                if (
                    $extract->Dataset->LatestFullExtract === null
                    || $extract->Dataset->LatestFullExtract->CreatedDate > $extract->CreatedDate
                ) {
                    continue;
                }

                $this->extractProcessor->processExtract($extract, $force);
                $this->extractRepo->setExtracts([$extract]);
            }
        }

        return Command::SUCCESS;
    }
}
