<?php

declare(strict_types=1);

namespace D2L\DataHub\ETL;

use D2L\DataHub\Model\DatasetExtract;

interface ProcessFileLoaderInterface
{
    /**
     * @param DatasetExtract $extract
     * @return bool
     */
    public function loadProcessFiles(DatasetExtract $extract): bool;
}
