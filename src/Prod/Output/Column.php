<?php

namespace Drupal\Prod\Output;

use Drupal\Prod\ProdObject;
use Drupal\Prod\Error\FormatterException;

/**
 * results's Column Formatter Object
 *
 */
class Column extends ProdObject
{

    /**
     * Human title
     * @var string
     */
    protected $title;
    
    /**
     * Json label (key)
     * @var string
     */
    protected $label;
    
    /**
     * List of envs unsupported (use to exclude a column from output per env)
     * @var array
     */
    protected $invalid_envs;
    
    /**
     * Column used in tooltip's title
     * @var boolean
     */
    protected $is_in_title = FALSE;
    
    /**
     * Column used in tooltip's content
     * @var boolean
     */
    protected $is_in_tooltip = FALSE;
    
    /**
     * In single column mode (@see $single_column_mode), the name of the input column
     * @var string
     */
    protected $result_column_idx;
    
    /**
     * Is this column using several columns from input?
     * @var unknown
     */
    protected $single_column_mode = FALSE;
    
    /**
     * Ordered list of formats to apply
     * @var array
     */
    protected $format_list = array();
    
    /**
     * Mode used for multiple columns compositions (@see $single_column_mode)
     * @var string
     */
    protected $multiple_function;
    
    /**
     * Operations used for multiple columns compositions (@see $single_column_mode)
     * @var array
     */
    protected $operations = array();
    
    /**
     * HTML style for table cells
     * @var string
     */
    protected $style = '';
    
    /**
     * True for columns containing bytes information
     * @var boolean
     */
    protected $is_base_1024 = FALSE;
    
    public function __construct()
    {

        // load the helpers (like $this->logger)
        $this->initHelpers();

        $this->format_list = array();
        $this->invalid_envs = array();
        return $this;

    }
    
    public function mapColumn($colname)
    {
        $this->result_column_idx = $colname;
        $this->single_column_mode = TRUE;
        return $this;
    }

    public function setTitle( $title )
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Return the column Human Title
     * @return string
     */
    public function getTitle()
    {
        return (!isset( $this->title ))? '' : $this->title;
    }

    public function setStyle( $style )
    {
        $this->style = $style;
        return $this;
    }

    public function getStyle()
    {
        return (!isset( $this->style ))? '' : $this->style;
    }

    /**
     * Set the Is-in-tooltip-title status
     * @param boolean $bool
     */
    public function flagInTitle( $bool ) 
    {
        $this->is_in_title = (int) $bool;
        return $this;
    }
    
    public function isInTitle()
    {
        return $this->is_in_title;
    }
    

    public function flagBase1024( $bool )
    {
        $this->is_base_1024 = (int) $bool;
        return $this;
    }
    

    public function isBase1024()
    {
        return (int) $this->is_base_1024;
    }
    
    /**
     * Set the Is-in-tooltip status
     * @param boolean $bool
     */
    public function flagInTooltip( $bool )
    {

        $this->is_in_tooltip = (int) $bool;
        return $this;
    }
    
    
    public function isInTooltip()
    {
        return $this->is_in_tooltip;
    }
    
    /**
     * Return the column Label (json key)
     * @return string
     * @throws FormatterException
     */
    public function getLabel()
    {
        if (!isset( $this->label )) {
            throw new FormatterException('Column has no label');
        }
        return $this->label;
    }

    public function setLabel( $label)
    {
        $this->label = $label;
        return $this;
    }
    
    public function addInvalidEnv( $env )
    {
        $this->invalid_envs[$env] = TRUE;
        return $this;
    }
    
    public function addFormat($format, $filter_format=NULL)
    {
        if (!isset($filter_format)) {
            
            $this->format_list[] = $format;
            
        } else {
            
            $format_def = array(
                'format' => $format,
                'filter' => $filter_format
            );
            
            $this->format_list[] = $format_def;
            
        }
        return $this;
    }
    
    public function setmultipleMap( $def )
    {
        $this->single_column_mode = FALSE;
        
        if (!array_key_exists( 'type', $def)) {
            throw new FormatterException(__METHOD__ . ' type of multiple map definition is required');
        }
        switch( $def['type'] ) {
            case 'concat':
                $this->multiple_function = 'concat';
                break;
            default:
                throw new FormatterException(__METHOD__ . ' Unknown type of multiple field main operation: ' . $def['type'] );
        }
        
        $this->operations = array();
        
        foreach ($def as $key => $value ) {
            if ('type' !== $key ) {
                switch( $value ) {
                    case 'field':
                        $this->operations[] = array('type' => 'field', 'value' => $key);
                        break;
                    case 'str':
                        $this->operations[] = array('type' => 'str', 'value' => $key);
                        break;
                    default:
                        throw new FormatterException(__METHOD__ . ' Unknown type of multiple field operation: ' . $key );
                }
            }
        }
        
        return $this;
    }
    
    public function validateEnv($env) {
        return (! array_key_exists($env , $this->invalid_envs));
    }
    
    public function render( $data )
    {
       // if ( ! $this->validateEnv($env)) return FALSE;
        
        $out = '';
        
        if ( $this->single_column_mode ) {
            
            $out = $data->{$this->result_column_idx};
            
        } else {
            
            if ('concat' == $this->multiple_function) {
                $tmp = '';
                foreach ($this->operations as $i => $op_record) {
                    
                    if ( 'field' == $op_record['type'] ) {
                        $tmp .= $data->{$op_record['value']};
                    }

                    if ( 'str' == $op_record['type'] ) {
                        $tmp .= $op_record['value'];
                    }
                }
            }
            
            $out = $tmp;
            
        }

        // final Format functions
        $unit = '';
        $key = '';
        foreach ($this->format_list as $k => $format) {
            
            if (is_array($format)){
                
                // filtered formats, depends on the real final output env
                if ( $env === $format['filter'] ) {
                    
                    $format = $format['format'];
                    
                } else {
                    
                    //ignore this filter, goes to next
                    continue;
                }
                
            }

            
            switch($format) {

                case 'check_plain':
                    $out = check_plain($out);
                    break;

                case 'human_int':
                    $out = number_format($out, 0);
                    break;
                    

                case 'human_bytes':
                    $out = format_size($out);
                    break;

                case 'id':
                    // TODO: link?
                    $out = '*' . $out . '*';
                    break;

                case '3_dec':
                    $out = round($out, 3);
                    break;

                case '2_dec':
                    $out = round($out, 2);
                    break;

                case 'undo_factor100':
                    $out = $out / 100;
                    break;

                case 'interval':
                    $out = format_interval(REQUEST_TIME - $out, 2);
                    break;

                default:
                    throw new FormatterException(__METHOD__ . ' Unknown format filter: ' . $format );
            }
        }
        
        if (!empty($unit)) {
            $out = $out. '&nbsp;' . $unit;
        }
        
        /*if ( 'table' == $env ) {
            
            $cell = array(
                'data' => $out
            );
            if ( ! empty($style)) {
                $cell['style'] = $style;
            }

            return $cell;
        }*/
        
        return $out;
    }
}
