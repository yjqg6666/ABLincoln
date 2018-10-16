<?php

namespace Vimeo\ABLincoln\Namespaces;

use \Vimeo\ABLincoln\Assignment;
use Vimeo\ABLincoln\Experiments\AbstractExperiment;
use \Vimeo\ABLincoln\Operators\Random as Random;

/**
 * Simple namespace base class that handles user assignment when dealing with
 * multiple concurrent experiments.
 */
abstract class SimpleNamespace extends AbstractNamespace
{
    protected $name;
    protected $inputs;
    protected $primary_unit;
    protected $num_segments;

    /**
     * @var AbstractExperiment
     */
    private $experiment = null;

    /**
     * @var AbstractExperiment
     */
    private $default_experiment = null;

    /**
     * @var string
     */
    private $default_experiment_class;
    /**
     * @var bool
     */
    private $in_experiment;

    /**
     * @var string[]
     */
    private $current_experiments;

    private $available_segments;
    private $segment_allocations;

    /**
     * Set up attributes needed for the namespace
     *
     * @param array $inputs data to determine parameter assignments, e.g. userid
     */
    public function __construct($inputs)
    {
        $this->inputs = $inputs;         // input data
        $this->name = get_class($this);  // use class name as default name
        $this->num_segments = null;      // num_segments set in setup()
        $this->in_experiment = false;    // not in experiment until unit assigned

        // array mapping segments to experiment names
        $this->segment_allocations = [];

        // array mapping experiment names to experiment objects
        $this->current_experiments = [];

        $this->experiment = null;          // memoized experiment object
        $this->default_experiment = null;  // memoized default experiment object
        $this->default_experiment_class = 'Vimeo\ABLincoln\Experiments\DefaultExperiment';

        // setup name, primary key, number of segments, etc
        $this->setup();
        $this->available_segments = range(0, $this->num_segments - 1);

        $this->setupExperiments();  // load namespace with experiments
    }

    /**
     * Set namespace attributes for run. Developers extending this class should
     * set the following variables:
     *     this->name = 'sample namespace';
     *     this->primary_unit = 'userid';
     *     this->num_segments = 10000;
     */
    abstract public function setup();

    /**
     * Setup experiments segments will be assigned to:
     *     $this->addExperiment('first experiment', Exp1, 100);
     */
    abstract public function setupExperiments();

    /**
     * Get the primary unit that will be mapped to segments
     *
     * @return array array containing value(s) used for unit assignment
     */
    public function primaryUnit()
    {
        return $this->primary_unit;
    }

    /**
     * Set the primary unit that will be used to map to segments
     *
     * @param mixed $value value or array used for unit assignment to segments
     */
    public function setPrimaryUnit($value)
    {
        $this->primary_unit = is_array($value) ? $value : [$value];
    }

    /**
     * In-experiment accessor
     *
     * @return boolean true if primary unit mapped to an experiment, false otherwise
     */
    public function inExperiment()
    {
        $this->_requiresExperiment();
        return $this->in_experiment;
    }

    /**
     * Map a new experiment to a given number of segments in the namespace
     *
     * @param string $name name to give the new experiment
     * @param string $exp_class string version of experiment class to instantiate
     * @param int $num_segments number of segments to allocate to experiment
     * @throws \Exception when invalid argument given
     */
    public function addExperiment($name, $exp_class, $num_segments)
    {
        $num_available = count($this->available_segments);
        if ($num_available < $num_segments) {
            throw new \Exception(get_class($this) . ": $num_segments requested, only $num_available available.");
        }
        if (array_key_exists($name, $this->current_experiments)) {
            throw new \Exception(get_class($this) . ": there is already an experiment called $name.");
        }
        if (!is_null($this->experiment)) {
            throw new \Exception(get_class($this) . ': addExperiment() cannot be called after an assignment is made.');
        }

        // randomly select the given numer of segments from all available options
        $assignment = new Assignment($this->name);
        $assignment->sampled_segments = new Random\Sample(
            ['choices' => $this->available_segments, 'draws' => $num_segments],
            ['unit' => $name]
        );

        // assign each segment to the experiment name
        foreach ($assignment->sampled_segments as $key => $segment) {
            $this->segment_allocations[$segment] = $name;
            unset($this->available_segments[$segment]);
        }

        // associate the experiment name with a class to instantiate
        $this->current_experiments[$name] = $exp_class;
    }

    /**
     * Remove a given experiment from the namespace and free its associated segments
     *
     * @param string $name previously defined name of experiment to remove
     * @throws \Exception when invalid argument given
     */
    public function removeExperiment($name)
    {
        if (!array_key_exists($name, $this->current_experiments)) {
            throw new \Exception(get_class($this) . ": there is no experiment called $name.");
        }
        if (!is_null($this->experiment)) {
            throw new \Exception(get_class($this) . ': removeExperiment() cannot be called after an assignment is made.');
        }

        // make segments available for allocation again, remove experiment name
        foreach ($this->segment_allocations as $segment => $exp_name) {
            if ($exp_name === $name) {
                unset($this->segment_allocations[$segment]);
                $this->available_segments[$segment] = $segment;
            }
        }
        unset($this->current_experiments[$name]);
    }

    /**
     * Use the primary unit value(s) to obtain a segment and associated experiment
     *
     * @return int the segment corresponding to the primary unit value(s)
     */
    private function _getSegment()
    {
        $assignment = new Assignment($this->name);
        $assignment->segment = new Random\RandomInteger(
            ['min' => 0, 'max' => $this->num_segments - 1],
            ['unit' => $this->inputs[$this->primary_unit]]
        );
        return $assignment->segment;
    }

    /**
     * Checks if primary unit segment is assigned to an experiment,
     * and if not assigns it to one
     */
    protected function _requiresExperiment()
    {
        if (is_null($this->experiment)) {
            $this->_assignExperiment();
        }
    }

    /**
     * Checks if primary unit segment is assigned to a default experiment,
     * and if not assigns it to one
     */
    protected function _requiresDefaultExperiment()
    {
        if (is_null($this->default_experiment)) {
            $this->_assignDefaultExperiment();
        }
    }

    /**
     * Assigns the primary unit value(s) and associated segment to a new
     * experiment and updates the experiment name/salt accordingly
     */
    private function _assignExperiment()
    {
        $this->in_experiment = false;
        $segment = $this->_getSegment();

        // is the unit allocated to an experiment?
        if (array_key_exists($segment, $this->segment_allocations)) {
            $exp_name = $this->segment_allocations[$segment];
            $experiment = new $this->current_experiments[$exp_name]($this->inputs);
            /** @noinspection PhpUndefinedMethodInspection */
            $experiment->setName($this->name . '-' . $exp_name);
            /** @noinspection PhpUndefinedMethodInspection */
            $experiment->setSalt($this->name . '.' . $exp_name);
            $this->experiment = $experiment;
            /** @noinspection PhpUndefinedMethodInspection */
            $this->in_experiment = $experiment->inExperiment();
        }

        if (!$this->in_experiment) {
            $this->_assignDefaultExperiment();
        }
    }

    /**
     * Assigns the primary unit value(s) and associated segment to a new
     * default experiment used if segment not assigned to a real one
     */
    private function _assignDefaultExperiment()
    {
        $this->default_experiment = new $this->default_experiment_class($this->inputs);
    }

    /**
     * Get the value of a given experiment parameter - triggers exposure log
     *
     * @param string $name parameter to get the value of
     * @param string $default optional value to return if parameter undefined
     * @return mixed the value of the given parameter
     */
    public function get($name, $default = null)
    {
        $this->_requiresExperiment();
        if (is_null($this->experiment)) {
            return $this->_defaultGet($name, $default);
        }
        return $this->experiment->get($name, $this->_defaultGet($name, $default));
    }

    /**
     * Get the value of a given default experiment parameter. Called on get()
     * if primary unit value(s) not mapped to a real experiment
     *
     * @param string $name parameter to get the value of
     * @param string $default optional value to return if parameter undefined
     * @return mixed the value of the given parameter
     */
    private function _defaultGet($name, $default = null)
    {
        $this->_requiresDefaultExperiment();
        return $this->default_experiment->get($name, $default);
    }

    /**
     * Disables / enables auto exposure logging (enabled by default)
     *
     * @param boolean $value true to enable, false to disable
     */
    public function setAutoExposureLogging($value)
    {
        $this->_requiresExperiment();
        if (!is_null($this->experiment)) {
            $this->experiment->setAutoExposureLogging($value);
        }
    }

    /**
     * Logs exposure to treatment
     *
     * @param array $extras optional extra data to include in exposure log
     */
    public function logExposure($extras = null)
    {
        $this->_requiresExperiment();
        if (!is_null($this->experiment)) {
            $this->experiment->logExposure($extras);
        }
    }

    /**
     * Log an arbitrary event
     *
     * @param string $event_type name of event to kig]
     * @param array $extras optional extra data to include in log
     */
    public function logEvent($event_type, $extras = null)
    {
        $this->_requiresExperiment();
        if (!is_null($this->experiment)) {
            $this->experiment->logEvent($event_type, $extras);
        }
    }
}
