<?php

declare(strict_types=1);

namespace D2L\DataHub\ETL;

use D2L\DataHub\Model\Dataset;
use D2L\DataHub\Model\DatasetExtract;
use D2L\DataHub\Repository\ValenceAPIRepositoryInterface;
use D2L\DataHub\Util\StringUtils;
use Psr\Log\LoggerInterface;

class ExtractDownloader implements ExtractDownloaderInterface
{
    /**
     * @param ValenceAPIRepositoryInterface $apiRepo
     * @param LoggerInterface $logger
     * @param string $extractsDir
     */
    public function __construct(
        private ValenceAPIRepositoryInterface $apiRepo,
        private LoggerInterface $logger,
        private string $extractsDir
    ) {
    }


    /**
     * @param Dataset $dataset
     * @param string $bdsType
     * @param bool $force
     * @return DatasetExtract[]
     */
    public function downloadExtracts(
        Dataset $dataset,
        string $bdsType = 'All',
        bool $force = false
    ): array {
        $extracts = [];

        $_extracts = $this->apiRepo->listDatasetExtracts($dataset);
        foreach ($_extracts as $extract) {
            if ($bdsType !== 'All' && $bdsType !== $extract->BdsType) {
                continue;
            }

            $filePath = $this->extractsDir . '/' . $extract->FileName;
            if ($force === true || !file_exists($filePath)) {
                $start = microtime(true);

                $this->apiRepo->downloadDatasetExtract($extract, $filePath);
                $extracts[$extract->FileName] = $extract;

                $this->logger->info(
                    sprintf(
                        "%s - Size=>%s kB; Elapsed=>%s",
                        $extract->FileName,
                        number_format($extract->DownloadSize / 1024, 3),
                        StringUtils::formatElapsedTime($start)
                    )
                );
            }
        }

        return $extracts;
    }
}
