<?php
namespace BethelChika\Remita;
class RemitaObject{
    /** @var null|array Request options*/
    protected $_opts;


    /** @var array */
    protected $_values;
    public function __construct($id = null, $opts = null)
    {
        
        $this->_opts = $opts;
        
        $this->_values = [];
        
        if (null !== $id) {
            $this->__set('id',$id);
        }
    }

        /**
     * This unfortunately needs to be public to be used in Util\Util.
     *
     * @param array $values
     * @param null|array| $opts
     *
     * @return static the object constructed from the given values
     */
    public static function constructFrom($values, $opts = null)
    {
        $obj = new static(isset($values['id']) ? $values['id'] : null);
        $obj->refreshFrom($values, $opts);

        return $obj;
    }

    /**
     * Refreshes this object using the provided values.
     *
     * @param array $values
     * @param null|array $opts Request options
     */
    public function refreshFrom($values, $opts)
    {
        $this->_opts = $opts;
        
        foreach ($values as $k => $v) {
            $this->_values[$k]=$v;
        }
    }

    /**
     * Refreshes this object using the provided values.
     * @param array $values
     *
     * @return void
     */
    public function refreshValuesFrom($values){
        $this->refreshFrom($values,$this->_opts);
    }

    /**
     * Return the options
     *
     * @return null|array
     */
    public function getOptions(){
        return $this->_opts;
    }

    /**
     * Get the raw values. Use this e.g to see data as received from server
     *
     * @return array
     */
    public function rawValues(){
        return $this->_values;
    }


    /**
     * Set the value of a property
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        /**
         * How we write a value depends on if we have a data entry/property on the 
         * object or not.
         * If we have data, then we write into data.
         */
        if(array_key_exists('data', $this->_values)){
            return $this->_values['data'][$name]=$value;
        }else{// No data, so we write normally
            if (array_key_exists($name, $this->_values)) {
                $this->_values[$name] = $value;
            }
        }
    }

        /**
     * Property assessor
     *
     * @param string $name Property name
     * @return mixed
     */
    public function __get($name)
    {

        /**
         * How we read a value depends on if we have a data entry/property on the 
         * object or not.
         * If we have data, then we read everything from data.
         */
        if(array_key_exists('data', $this->_values)){
            if (array_key_exists($name, $this->_values['data'])) {
                return $this->_values['data'][$name];
            }
        }else{// No data property, so we read normally
            if (array_key_exists($name, $this->_values)) {
                return $this->_values[$name];
            }
        }


        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;
    }
}