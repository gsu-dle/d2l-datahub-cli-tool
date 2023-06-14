<?php

declare(strict_types=1);

namespace D2L\DataHub\Repository;

use D2L\DataHub\Model\Dataset;
use D2L\DataHub\Model\DatasetExtract;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\SemaphoreStore;

class DatasetExtractRepository implements DatasetExtractRepositoryInterface
{
    private LockInterface $lock;
    private int $expiresOn;


    /**
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(private CacheItemPoolInterface $cache)
    {
        $this->lock = (new LockFactory(new SemaphoreStore()))->createLock(self::class);
        $this->expiresOn = intval(strtotime('-45 days'));
    }


    /**
     * @param Dataset|Dataset[] $selected
     * @param string $bdsType
     * @return DatasetExtract[]
     */
    public function getExtracts(
        Dataset|array $selected,
        string $bdsType = 'All'
    ): array {
        try {
            $this->lock->acquire(true);
            return $this->__getExtracts($selected, $bdsType);
        } finally {
            $this->lock->release();
        }
    }


    /**
     * @param DatasetExtract[]|null $extracts
     * @return bool
     */
    public function setExtracts(?array $extracts): bool
    {

        try {
            $this->lock->acquire(true);
            return $this->__setExtracts($extracts);
        } finally {
            $this->lock->release();
        }
    }


    /**
     * @param Dataset|Dataset[] $selected
     * @param string $bdsType
     * @return DatasetExtract[]
     */
    private function __getExtracts(
        Dataset|array $selected,
        string $bdsType = 'All'
    ): array {
        if ($selected instanceof Dataset) {
            $selected = [$selected];
        }
        foreach ($selected as $idx => $dataset) {
            $selected[$dataset->SearchName] = $dataset;
            unset($selected[$idx]);
        }

        $extracts = [];

        $_extracts = $this->cache->getItem('extracts')->get();
        if (is_array($_extracts)) {
            foreach ($_extracts as $extract) {
                if (!$extract instanceof DatasetExtract) {
                    continue;
                }
                if (count($selected) > 0 && !isset($selected[$extract->Dataset->SearchName])) {
                    continue;
                }
                if ($bdsType !== 'All' && $extract->BdsType !== $bdsType) {
                    continue;
                }
                if ($extract->CreatedDate < $this->expiresOn) {
                    continue;
                }

                $extracts[$extract->FileName] = $extract;
            }
        }

        return $extracts;
    }


    /**
     * @param DatasetExtract[]|null $extracts
     * @return bool
     */
    private function __setExtracts(?array $extracts): bool
    {
        if ($extracts === null) {
            return $this->cache->deleteItem('extracts');
        }

        $_extracts = $this->__getExtracts([], 'All');
        foreach ($extracts as $extract) {
            if ($extract->CreatedDate >= $this->expiresOn) {
                $_extracts[$extract->FileName] = $extract;
            }
        }
        ksort($_extracts);

        return $this->cache->save(
            $this->cache->getItem('extracts')->set($_extracts)
        );
    }
}
