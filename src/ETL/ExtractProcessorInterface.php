<?php

declare(strict_types=1);

namespace D2L\DataHub\ETL;

use D2L\DataHub\Model\DatasetExtract;

interface ExtractProcessorInterface
{
    /**
     * @param DatasetExtract $extract
     * @param bool $force
     * @return bool
     */
    public function processExtract(
        DatasetExtract $extract,
        bool $force = false
    ): bool;
}
