<?php

declare(strict_types=1);

namespace D2L\DataHub\ETL;

use D2L\DataHub\Model\DatasetExtract;
use D2L\DataHub\Model\DatasetField;
use D2L\DataHub\Util\StringUtils;
use Psr\Log\LoggerInterface;

class ExtractProcessor implements ExtractProcessorInterface
{
    /**
     * @param LoggerInterface $logger
     * @param string $extractsDir
     * @param string $processFilesDir
     * @param int $chunkSize
     */
    public function __construct(
        private LoggerInterface $logger,
        private string $extractsDir,
        private string $processFilesDir,
        private int $chunkSize
    ) {
    }


    /**
     * @param DatasetExtract $extract
     * @param bool $force
     * @return bool
     */
    public function processExtract(
        DatasetExtract $extract,
        bool $force = true
    ): bool {
        if ($force === false && count($extract->ProcessFiles) > 0) {
            return true;
        }

        $buffer = [];
        $extract->ProcessFiles = [];

        $firstBuffer = true;
        $start = $batchStart = microtime(true);
        $recordCount = $bufferCount = 0;

        $filePath = "{$this->extractsDir}/{$extract->FileName}";
        $outputFileNamePrefix = $extract->Dataset->TableName
            . "_" . date('YmdHis', $extract->CreatedDate)
            . "_" . $extract->BdsType;
        $outputFilePrefix = $this->processFilesDir
            . "/" . $outputFileNamePrefix;

        try {
            list($zipFile, $file)           = $this->openFile($filePath);
            list($fields, $fileColumnNames) = $this->getFields($extract, $file);
            list($sqlHeader, $sqlFooter)    = $this->getHeaderFooter($extract, $fileColumnNames, $fields);

            for ($now = microtime(true); $row = fgetcsv(stream: $file, escape: '"'); $now = microtime(true)) {
                $buffer[] = $this->genInsertRow($fields, $row);

                if ((++$recordCount) % $this->chunkSize === 0) {
                    $outputFilePath = $outputFilePrefix . '_' . sprintf('%\'.08d', ++$bufferCount) . '.sql.gz';
                    $outputFileContents = $sqlHeader . implode(",\n  ", $buffer) . $sqlFooter;
                    if ($firstBuffer === true && $extract->BdsType === 'Full') {
                        $outputFileContents = "TRUNCATE `{$extract->Dataset->TableName}`;\n\n" . $outputFileContents;
                    }

                    $this->writeBuffer($outputFilePath, $outputFileContents);

                    $extract->ProcessFiles[] = $outputFilePath;
                    $buffer = [];
                    if ($firstBuffer) {
                        $firstBuffer = false;
                    }
                }

                if ($now - $batchStart >= 15) {
                    $batchStart = $now;
                    $this->logger->info(
                        sprintf(
                            "%s - Files=>%'.08d; Records=>%'.09d; Elapsed=>%s; RPS=>%s",
                            $outputFileNamePrefix,
                            $bufferCount,
                            $recordCount,
                            StringUtils::formatElapsedTime($start),
                            number_format($recordCount / ($now - $start), 3)
                        )
                    );
                }
            }

            if (count($buffer) > 0) {
                $outputFilePath = $outputFilePrefix . '_' . sprintf('%\'.08d', ++$bufferCount) . '.sql.gz';
                $outputFileContents = $sqlHeader . implode(",\n  ", $buffer) . $sqlFooter;
                if ($firstBuffer === true && $extract->BdsType === 'Full') {
                    $outputFileContents = "TRUNCATE `{$extract->Dataset->TableName}`;\n\n" . $outputFileContents;
                }
                $this->writeBuffer($outputFilePath, $outputFileContents);
                $extract->ProcessFiles[] = $outputFilePath;
            }

            if ($recordCount > 0) {
                $this->logger->info(
                    sprintf(
                        "%s - Files=>%'.08d; Records=>%'.09d; Elapsed=>%s; RPS=>%s",
                        $outputFileNamePrefix,
                        $bufferCount,
                        $recordCount,
                        StringUtils::formatElapsedTime($start),
                        number_format($recordCount / (microtime(true) - $start), 3)
                    )
                );
            }

            return true;
        } finally {
            if (is_resource($file ?? null)) {
                fclose($file);
            }

            if (($zipFile ?? null) instanceof \ZipArchive) {
                /** @var \ZipArchive $zipFile */
                $zipFile->close();
            }
        }
    }


    /**
     * @param string $filePath
     * @return array{0:\ZipArchive,1:resource}
     */
    private function openFile(string $filePath): array
    {
        $zipFile = new \ZipArchive();
        if ($zipFile->open($filePath) !== true) {
            throw new \RuntimeException("Unable to open file: {$filePath}");
        }

        $file = $zipFile->getStream(strval($zipFile->getNameIndex(0)));
        if (!is_resource($file)) {
            throw new \RuntimeException("Unable to open file: {$filePath}");
        }

        // Strip byte order mark (BOM)
        fread($file, 3);

        return [$zipFile, $file];
    }


    /**
     * @param DatasetExtract $extract
     * @param resource $file
     * @return array{0:array<int,DatasetField>,1:string}
     */
    private function getFields(
        DatasetExtract $extract,
        $file
    ): array {
        /** @var array<int,DatasetField> $fields */
        $fields = [];
        $fileColumns = fgetcsv($file);
        if (!is_array($fileColumns)) {
            throw new \RuntimeException("Unable to read column labels");
        }
        foreach ($fileColumns as $idx => &$fileColumn) {
            $fileColumn = trim($fileColumn);
            foreach ($extract->Dataset->Fields as $field) {
                if ($field->Name === $fileColumn) {
                    $fields[$idx] = $field;
                    break;
                }
            }
            if (!isset($fields[$idx])) {
                unset($fileColumns[$idx]);
            }
        }
        if (count($fileColumns) < 1) {
            throw new \RuntimeException("There are no columns in the file that match the dataset schema");
        }
        $fileColumnNames = '`' . implode("`,`", $fileColumns) . '`';

        return [$fields, $fileColumnNames];
    }


    /**
     * @param DatasetExtract $extract
     * @param string $fileColumnNames
     * @param DatasetField[] $fields
     * @return array<int,string>
     */
    private function getHeaderFooter(
        DatasetExtract $extract,
        string $fileColumnNames,
        array &$fields
    ): array {
        return [
            "INSERT INTO `{$extract->Dataset->TableName}`\n"
                . "  ({$fileColumnNames})\n"
                . "VALUES\n  ",
            "\nAS new ON DUPLICATE KEY UPDATE\n"
                . implode(",\n", array_map(function (DatasetField $field) {
                    return "  `{$field->Name}`=new.`{$field->Name}`";
                }, $fields))
                . "\n;\n"
        ];
    }


    /**
     * @param array<int,DatasetField> $fields
     * @param array<int,string> $row
     *
     * @return string
     */
    private function genInsertRow(
        array &$fields,
        array &$row
    ): string {
        foreach ($row as $idx => &$value) {
            $field = $fields[$idx] ?? null;
            if ($field === null) {
                unset($row[$idx]);
                continue;
            }
            $this->formatValue($field, $value);
        }

        return "(" . implode(",", $row) . ")";
    }


    /**
     * @param DatasetField $field
     * @param string $value
     * @return void
     */
    private function formatValue(
        DatasetField $field,
        string &$value
    ): void {
        switch (strtoupper($field->DataType)) {
            case 'BIT':
                if ($field->CanBeNull && $value === '') {
                    $value = null;
                } else {
                    $value = ($value == "Y" || $value == "1" || $value == "T" || $value == "TRUE") ? "1" : "0";
                }
                break;
            case 'DATETIME2':
                if ($field->CanBeNull && $value === '') {
                    $value = null;
                } else {
                    $dateValue = @strtotime($value);
                    $value = is_numeric($dateValue) ? date('Y-m-d H:i:s', $dateValue) : $value;
                }
                break;
            default:
                if ($field->CanBeNull && $value === '') {
                    $value = null;
                }
                break;
        }

        if ($value === null) {
            $value = 'null';
        } else {
            $value = "'" . preg_replace('~[\x00\x0A\x0D\x1A\x22\x27\x5C]~u', '\\\$0', $value) . "'";
        }
    }


    /**
     * @param string $path
     * @param string $contents
     * @return bool
     */
    private function writeBuffer(
        string $path,
        string $contents
    ): bool {
        try {
            $gzfile = gzopen($path, 'w9');
            if (!is_resource($gzfile)) {
                throw new \RuntimeException(); // TODO: add message
            }

            $bytesWritten = gzwrite($gzfile, $contents);

            return is_int($bytesWritten) && $bytesWritten > 0;
        } finally {
            if (is_resource($gzfile ?? null)) {
                gzclose($gzfile);
            }
        }
    }
}
