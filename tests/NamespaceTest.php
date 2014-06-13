<?php

use \Vimeo\ABLincoln\Namespaces\SimpleNamespace;
use \Vimeo\ABLincoln\Experiments\AbstractExperiment;
use \Vimeo\ABLincoln\Operators\Random as Random;

/**
 * PHPUnit Namespace test class
 */
class NamespaceTest extends \PHPUnit_Framework_TestCase
{
    public static $log = array();

    public function testVanillaNamespace()
    {
        $userid1 = 3;
        $username1 = 'user1';
        $userid2 = 7;
        $username2 = 'user2';

        $namespace = new TestVanillaNamespace(
            array('userid' => $userid1, 'username' => $username1)
        );
        $foo = $namespace->get('foo');
        $this->assertEquals($foo, 3);
        $this->assertEquals(count(self::$log), 1);

        $namespace = new TestVanillaNamespace(
            array('userid' => $userid2, 'username' => $username2)
        );
        $foo = $namespace->get('foo');
        $this->assertEquals($foo, 'a');
        $this->assertEquals(count(self::$log), 2);

        $namespace->removeExperiment('first');
        $foo = $namespace->get('foo');
        $this->assertNull($foo);
    }
}

class TestVanillaNamespace extends SimpleNamespace
{
    protected function _setup()
    {
        $this->_name = 'namespace_demo';
        $this->_primary_unit = 'userid';
        $this->_num_segments = 1000;
    }

    protected function _setupExperiments()
    {
        $this->addExperiment('first', 'TestVanillaExperiment', 300);
        $this->addExperiment('second', 'TestVanillaExperiment2', 700);
    }
}

class TestVanillaExperiment extends AbstractExperiment
{
    protected function _setup()
    {
        $this->_name = 'test_name';
    }

    protected function _assign($params, $inputs)
    {
        $params['foo'] = new Random\UniformChoice(
            array('choices' => array('a', 'b')),
            $inputs
        );
    }

    protected function _previouslyLogged()
    {
        return false;
    }

    protected function _configureLogger() {}

    protected function _log($data)
    {
        NamespaceTest::$log[] = $data;
    }
}

class TestVanillaExperiment2 extends TestVanillaExperiment
{
    protected function _setup()
    {
        $this->name = 'test2_name';
    }

    protected function _assign($params, $inputs)
    {
        $params['foo'] = new Random\UniformChoice(
            array('choices' => array(1, 2, 3)),
            $inputs
        );
    }
}