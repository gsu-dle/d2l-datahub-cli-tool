<?php

declare(strict_types=1);

namespace D2L\DataHub\ETL;

use D2L\DataHub\Model\Dataset;
use D2L\DataHub\Model\DatasetField;

class SQLTableGenerator implements SQLTableGeneratorInterface
{
    /**
     * @param Dataset $dataset
     * @inheritdoc
     */
    public function renderTable(Dataset $dataset): string
    {
        $tableCols = $this->renderTableColumns($dataset);
        return "DROP TABLE IF EXISTS `{$dataset->TableName}`;
CREATE TABLE `{$dataset->TableName}` (
  {$tableCols}
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    }


    /**
     * @param Dataset $dataset
     * @return string
     */
    private function renderTableColumns(Dataset $dataset): string
    {
        $tableCols = [];
        $primaryKeys = [];

        foreach ($dataset->Fields as $field) {
            $dataType = $this->getDataType($field);
            $canBeNull = $field->CanBeNull ? "DEFAULT NULL" : "NOT NULL";
            $tableCols[] = "  `{$field->Name}` {$dataType} {$canBeNull}";
            if ($field->PK) {
                $primaryKeys[] = $field->Name;
            }
        }

        if (count($primaryKeys) > 0) {
            $tableCols[] = '  UNIQUE KEY (`' . trim(implode('`, `', $primaryKeys)) . '`)';
        }

        return trim(implode(",\n", $tableCols));
    }


    /**
     * @param DatasetField $field
     * @return string
     */
    private function getDataType(DatasetField $field): string
    {
        switch (strtoupper($field->DataType)) {
            case 'BIGINT':
            case 'FLOAT':
            case 'INT':
                $dataType = strtoupper($field->DataType);
                break;
            case 'BIT':
                $dataType = 'TINYINT';
                break;
            case 'DATETIME2':
                $dataType = 'DATETIME';
                break;
            case 'DECIMAL':
                $dataType = 'DECIMAL';
                if ($field->ColumnSize !== '') {
                    $dataType .= '(' . $field->ColumnSize . ')';
                }
                break;
            default:
                $dataType = 'VARCHAR';
                $columnSize = $field->ColumnSize;
                if ($columnSize === '') {
                    $columnSize = '128';
                }
                if (intval($columnSize) >= 10000) {
                    $dataType = 'TEXT';
                }
                $dataType .= '(' . $columnSize . ')';
                break;
        }

        return $dataType;
    }
}
