<?php

declare(strict_types=1);

namespace D2L\DataHub\Command;

use D2L\DataHub\ETL\ExtractDownloaderInterface;
use D2L\DataHub\Repository\DatasetExtractRepositoryInterface;
use D2L\DataHub\Repository\DatasetRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'datahub:download',
    description: 'Download available datasets from D2L'
)]
class DatahubDownloadCommand extends Command
{
    /**
     * @param DatasetRepositoryInterface $datasetRepo
     * @param DatasetExtractRepositoryInterface $extractRepo
     * @param ExtractDownloaderInterface $extractDownloader
     */
    public function __construct(
        private DatasetRepositoryInterface $datasetRepo,
        private DatasetExtractRepositoryInterface $extractRepo,
        private ExtractDownloaderInterface $extractDownloader
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
                description: 'Clear cache and download datasets as specified by command arguments'
            )
            ->addArgument(
                'type',
                InputArgument::OPTIONAL,
                'Type of dataset to download. Valid options are \'Full\', \'Differential\', and \'All\'',
                'All'
            )
            ->addArgument(
                'datasets',
                InputArgument::IS_ARRAY,
                'Specific dataset(s) to download. If this is not specified, all available datasets are downloaded.'
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
        $bdsType = $input->getArgument('type');
        if (!is_string($bdsType) || !in_array($bdsType, ['Full', 'Differential', 'All'], true)) {
            throw new \InvalidArgumentException();
        }
        $selected = $input->getArgument('datasets');
        if (!is_array($selected) || count($selected) < 1) {
            $selected = null;
        }

        if ($force === true) {
            $this->extractRepo->setExtracts(null);
        }

        $datasets = $this->datasetRepo->getDatasets($selected, true);
        foreach ($datasets as $dataset) {
            $extracts = $this->extractDownloader->downloadExtracts($dataset, $bdsType, $force);

            foreach ($extracts as $extract) {
                if ($extract->BdsType === 'Full') {
                    if (
                        $dataset->LatestFullExtract === null
                        || $dataset->LatestFullExtract->CreatedDate < $extract->CreatedDate
                    ) {
                        $dataset->LatestFullExtract = $extract;
                    }
                }
            }

            $this->extractRepo->setExtracts($extracts);
        }
        $this->datasetRepo->setDatasets($datasets);

        return Command::SUCCESS;
    }
}
