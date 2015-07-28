<?php

namespace Drupal\Prod\Output;

use Drupal\Prod\ProdObject;
use Drupal\Prod\Output\Column;
use Drupal\Prod\Error\FormatterException;
use Drupal\Prod\Error\Drupal\Prod\Error;

/**
 * results Formatter Object
 *
 */
class Formatter extends ProdObject
{

    protected $key_column;
    protected $columns;
    protected $idxcolumns = array();
    protected $data;
    protected $graph_type;
    protected $x_axis;
    protected $y1_axis;
    protected $y2_axis;
    
    /**
     * 
     * @return \Drupal\Prod\Output\Formatter
     */
    public function __construct()
    {

        // load the helpers (like $this->logger)
        $this->initHelpers();
        
        $this->columns = array();

        return $this;

    }
    
    /**
     * Set the result key column identifier
     * 
     * @param string $key_column
     */
    public function setKey($key_column)
    {
        $this->key_column = $key_column;
        return $this;
    }

    /**
     * Get the result key column identifier if it exists (else NULL)
     *
     */
    public function getKey()
    {
        return $this->key_column;
    }
    
    public function setGraphType( $type ) {
        $this->graph_type = $type;
        return $this;
    }
    
    public function getGraphType() {
        return $this->graph_type;
    }
    
    public function setGraphLeftAxis( $json_label, $text=null) {
        $this->y1_axis = array(
            'json_label' => $json_label
        );
        if (isset($text)) {
            $this->y1_axis['has_text'] = TRUE;
            $this->y1_axis['text'] = $text;
        } else {
            $this->y1_axis['has_text'] = FALSE;
        }
        return $this;
    }
    
    public function setGraphRightAxis( $json_label, $text=null) {
        $this->y2_axis = array(
            'json_label' => $json_label
        );
        if (isset($text)) {
            $this->y2_axis['has_text'] = TRUE;
            $this->y2_axis['text'] = $text;
        } else {
            $this->y2_axis['has_text'] = FALSE;
        }
        return $this;
    }
    
    public function setGraphBottomAxis( $json_label, $text=null) {
        $this->x_axis = array(
            'json_label' => $json_label
        );
        if (isset($text)) {
            $this->x_axis['has_text'] = TRUE;
            $this->x_axis['text'] = $text;
        } else {
            $this->x_axis['has_text'] = FALSE;
        }
        return $this;
    }
    
    public function addColumn(Column $column)
    {
        $this->columns[] = $column;
        $this->idxcolumns[ $column->getLabel()] = $column;
        return $this;
    }
    

    public function setData( $data )
    {
        $this->data = $data;
        return $this;
    }
    
    public function getTitleColumns()
    {
        $cols = array();
        foreach ($this->columns as $j => $column) {
            
            if ($column->isInTitle()) {
               $cols[] = $column->getLabel();
            }
            
        }
        return $cols;
    }
    public function getTooltipColumns()
    {
        $cols = array();
        foreach ($this->columns as $j => $column) {
        
            if ($column->isInTooltip()) {
                $cols[] = array(
                        'legend' => $column->getTitle(),
                        'key' => $column->getLabel()
                );
            }
        
        }
        return $cols;
    }
    
    public function getStackedColumns()
    {
        $cols = array();
        foreach ($this->columns as $j => $column) {
            if ($column->isStacked()) {
                $cols[] = $column->getLabel();
            }
        
        }
        return $cols;
    }
    
    public function render()
    {
        $final = array();
        $key = $this->getKey();
        
        //$final['meta'] = $this->renderMetaInformations( $env );
        $final['rows'] = array();
        
        foreach($this->data as $k => $row) {
            
            $f_row = array();
            $idx = (isset($key))? $row->{$key} : FALSE;
            
            foreach ($this->columns as $j => $column) {
                
                // column render is FALSE if column is not for current env
                $col = $column->render($row);
                if ( FALSE !== $col ) {
                
                    // in json mode the keyed rows arrays should be merged
                    //if ( 'graphic' === $env ) {
                        
                        $f_row[ $column->getLabel() ] = $col;
                        /*if (! is_array($col)) {
                            throw new FormatterException('Column ' . $j . ' is missing the graphic key!');
                        }
                        
                        $f_row = array_merge( $f_row, $col);
                        */
                        
                    /*} else {
                        
                        $f_row[] = $col;
                        
                    }*/
                }
            }
            
            if (FALSE !== $idx) {
                    
                    $final['rows'][$idx] = $f_row;
                    
            } else {

                    $final['rows'][] = $f_row;
                    
            }
        }
        
        return $final;
    }
    
    public function renderDefinition()
    {
        $final = array();
        $key = $this->getKey();
    
        $envs = array('graphic', 'table');
        foreach( $envs as $env ) {
            $final[$env] = $this->renderMetaInformations( $env );
        }
        
        $final['buttons'] = array(
            'next' => t('Next'),
            'prev' => t('Previous'),
            'save' => t('Save'),
        );


        // TODO: remove hard coded things
        
        $final['graphic']['has_tooltip'] = TRUE;

        $final['table']['caption'] = 'Raw data table';
        $final['table']['empty'] = 'TODO';

        return $final;
    }

    public function renderMetaInformations( $env )
    {
        $meta = array();

        if ('table'===$env) {

            $meta['columns'] = array();

            foreach ($this->columns as $j => $column) {
    
                if ($column->validateEnv( $env) ) {
                    
                    $meta['columns'][] = array(
                        'header' => t($column->getTitle()),
                        'key' => $column->getLabel(),
                        'style' => $column->getStyle(),
                    );
                }
    
            }
        }
    
        if ('graphic' == $env) {
            

            $meta['type'] = $this->getGraphType();
            
            $meta['has_y2'] = ( ('2StackedBars1Line' === $meta['type'])
                    || ('1Bar1Line' === $meta['type']) );
            
            $meta['tooltip'] = array(
                    'title' => $this->getTitleColumns(),
                    'content' => $this->getTooltipColumns()
            );

            $meta['axis_x'] = $this->_renderAxis($this->x_axis);

            $meta['axis_y1'] = $this->_renderAxis($this->y1_axis);
            
            if ( $meta['has_y2'] ) {
                  
                $meta['axis_y2'] = $this->_renderAxis($this->y2_axis);
                
            }
            
            $meta['stacked'] = 0;
            if ( '2StackedBars1Line' === $meta['type']) {
                $meta['stacked'] = 1;
                $meta['stacked_layers'] = $this->getStackedColumns();
            }
        }
        
        return $meta;
    }
    
    protected function _renderAxis( $axis_data )
    {
        $axis = array(
            'key' => $axis_data['json_label'],
        );
        
        if (! array_key_exists($axis['key'], $this->idxcolumns)) {
            throw new FormatterException('Unknown column label used in axis.');
        }
        
        $column = $this->idxcolumns[$axis['key']];
        
        $axis['is_1024'] = $column->isBase1024();
        
        $axis['label'] = ($axis_data['has_text']) ? $axis_data['text'] : 'none';

        return $axis;
    }
}
