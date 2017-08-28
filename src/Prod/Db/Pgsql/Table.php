<?php

namespace Drupal\Prod\Db\Pgsql;

use Drupal\Prod\Error\DbAnalyzerException;
use Drupal\Prod\Error\DevelopperException;
use Drupal\Prod\Db\AbstractTable;
use Drupal\Prod\Db\TableInterface;
use Drupal\Prod\Stats\Stat;

/**
 * PostgreSQL DB Analyzer Table
 */
class Table extends AbstractTable implements TableInterface
{

   private $seqscan_nb;
   private $seqscan_rows;
   private $idxscan_nb;
   private $idxscan_rows;
   private $inserts;
   private $updates;
   private $deletes;
   private $last_autovaccuum;
   private $last_autoanalyze;
   private $nb_autovaccuum;
   private $nb_autoanalyze;



   public function getSeqscanNb()
   {
       return $this->seqscan_nb;
   }
   public function setSeqscanNb($val)
   {
       $this->seqscan_nb = $val;
       return $this;
   }

   public function getSeqScanRows()
   {
       return $this->seqscan_rows;
   }
   public function setSeqScanRows($val)
   {
       $this->seqscan_rows = $val;
       return $this;
   }

   public function getIdxscanNb()
   {
       return $this->idxscan_nb;
   }
   public function setIdxscanNb($val)
   {
       $this->idxscan_nb = $val;
       return $this;
   }

   public function getIdxscanRows()
   {
       return $this->idxscan_rows;
   }
   public function setIdxscanRows($val)
   {
       $this->idxscan_rows = $val;
       return $this;
   }

   public function getInserts()
   {
       return $this->inserts;
   }
   public function setInserts($val)
   {
       $this->inserts = $val;
       return $this;
   }

   public function getUpdates()
   {
       return $this->updates;
   }
   public function setUpdates($val)
   {
       $this->updates = $val;
       return $this;
   }

   public function getDeletes()
   {
       return $this->deletes;
   }
   public function setDeletes($val)
   {
       $this->deletes = $val;
       return $this;
   }

   public function getLastAutoVaccuum()
   {
       return $this->last_autovaccuum;
   }
   public function setLastAutovaccum($val)
   {
       $this->last_autovaccuum = $val;
       return $this;
   }

   public function getLastAutoanalyze()
   {
       return $this->last_autoanalyze;
   }

   public function setLastAutoanalyze($val)
   {
       $this->last_autoanalyze = $val;
       return $this;
   }

   public function getNbAutovacuum()
   {
       return $this->nb_autovaccuum;
   }

   public function setNbAutovacuum($val)
   {
       $this->nb_autovaccuum = $val;
       return $this;
   }

   public function getNbAutoanalyze()
   {
       return $this->nb_autoanalyze;
   }

   public function setNbAutoanalyze($val)
   {
       $this->nb_autoanalyze = $val;
       return $this;
   }

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

        parent::init($data);

        if (!is_array($data)
          || !array_key_exists('seqscan_nb', $data)) {
            throw new DbAnalyzerException('seqscan_nb key is not present in the given data entry');
        }
        if (!array_key_exists('seqscan_rows', $data)) {
            throw new DbAnalyzerException('seqscan_rows key is not present in the given data entry');
        }
        if (!array_key_exists('idxscan_nb', $data)) {
            throw new DbAnalyzerException('idxscan_nb key is not present in the given data entry');
        }
        if (!array_key_exists('idxscan_rows', $data)) {
            throw new DbAnalyzerException('idxscan_rows key is not present in the given data entry');
        }
        if (!array_key_exists('inserts', $data)) {
            throw new DbAnalyzerException('inserts key is not present in the given data entry');
        }
        if (!array_key_exists('inserts', $data)) {
            throw new DbAnalyzerException('inserts key is not present in the given data entry');
        }
        if (!array_key_exists('updates', $data)) {
            throw new DbAnalyzerException('updates key is not present in the given data entry');
        }
        if (!array_key_exists('deletes', $data)) {
            throw new DbAnalyzerException('deletes key is not present in the given data entry');
        }
        if (!array_key_exists('last_autovaccuum', $data)) {
            throw new DbAnalyzerException('last_autovaccuum key is not present in the given data entry');
        }
        if (!array_key_exists('last_autoanalyze', $data)) {
            throw new DbAnalyzerException('last_autoanalyze key is not present in the given data entry');
        }
        if (!array_key_exists('nb_autovaccuum', $data)) {
            throw new DbAnalyzerException('nb_autovaccuum key is not present in the given data entry');
        }
        if (!array_key_exists('nb_autoanalyze', $data)) {
            throw new DbAnalyzerException('nb_autoanalyze key is not present in the given data entry');
        }
        $this->setSeqScanRows($data['seqscan_nb'])
            ->setSeqScanRows($data['seqscan_rows'])
            ->setIdxscanNb($data['idxscan_nb'])
            ->setIdxscanRows($data['idxscan_rows'])
            ->setInserts($data['inserts'])
            ->setUpdates($data['updates'])
            ->setDeletes($data['deletes'])
            ->setLastAutovaccum($data['last_autovaccuum'])
            ->setLastAutoanalyze($data['last_autoanalyze'])
            ->setNbAutovacuum($data['nb_autovaccuum'])
            ->setNbAutoanalyze($data['nb_autoanalyze'])
            ;
        return $this;
    }

   /**
     * Get the list of stats provided by this provider
     *
     * @return array List of StatInterface, the array key
     *               is the stat_col
     */
    public function getStatsList()
    {
        $stats = parent::getStatsList();

        $stats['seqscan_nb'] = new Stat(
            $this->id,
            'seqscan_nb',
            $this->getSeqscanNb(),
            $this->getTimestamp()
        );

        $stats['seqscan_rows'] = new Stat(
            $this->id,
            'seqscan_rows',
            $this->getSeqScanRows(),
            $this->getTimestamp()
        );

        $stats['idxscan_nb'] = new Stat(
            $this->id,
            'idxscan_nb',
            $this->getIdxscanNb(),
            $this->getTimestamp()
        );

        $stats['idxscan_rows'] = new Stat(
            $this->id,
            'idxscan_rows',
            $this->getIdxscanRows(),
            $this->getTimestamp()
        );

        $stats['inserts'] = new Stat(
            $this->id,
            'inserts',
            $this->getInserts(),
            $this->getTimestamp()
        );
        $stats['updates'] = new Stat(
            $this->id,
            'updates',
            $this->getUpdates(),
            $this->getTimestamp()
        );
        $stats['deletes'] = new Stat(
            $this->id,
            'deletes',
            $this->getDeletes(),
            $this->getTimestamp()
        );

        $stats['last_autovaccuum'] = new Stat(
            $this->id,
            'last_autovaccuum',
            $this->getLastAutovaccuum(),
            $this->getTimestamp()
        );
        $stats['last_autoanalyze'] = new Stat(
            $this->id,
            'last_autoanalyze',
            $this->getLastAutoanalyze(),
            $this->getTimestamp()
        );

        $stats['nb_autovaccuum'] = new Stat(
            $this->id,
            'nb_autovaccuum',
            $this->getNbAutovacuum(),
            $this->getTimestamp()
        );
        $stats['nb_autoanalyze'] = new Stat(
            $this->id,
            'nb_autoanalyze',
            $this->getNbAutoanalyze(),
            $this->getTimestamp()
        );

        return $stats;
    }


    /**
     * Save (upsert) the table record in prod_db_stats table.
     * This function is also responsible for feeding the
     * object with the database id (setId())
     *
     * @return TableInterface
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function save()
    {
        $this->logger->log('Saving record for table ' .  $this->getTable(),NULL, WATCHDOG_DEBUG);

        // Upsert the record
        try {
            $merge = db_merge('prod_db_stats')
                -> key( array(
                    'pdb_identifier' => $this->getDbIdentifier(),
                    'pdb_db_name' => $this->getDbName(),
                    'pdb_table' => substr($this->getTable(),0,255),
                    'pdb_is_database' => $this->getIsDatabase()
                ));
            if ($this->getIsDatabase()) {
                $merge->fields( array(
                    'pdb_size' => $this->getSize() * 100,
                    'pdb_idx_size' => $this->getIndexSize() * 100,
                    'pdb_full_size' => $this->getTotalSize() * 100,
                    'pdb_nb_rows' => $this->getRows() * 100,
                    'pdb_timestamp' => $this->getTimestamp(),
                    // TODO: getter/setter when this will go to ui
                    'pdb_enable' => 1,
                ));
            } else {
                $merge->fields( array(
                    'pdb_size' => $this->getSize() * 100,
                    'pdb_idx_size' => $this->getIndexSize() * 100,
                    'pdb_full_size' => $this->getTotalSize() * 100,
                    'pdb_nb_rows' => $this->getRows() * 100,
                    'pdb_timestamp' => $this->getTimestamp(),
                    'pdb_seqscan_nb' => $this->getSeqscanNb() * 100,
                    'pdb_seqscan_rows' => $this->getSeqScanRows() * 100,
                    'pdb_idxscan_nb' => $this->getIdxscanNb() * 100,
                    'pdb_idxscan_rows' => $this->getIdxscanRows() * 100,
                    'pdb_inserts' => $this->getInserts() * 100,
                    'pdb_updates' => $this->getUpdates() * 100,
                    'pdb_deletes' => $this->getDeletes() * 100,
                    'pdb_last_autovaccuum' => $this->getLastAutoVaccuum(),
                    'pdb_last_autoanalyze' => $this->getLastAutoanalyze(),
                    'pdb_nb_autovaccuum' => $this->getNbAutovacuum() * 100,
                    'pdb_nb_autoanalyze' => $this->getNbAutoanalyze() * 100,
                    // TODO: getter/setter when this will go to ui
                    'pdb_enable' => 1,
                    ));
            }
            $merge->execute();
        } catch (Exception $e) {
            throw new DbAnalyzerException(__METHOD__ . ": Unable to save the table record. " . $e->getMessage());
        }

        // get the record id
        try {
            $result = db_select('prod_db_stats', 's')
                ->fields('s',array('pdb_id'))
                ->condition('pdb_identifier',$this->getDbIdentifier())
                ->condition('pdb_db_name',$this->getDbName())
                ->condition('pdb_table',substr($this->getTable(),0,255))
                ->condition('pdb_is_database',$this->getIsDatabase())
                ->execute();
            foreach ($result as $res) {
                $id = $res->pdb_id;
            }

            $this->setId($id);
            return $this->getId();

        } catch (Exception $e) {
            throw new DbAnalyzerException(__METHOD__ . ": Unable to reload the table record. " . $e->getMessage());
        }

    }

}
