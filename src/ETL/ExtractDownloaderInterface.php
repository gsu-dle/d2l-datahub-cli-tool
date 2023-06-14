<?php

declare(strict_types=1);

namespace D2L\DataHub\ETL;

use D2L\DataHub\Model\Dataset;
use D2L\DataHub\Model\DatasetExtract;

interface ExtractDownloaderInterface
{
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
    ): array;
}
