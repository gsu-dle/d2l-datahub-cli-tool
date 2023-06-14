<?php

declare(strict_types=1);

namespace D2L\DataHub\ETL;

use D2L\DataHub\Model\Dataset;

interface SQLTableGeneratorInterface
{
    /**
     * @param Dataset $dataset
     * @return string
     */
    public function renderTable(Dataset $dataset): string;
}
