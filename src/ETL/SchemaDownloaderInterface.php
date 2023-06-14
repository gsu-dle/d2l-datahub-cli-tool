<?php

declare(strict_types=1);

namespace D2L\DataHub\ETL;

use D2L\DataHub\Model\Dataset;

interface SchemaDownloaderInterface
{
    /**
     * @return Dataset[]
     */
    public function downloadDatasets(): array;
}
