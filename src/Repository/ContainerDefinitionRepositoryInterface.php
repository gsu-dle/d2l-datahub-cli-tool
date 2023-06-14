<?php

declare(strict_types=1);

namespace D2L\DataHub\Repository;

use DI\Definition\Source\DefinitionSource;

interface ContainerDefinitionRepositoryInterface extends DefinitionSource
{
    /**
     * @return array<string,mixed>
     */
    public function getContainerDefinitions(): array;
}
