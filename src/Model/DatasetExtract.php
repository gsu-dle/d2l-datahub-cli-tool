<?php

declare(strict_types=1);

namespace D2L\DataHub\Model;

class DatasetExtract
{
    public readonly Dataset $Dataset;
    public readonly string $FileName;
    public readonly string $SchemaId;
    public readonly string $PluginId;
    public readonly string $BdsType;
    public readonly int $CreatedDate;
    public readonly string $DownloadLink;
    public readonly int $DownloadSize;
    public readonly int $QueuedForProcessingDate;
    public readonly string $Version;
    /** @var string[] $ProcessFiles */
    public array $ProcessFiles = [];
    public bool $ProcessFilesLoaded = false;


    /**
     * @param Dataset $dataset
     * @param string $schemaId
     * @param string $pluginId
     * @param string $bdsType
     * @param int|string $createdDate
     * @param string $downloadLink
     * @param int $downloadSize
     * @param int|string $queuedForProcessingDate
     * @param string $version
     */
    public function __construct(
        Dataset $dataset,
        string $schemaId,
        string $pluginId,
        string $bdsType,
        int|string $createdDate,
        string $downloadLink,
        int $downloadSize,
        int|string $queuedForProcessingDate,
        string $version
    ) {
        $this->Dataset = $dataset;
        $this->SchemaId = $schemaId;
        $this->PluginId = $pluginId;
        $this->BdsType = $bdsType;
        $this->CreatedDate = is_string($createdDate) ? intval(strtotime($createdDate)) : $createdDate;
        $this->DownloadLink = $downloadLink;
        $this->DownloadSize = $downloadSize;
        $this->QueuedForProcessingDate = is_string($queuedForProcessingDate)
            ? intval(strtotime($queuedForProcessingDate))
            : $queuedForProcessingDate;
        $this->Version = $version;
        $this->FileName = strval(preg_replace('/[ \t\r\n]+/', '', $dataset->Name))
            . '_' . $bdsType
            . '_' . strval(date('Ymd_His', $this->CreatedDate))
            . '.zip';
    }
}
