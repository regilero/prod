<?php

namespace Drupal\Prod\Db\Mysql;

use Drupal\Prod\Error\DbAnalyzerException;
use Drupal\Prod\Error\DevelopperException;
use Drupal\Prod\Db\AbstractAnalyzer;
use Drupal\Prod\Db\AnalyzerInterface;

/**
 * MySQL Analyzer
 */
class Analyzer extends AbstractAnalyzer implements AnalyzerInterface
{

    /**
     * Initialize the AnalyzerInterface object
     *
     * @param array $db_arr Drupal database definition array
     *
     * @param string $identifier Drupal's internal identifier for this database
     *
     * @return AnalyzerInterface
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function init($db_arr, $identifier)
    {

        $this->setDbDriver('mysql');

        $this->setDbIdentifier($identifier);

        if (!is_array($db_arr)
          || !array_key_exists('database', $db_arr)) {
            throw new DbAnalyzerException('database key is not present in given database definition');
        }

        $this->setDbName($db_arr['database']);

        if (array_key_exists('prefix', $db_arr)
          && !empty($db_arr['prefix'])) {

            $this->setDbPrefix($db_arr['prefix']);

        } else {

            $this->setDbPrefix('');

        }

        return $this;
    }


    /**
     * Return the query used to list tables and tables informations
     */
    public function _getTablesInformationsQuery() {
        $qry = "
            SELECT table_name,
                   table_rows * 1000,
                   data_length * 1000,
                   index_length * 1000
            FROM information_schema.TABLES
            WHERE table_schema=:db_name
        ";
        return $qry;
    }

    public function _getQueryFilterTableList()
    {
        return " AND table_name IN (:list)";
    }
    public function _getQueryFilterTableExpr()
    {
        return "AND table_name like ':prefix%'";
    }

    protected function _getUntrackedTables($limit)
    {

        $query = "
            SELECT table_name
            FROM information_schema.TABLES t
            LEFT JOIN {prod_db_stats} s
              ON (s.pdb_identifier = :identifier
                AND s.pdb_db_name = t.table_schema
                AND s.pdb_table = t.table_name)
            WHERE t.table_schema = :db_name
            AND s.pdb_db_name IS NULL
        ";
        $args = array(
            ':db_name' => $this->getDbName(),
            ':identifier' => $this->getDbIdentifier(),
        );

        // On databases using some prefix we need to filter out the results
        if (!empty($this->getDbPrefix())) {
            $query .= "AND t.table_name like ':prefix%' \n";
            $args[':prefix'] = $this->getDbPrefix();
        }

        $query .= " LIMIT " . (int) $limit;

        // by default we'll try to use the slave connection, but if you do not
        // use the same db name or the same prefix as in the master/default you
        // should suspend this setting
        $options = array(
            'target' => variable_get('prod_db_stats_indexer_use_slave',TRUE)? 'slave': 'default',
            'fetch' => \PDO::FETCH_ASSOC,
        );
        $result = db_query( $query, $args, $options );

        $tables = array();
        foreach ($result->fetchAll() as $result) {
            $tables[] = $result['table_name'];
        }

        return $tables;

    }

    public function _getTablesGroupExpression()
    {
        return "
            CASE
            WHEN  LOCATE('_',pdb_table) > 0
            THEN
                CONCAT( UCASE( LEFT(pdb_table, 1)), SUBSTRING(pdb_table, 2, LOCATE('_',pdb_table) -2) )
            ELSE
                CONCAT( UCASE(LEFT(pdb_table, 1)), LCASE(SUBSTRING(pdb_table,2,7)) )
            END
        ";
    }

}
