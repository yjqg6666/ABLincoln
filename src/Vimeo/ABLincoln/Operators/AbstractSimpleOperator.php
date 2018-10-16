<?php

namespace Vimeo\ABLincoln\Operators;

use Vimeo\ABLincoln\Assignment;

/**
 * Easiest way to implement simple operators. The class automatically evaluates
 * the values of all parameters passed in via execute(), and stores the
 * Assignment object and evaluated parameters as instance variables. The user
 * can then extend AbstractSimpleOperator and implement _simpleExecute().
 */
abstract class AbstractSimpleOperator
{
    protected $parameters;
    protected $assignment;

    private $args;

    /**
     * Store the set of parameters to use as required and optional arguments
     *
     * @param array $options array mapping operator options to values
     * @param mixed $inputs input value/array used for hashing
     */
    public function __construct($options, $inputs)
    {
        $this->args = $options;
        $this->args['unit'] = $inputs;
    }

    /**
     * Evaluate all parameters and store as instance variables, then execute
     * the operator as defined in simpleExecute()
     *
     * @param Assignment $assignment object used to evaluate parameters
     * @return mixed the evaluated expression
     */
    public function execute($assignment)
    {
        $this->assignment = $assignment;
        $this->parameters = [];  // evaluated parameters
        foreach ($this->args as $key => $val) {
            $this->parameters[$key] = $assignment->evaluate($val);
        }
        return $this->_simpleExecute();
    }

    /**
     * Implement with operator functionality
     *
     * @return mixed the evaluated expression
     */
    abstract protected function _simpleExecute();

    /**
      * Argument accessor
      *
      * @return array operator arguments
      */
     public function args()
     {
         return $this->args;
     }

     /**
      * Argument setter
      *
      * @param mixed $key name of argument to set
      * @param mixed $value value to set argument
      */
     public function setArg($key, $value)
     {
         $this->args[$key] = $value;
     }
}
