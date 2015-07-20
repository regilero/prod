<?php

namespace Drupal\Prod\Output;

use Drupal\Prod\ProdObject;
use Drupal\Prod\Output\Column;
use Drupal\Prod\Error\FormatterException;

/**
 * results Formatter Object
 *
 */
class Formatter extends ProdObject
{

    protected $key_column;
    protected $columns;
    protected $data;
    
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
    
    public function addColumn(Column $column)
    {
        $this->columns[] = $column;
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
    
    public function renderMetaInformations( $env )
    {
        $meta = array();
        if ('table'===$env) {
            
            $meta['headers'] = array();
            foreach ($this->columns as $j => $column) {
                
                $meta['headers'][] = t($column->getTitle());
                
            }
        }
        
        $meta['tooltip'] = array(
            'title' => $this->getTitleColumns(),
            'content' => $this->getTooltipColumns()
        );
        return $meta;
    }
    
    public function render( $env )
    {
        $final = array();
        $key = $this->getKey();
        
        $final['meta'] = $this->renderMetaInformations( $env );
        $final['rows'] = array();
        
        foreach($this->data as $k => $row) {
            
            $f_row = array();
            $idx = (isset($key))? $row->{$key} : FALSE;
            
            foreach ($this->columns as $j => $column) {
                
                // column render is FALSE if column is not for current env
                if ( $col = $column->render($row, $env) ) {
                
                    // in json mode the keyed rows arrays should be merged
                    if ( 'json' === $env ) {
                        
                        $f_row[ $column->getLabel() ] = $col;
                        /*if (! is_array($col)) {
                            throw new FormatterException('Column ' . $j . ' is missing the json key!');
                        }
                        
                        $f_row = array_merge( $f_row, $col);
                        */
                        
                    } else {
                        
                        $f_row[] = $col;
                        
                    }
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
}
