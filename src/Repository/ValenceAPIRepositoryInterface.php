<?php

declare(strict_types=1);

namespace D2L\DataHub\Repository;

use D2L\DataHub\Model\Dataset;
use D2L\DataHub\Model\DatasetExtract;

interface ValenceAPIRepositoryInterface
{
    /**
     * @return ?string
     */
    public function getClientId(): ?string;


    /**
     * @return ?string
     */
    public function getClientSecret(): ?string;


    /**
     * @return ?string
     */
    public function getRefreshToken(): ?string;


    /**
     * @return ?string
     */
    public function getAccessToken(): ?string;


    /**
     * @return array<string,string>
     */
    public function listDatasets(): array;


    /**
     * @param Dataset $dataset
     * @return DatasetExtract[]
     */
    public function listDatasetExtracts(Dataset $dataset): array;


    /**
     * @param DatasetExtract $datasetExtract
     * @param string $outputLocation
     * @return void
     */
    public function downloadDatasetExtract(
        DatasetExtract $datasetExtract,
        string $outputLocation,
    ): void;


    /**
     * @param ?string $clientId
     * @return bool
     */
    public function setClientId(?string $clientId): bool;


    /**
     * @param ?string $clientSecret
     * @return bool
     */
    public function setClientSecret(?string $clientSecret): bool;


    /**
     * @param ?string $refreshToken
     * @return bool
     */
    public function setRefreshToken(?string $refreshToken): bool;


    /**
     * @param string|null $accessToken
     * @param int $expiresAfter
     * @return bool
     */
    public function setAccessToken(
        ?string $accessToken,
        int $expiresAfter = 0
    ): bool;
}
