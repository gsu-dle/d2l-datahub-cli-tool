<?php

declare(strict_types=1);

namespace D2L\DataHub\Model;

class Dataset
{
    public string $SchemaId = '';
    public string $SchemaURL = '';
    public ?DatasetExtract $LatestFullExtract = null;

    public readonly string $Name;
    public readonly string $SearchName;
    public readonly string $TableName;
    public readonly string $About;
    /** @var DatasetField[] $Fields */
    public readonly array $Fields;

    /**
     * @param string $name
     * @param string $about
     * @param DatasetField[] $fields
     */
    public function __construct(
        string $name = '',
        string $about = '',
        array $fields = []
    ) {
        $this->Name       = trim($name, " \t\n\r\0\x0B\xc2\xa0");
        $this->SearchName = strval(preg_replace('/[ \t\r\n]+/', '', strtoupper($this->Name)));
        $this->TableName  = 'D2L_' . str_replace(" ", "_", strtoupper($this->Name));
        $this->About      = trim($about, " \t\n\r\0\x0B\xc2\xa0");
        $this->Fields     = $fields;
    }
}
