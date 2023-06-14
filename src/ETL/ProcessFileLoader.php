<?php

declare(strict_types=1);

namespace D2L\DataHub\ETL;

use D2L\DataHub\Model\DatasetExtract;
use D2L\DataHub\Util\StringUtils;
use Psr\Log\LoggerInterface;

class ProcessFileLoader implements ProcessFileLoaderInterface
{
    /**
     * @param \mysqli $mysql
     * @param LoggerInterface $logger
     */
    public function __construct(
        private \mysqli $mysql,
        private LoggerInterface $logger
    ) {
    }


    /**
     * @param DatasetExtract $extract
     * @return bool
     */
    public function loadProcessFiles(DatasetExtract $extract): bool
    {
        $totalStart = microtime(true);

        foreach ($extract->ProcessFiles as $processFile) {
            $start = microtime(true);
            $this->loadFile($processFile);
            $this->logger->info(
                sprintf(
                    "%s - Elapsed=>%s",
                    basename($processFile),
                    StringUtils::formatElapsedTime($start)
                )
            );
        }

        $this->logger->info(
            sprintf(
                "%s_%s_%s - Files=>%'.08d; Elapsed=>%s",
                $extract->Dataset->Name,
                $extract->BdsType,
                date('Ymd_His', $extract->CreatedDate),
                count($extract->ProcessFiles),
                StringUtils::formatElapsedTime($totalStart)
            )
        );

        return true;
    }


    /**
     * @param string $file
     * @return void
     */
    private function loadFile(string $file): void
    {
        if ($this->mysql->multi_query($this->readFile($file)) === false) {
            throw new \RuntimeException(); // TODO: add message
        }
        while ($this->mysql->more_results()) {
            if ($this->mysql->next_result() === false) {
                throw new \RuntimeException(); // TODO: add message
            }
        }
    }


    /**
     * @param string $path
     * @return string
     */
    private function readFile(string $path): string
    {
        try {
            $gzfile = gzopen($path, 'r');
            if (!is_resource($gzfile)) {
                throw new \RuntimeException($path); // TODO: add message
            }

            $sql = '';
            while (!gzeof($gzfile)) {
                $buffer = gzread($gzfile, 4096);
                if (is_string($buffer)) {
                    $sql .= $buffer;
                }
            }

            if ($sql === '') {
                throw new \RuntimeException($path); // TODO: add message
            }

            gzclose($gzfile);

            return $sql;
        } finally {
            if (is_resource($gzfile ?? null)) {
                gzclose($gzfile);
            }
        }
    }
}
