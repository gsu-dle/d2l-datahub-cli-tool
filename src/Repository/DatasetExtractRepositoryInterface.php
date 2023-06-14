<?php

declare(strict_types=1);

namespace D2L\DataHub\Repository;

use D2L\DataHub\Model\Dataset;
use D2L\DataHub\Model\DatasetExtract;

interface DatasetExtractRepositoryInterface
{
    /**
     * @param Dataset|Dataset[] $selected
     * @param string $bdsType
     * @return DatasetExtract[]
     */
    public function getExtracts(
        Dataset|array $selected,
        string $bdsType = 'All'
    ): array;


    /**
     * @param DatasetExtract[]|null $extracts
     * @return bool
     */
    public function setExtracts(?array $extracts): bool;
}
