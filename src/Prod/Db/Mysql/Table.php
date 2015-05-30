<?php

namespace Drupal\Prod\Db\Mysql;

use Drupal\Prod\Error\DbAnalyzerException;
use Drupal\Prod\Db\AbstractTable;
use Drupal\Prod\Db\TableInterface;

/**
 * MySQL DB Analyzer Table
 */
class Table extends AbstractTable implements TableInterface
{
    /**
     * Initialize the TableInterface object whith an array of data extracted
     * from the Analyzer extractTables method.
     * Note: This method is not always called by the Factory.
     *
     * @param array $data record extracted from the Analyzer extractTables
     *
     * @return TableInterface
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function init($data)
    {
        if (!is_array($data) 
          || !array_key_exists('data_length', $data)) {
            throw new DbAnalyzerException('data_length key is not present in the given data entry');
        }
        if (!array_key_exists('index_length', $data)) {
            throw new DbAnalyzerException('index_length key is not present in the given data entry');
        }
        if (!array_key_exists('table_rows', $data)) {
            throw new DbAnalyzerException('table_rows key is not present in the given data entry');
        }
        if (!array_key_exists('table_name', $data)) {
            throw new DbAnalyzerException('table_name key is not present in the given data entry');
        }
        $this->setSize($data['data_length'])
            ->setIndexSize($data['index_length'])
            ->setRows($data['table_rows'])
            ->setTable($data['table_name']);
        return $this;
    }

}
