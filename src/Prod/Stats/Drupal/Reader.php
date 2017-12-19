<?php

namespace Drupal\Prod\Stats\Drupal;

use Drupal\Prod\Error\DevelopperException;
use Drupal\Prod\Error\ProdException;
use Drupal\Prod\Output\Formatter;
use Drupal\Prod\Output\Column;
use Drupal\Prod\ProdObject;

/**
 * Drupal\Prod\\Stats\Drupal\Reader class
 *
 * Reader of stored statictics data concerning Drupal stats.
 */
class Reader extends ProdObject
{

    /**
     *
     * @var array of \Drupal\Prod\Stats\Drupal object (for sort-of-Singletons)
     * keyed by data_id
     */
    protected static $instances;

    /**
     * Data id of the Reader instance (can be used in caches of definitions)
     */
    protected $id;

    /**
     * Singleton implementation
     *
     * @return \Drupal\Prod\Stats\Drupal
     */
    public static function getInstance($data_id)
    {
        if (!isset(self::$instances)) {
            self::$instances = array();
        }

        if (!array_key_exists($data_id, self::$instances)) {

            self::$instances[$data_id] = new Reader($data_id);
        }

        return self::$instances[$data_id];
    }

    public function __construct( $id )
    {
        $this->id = $id;
        return $this->initHelpers();
    }

    public function getId()
    {
        return $this->id;
    }

    protected function _getContentTypesFormatter()
    {
        $formatter = new Formatter();

        $formatter->setGraphType('2StackedBars')
                ->setGraphLeftAxis('total', t('Nb nodes'))
                ->setGraphBottomAxis('name');

        $col = new Column();
        $col->mapColumn('ptq_stat_tid')
            ->setLabel('id')
            ->addFormat('id')
            ->setTitle('id');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pds_name')
            ->setLabel('name')
            ->flagInTitle(TRUE)
            ->setTitle('Name')
            ->addFormat('check_plain')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('published')
            ->setLabel('published_h')
            ->addFormat('undo_factor1000')
            ->addFormat('human_int')
            ->setTitle('Published')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('published')
            ->setLabel('published')
            ->addFormat('undo_factor1000')
            ->setTitle('Published')
            ->flagStacked()
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('unpublished')
            ->setLabel('unpublished_h')
            ->addFormat('undo_factor1000')
            ->addFormat('human_int')
            ->setTitle('Unpublished')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('unpublished')
            ->setLabel('unpublished')
            ->addFormat('undo_factor1000')
            ->setTitle('Unpublished')
            ->flagStacked()
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('total')
            ->setLabel('total_h')
            ->addFormat('undo_factor1000')
            ->addFormat('human_int')
            ->setStyle('text-align: right')
            ->setTitle('Total')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('total')
            ->setLabel('total')
            ->addFormat('undo_factor1000')
            ->setStyle('text-align: right')
            ->setTitle('Total')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pds_timestamp')
            ->setLabel('timestamp')
            ->addFormat('interval')
            ->setTitle('Last Check')
            ->setStyle('text-align: right');
        $formatter->addColumn( $col );

        return $formatter;
    }


    public function getContentTypesDefinition()
    {
        $formatter = $this->_getContentTypesFormatter();

        return $formatter->renderDefinition();
    }


    /**
     * Return the nodes by content types rows
     *
     * @param string $sort 'published'/'total'
     */
    public function getContentTypesData($sort='published')
    {
        $sortidx = [];
        $query = db_select('prod_drupal_stats', 's')
            ->fields('s',array(
                'ptq_stat_tid',
                'pds_name',
                'pds_is_1024',
                'pds_value',
                'pds_timestamp'))
            ->condition('pds_enable', 1);
        $or = db_or();
        $or->condition('pds_name', 'node_%_total', 'LIKE');
        $or->condition('pds_name', 'node_%_published', 'LIKE');
        $query->condition($or);
        $query->orderBy('pds_name','ASC');

        $results = $query->execute();

        $data = array();
        foreach ($results as $result) {
            if (substr($result->pds_name, -1) == 'd') {
                // published
                $name = str_replace('_published', '', str_replace('node_', '',$result->pds_name));
                // always, because order by name
                //if (!array_key_exists($name, $data)) {
                    $data[$name] = $result;
                    $data[$name]->pds_name = $name;
                //}
                $data[$name]->published = $result->pds_value;
                if ('published' === $sort) {
                    $sortidx[] = $data[$name]->published;
                }
            }
            if (substr($result->pds_name, -1) == 'l') {
                // total
                $name = str_replace('_total', '', str_replace('node_', '',$result->pds_name));
                $data[$name]->total = $result->pds_value;
                $data[$name]->unpublished = $data[$name]->total - $data[$name]->published;
                if ('published' !== $sort) {
                    $sortidx[] = $data[$name]->total;
                }
            }
        }
        //var_dump($sortidx);
        //var_dump($data);

        array_multisort($sortidx, SORT_DESC, SORT_NUMERIC, $data);
        //array_multisort($data, SORT_DESC, SORT_NUMERIC, $sort_idx);

        $formatter = $this->_getContentTypesFormatter();

        $formatter->setData($data);

        return $formatter->render();

    }

    /**
     * Return the Users flat stats
     *
     * @param string $sort 'published'/'total'
     */
    public function getUsersStats() {
        $stats = array(
            'user_total',
            'user_enabled',
            'user_active',
            'online_users',
            'day_active_users',
            'month_active_users',
        );
        $query = db_select('prod_drupal_stats', 's')
            ->fields('s',array(
                'ptq_stat_tid',
                'pds_name',
                'pds_is_1024',
                'pds_value',
                'pds_timestamp'))
                ->condition('pds_enable', 1)
                ->condition('pds_name', $stats,'IN');

        $results = $query->execute();

        $out = array(
            '#theme' => 'item_list',
            '#list_type' => 'ul',
            '#items' => array(
                0 => '',
                1 => '',
                2 => '',
                3 => '',
                4 => '',
                5 => '',
            ),
            '#attributes' => ['class' => 'stats-list'],
            '#wrapper_attributes' => ['class' => 'container'],
        );
        foreach ($results as $result) {
            switch ($result->pds_name) {
                case 'user_total':
                    $out['#items'][0] = t('Total users : ') . (int) ($result->pds_value / 1000);
                    break;
                case 'user_enabled':
                $out['#items'][1] = t('Total enabled users : ') . (int) ($result->pds_value / 1000);
                    break;
                case 'user_active':
                $out['#items'][2] = t('Total active users : ') . (int) ($result->pds_value / 1000);
                    break;
                case 'online_users':
                $out['#items'][3] = t('Online users : ') . (int) ($result->pds_value / 1000);
                    break;
                case 'day_active_users':
                $out['#items'][4] = t('Users Active today: ') . (int) ($result->pds_value / 1000);
                    break;
                case 'month_active_users':
                $out['#items'][5] = t('Users active this month: ') . (int) ($result->pds_value / 1000);
                    break;
            }
        }
        return $out;
    }
}
