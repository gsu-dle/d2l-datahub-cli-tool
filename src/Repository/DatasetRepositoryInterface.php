<?php

declare(strict_types=1);

namespace D2L\DataHub\Repository;

use D2L\DataHub\Model\Dataset;

interface DatasetRepositoryInterface
{
    /**
     * @param ?string[] $selected
     * @param bool $excludeMissingID
     * @return Dataset[]
     */
    public function getDatasets(
        ?array $selected = null,
        bool $excludeMissingID = true
    ): array;


    /**
     * @param Dataset[]|null $datasets
     * @return bool
     */
    public function setDatasets(?array $datasets): bool;
}
