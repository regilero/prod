<?php

namespace Drupal\Prod\Db\Pgsql;

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

        $this->setDbDriver('pgsql');

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
        SELECT
            -- n.nspname AS schemaname,
            c.relname as table_name,
            reltuples as table_rows,
            pg_table_size(c.oid) as data_length,
            pg_indexes_size(c.oid) as index_length
        FROM pg_class c
          LEFT JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE c.relkind IN ('r','')
          AND n.nspname <> 'pg_catalog'
          AND n.nspname <> 'information_schema'
          AND n.nspname !~ '^pg_toast'
          AND pg_catalog.pg_table_is_visible(c.oid)
          AND :db_name = :db_name
        ";
        return $qry;
    }

    public function _getQueryFilterTableList()
    {
        return " AND c.relname IN (:list)";
    }

    public function _getQueryFilterTableExpr()
    {
        return "AND c.relname like ':prefix%'";
    }

    protected function _getUntrackedTables($limit)
    {

        $query = "
            SELECT c.relname as table_name
            FROM pg_class c
              LEFT JOIN pg_namespace n ON n.oid = c.relnamespace
              LEFT JOIN {prod_db_stats} s
                ON (s.pdb_identifier = :identifier
                  AND s.pdb_db_name = :db_name
                  AND s.pdb_table = c.relname)
            WHERE c.relkind IN ('r','')
                AND n.nspname <> 'pg_catalog'
                AND n.nspname <> 'information_schema'
                AND n.nspname !~ '^pg_toast'
                AND pg_catalog.pg_table_is_visible(c.oid)
                AND s.pdb_db_name IS NULL
        ";
        $args = array(
            ':db_name' => $this->getDbName(),
            ':identifier' => $this->getDbIdentifier(),
        );

        // On databases using some prefix we need to filter out the results
        if (!empty($this->getDbPrefix())) {
            $query .= "AND c.relname like ':prefix%' \n";
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
            WHEN position('_' IN pdb_table) > 0
            THEN
                UPPER(LEFT(pdb_table, 1)) || SUBSTRING(pdb_table, 2, position('_' in pdb_table) -2)
            ELSE
                UPPER(LEFT(pdb_table, 1)) || LOWER(SUBSTRING(pdb_table,2,7))
            END
        ";

    }

}
