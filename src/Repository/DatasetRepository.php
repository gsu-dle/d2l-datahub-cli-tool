<?php

declare(strict_types=1);

namespace D2L\DataHub\Repository;

use D2L\DataHub\ETL\SchemaDownloaderInterface;
use D2L\DataHub\Model\Dataset;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\SemaphoreStore;

class DatasetRepository implements DatasetRepositoryInterface
{
    private LockInterface $lock;


    /**
     * @param CacheItemPoolInterface $cache
     * @param SchemaDownloaderInterface $schemaDownloader
     */
    public function __construct(
        private CacheItemPoolInterface $cache,
        private SchemaDownloaderInterface $schemaDownloader
    ) {
        $this->lock = (new LockFactory(new SemaphoreStore()))->createLock(self::class);
    }


    /**
     * @param ?string[] $selected
     * @param bool $excludeMissingID
     * @return Dataset[]
     */
    public function getDatasets(
        ?array $selected = null,
        bool $excludeMissingID = true
    ): array {
        try {
            $this->lock->acquire(true);
            return $this->__getDatasets($selected, $excludeMissingID);
        } finally {
            $this->lock->release();
        }
    }


    /**
     * @param Dataset[]|null $datasets
     * @return bool
     */
    public function setDatasets(?array $datasets): bool
    {
        try {
            $this->lock->acquire(true);
            return $this->__setDatasets($datasets);
        } finally {
            $this->lock->release();
        }
    }


    /**
     * @param ?string[] $selected
     * @param bool $excludeMissingID
     * @return Dataset[]
     */
    private function __getDatasets(
        ?array $selected = null,
        bool $excludeMissingID = true
    ): array {
        if (is_array($selected)) {
            if (count($selected) > 0) {
                foreach ($selected as &$name) {
                    $name = strval(preg_replace('/[ \t\r\n]+/', '', strtoupper($name)));
                }
            } else {
                $selected = null;
            }
        }

        $datasets = [];

        $_datasets = $this->cache->getItem('datasets')->get();
        if (!is_array($_datasets)) {
            $_datasets = $this->schemaDownloader->downloadDatasets();
            $this->__setDatasets($_datasets, false);
        }

        foreach ($_datasets as $dataset) {
            if (!$dataset instanceof Dataset) {
                continue;
            }
            if ($excludeMissingID === true && $dataset->SchemaId === '') {
                continue;
            }
            if (is_array($selected) && !in_array($dataset->SearchName, $selected, true)) {
                continue;
            }
            $datasets[$dataset->Name] = $dataset;
        }

        ksort($datasets);

        return $datasets;
    }


    /**
     * @param Dataset[]|null $datasets
     * @param bool $update
     * @return bool
     */
    private function __setDatasets(?array $datasets, bool $update = true): bool
    {
        if ($datasets === null) {
            return $this->cache->deleteItem('datasets');
        }

        $_datasets = $update ? $this->__getDatasets(null, true) : [];
        foreach ($datasets as $dataset) {
            $_datasets[$dataset->Name] = $dataset;
        }
        ksort($_datasets);

        return $this->cache->save(
            $this->cache->getItem('datasets')->set($_datasets)
        );
    }
}
