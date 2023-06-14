<?php

declare(strict_types=1);

namespace D2L\DataHub\Model;

class DatasetField
{
    public readonly string $Name;
    public readonly string $Description;
    public readonly string $DataType;
    public readonly string $ColumnSize;
    public readonly bool $CanBeNull;
    public readonly bool $PK;
    public readonly bool $FK;
    public readonly string $VersionHistory;


    /**
     * @param string $name
     * @param string $description
     * @param string $dataType
     * @param string $columnSize
     * @param string $key
     * @param string $versionHistory
     */
    public function __construct(
        string $name = '',
        string $description = '',
        string $dataType = '',
        string $columnSize = '',
        string $key = '',
        string $versionHistory = '',
    ) {
        $this->Name           = trim($name, " \t\n\r\0\x0B\xc2\xa0");
        $this->Description    = trim(strval(preg_replace('/[ \t\r\n]+/', ' ', $description)), " \t\n\r\0\x0B\xc2\xa0");
        $this->DataType       = trim($dataType, " \t\n\r\0\x0B\xc2\xa0");
        $this->ColumnSize     = trim($columnSize, " \t\n\r\0\x0B\xc2\xa0");
        $this->CanBeNull      = str_contains($this->Description, 'Field can be null');
        $this->PK             = str_contains($key, 'PK');
        $this->FK             = str_contains($key, 'FK');
        $this->VersionHistory = trim($versionHistory, " \t\n\r\0\x0B\xc2\xa0");
    }
}
