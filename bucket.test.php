<?php
require_once 'simpletest/unit_tester.php';
if (realpath($_SERVER['PHP_SELF']) == __FILE__) {
  error_reporting(E_ALL | E_STRICT);
  require_once 'simpletest/autorun.php';
}

require_once 'lib/bucket.inc.php';

class NoDependencies {}
class ExtendsNoDependencies extends NoDependencies {}

class SingleClassDependency {
  public $val;
  function __construct(NoDependencies $val) {
    $this->val = $val;
  }
}

class DefaultValue {
  public $val;
  function __construct($val = 42) {
    $this->val = $val;
  }
}

class UnTypedDependency {
  public $val;
  function __construct($val) {
    $this->val = $val;
  }
}

interface AnInterface {};

class ConcreteImplementation implements AnInterface {}

class DependsOnInterface {
  public $val;
  function __construct(AnInterface $val) {
    $this->val = $val;
  }
}

class TestFactory {
  public $invoked = false;
  function new_NoDependencies($container) {
    $this->invoked = true;
    return new NoDependencies();
  }
  function new_ConcreteImplementation($container) {
    $this->invoked = true;
    return new NoDependencies();
  }
}

class RequireUndefinedClass {
  function __construct(ClassThatDoesntExist $autoloaded) {}
}

class TriedToAutoloadException extends Exception {
  public $classname;
  function __construct($classname) {
    $this->classname = $classname;
    parent::__construct();
  }
}

function test_autoload_fail($classname) {
  throw new TriedToAutoloadException($classname);
}

class TestOfBucketAutoload extends UnitTestCase {
  function setUp() {
    $this->spl_autoload_functions = spl_autoload_functions();
    if ($this->spl_autoload_functions) {
      foreach ($this->spl_autoload_functions as $fn) {
        spl_autoload_unregister($fn);
      }
    }
  }
  function tearDown() {
    if (spl_autoload_functions()) {
      foreach (spl_autoload_functions() as $fn) {
        spl_autoload_unregister($fn);
      }
    }
    if ($this->spl_autoload_functions) {
      foreach ($this->spl_autoload_functions as $fn) {
        spl_autoload_register($fn);
      }
    }
  }
  function test_undefined_class_triggers_autoload() {
    spl_autoload_register('test_autoload_fail');
    $bucket = new bucket_Container();
    $this->expectException('TriedToAutoloadException');
    $bucket->create('RequireUndefinedClass');
  }
  function test_autoload_gets_canonical_classname() {
    spl_autoload_register('test_autoload_fail');
    $bucket = new bucket_Container();
    try {
      $bucket->create('RequireUndefinedClass');
      $this->fail("Expected TriedToAutoloadException");
    } catch (TriedToAutoloadException $ex) {
      $this->assertEqual($ex->classname, 'RequireUndefinedClass');
    }
  }
}

class TestOfBucketResolving extends UnitTestCase {
  function test_can_create_empty_container() {
    $bucket = new bucket_Container();
  }
  function test_can_create_class_with_no_dependencies() {
    $bucket = new bucket_Container();
    $this->assertIsA($bucket->create('NoDependencies'), 'NoDependencies');
  }
  function test_can_create_class_with_class_dependency() {
    $bucket = new bucket_Container();
    $o = $bucket->create('SingleClassDependency');
    $this->assertIsA($o, 'SingleClassDependency');
    $this->assertIsA($o->val, 'NoDependencies');
  }
  function test_can_create_class_with_default_value() {
    $bucket = new bucket_Container();
    $o = $bucket->create('DefaultValue');
    $this->assertIsA($o, 'DefaultValue');
    $this->assertEqual($o->val, 42);
  }
  function test_barks_on_untyped_dependency() {
    $bucket = new bucket_Container();
    try {
      $bucket->create('UnTypedDependency');
      $this->fail("Expected exception");
    } catch (bucket_CreationException $ex) {
      $this->pass("Exception caught");
    }
  }
  function test_barks_on_interface_dependency_when_unregistered() {
    $bucket = new bucket_Container();
    try {
      $bucket->create('DependsOnInterface');
      $this->fail("Expected exception");
    } catch (bucket_CreationException $ex) {
      $this->pass("Exception caught");
    }
  }
  function test_can_create_class_with_interface_dependency() {
    $bucket = new bucket_Container();
    $bucket->registerImplementation('AnInterface', 'ConcreteImplementation');
    $o = $bucket->create('DependsOnInterface');
    $this->assertIsA($o, 'DependsOnInterface');
    $this->assertIsA($o->val, 'ConcreteImplementation');
  }
  function test_can_set_different_implementation_for_concrete_class() {
    $bucket = new bucket_Container();
    $bucket->registerImplementation('NoDependencies', 'ExtendsNoDependencies');
    $o = $bucket->create('SingleClassDependency');
    $this->assertIsA($o, 'SingleClassDependency');
    $this->assertIsA($o->val, 'ExtendsNoDependencies');
  }
}

class TestOfBucketContainer extends UnitTestCase {
  function test_get_creates_new_object() {
    $bucket = new bucket_Container();
    $this->assertIsA($bucket->get('NoDependencies'), 'NoDependencies');
  }
  function test_get_returns_same_instance_on_subsequent_calls() {
    $bucket = new bucket_Container();
    $this->assertSame(
      $bucket->get('NoDependencies'),
      $bucket->get('NoDependencies'));
  }
}

class TestOfBucketFactory extends UnitTestCase {
  function test_container_delegates_to_factory_method() {
    $factory = new TestFactory();
    $bucket = new bucket_Container($factory);
    $this->assertIsA($bucket->get('NoDependencies'), 'NoDependencies');
    $this->assertTrue($factory->invoked);
  }
  function test_container_can_return_different_implementation() {
    $bucket = new bucket_Container(new TestFactory());
    $this->assertIsA($bucket->get('ConcreteImplementation'), 'NoDependencies');
  }
  function test_container_delegates_to_factory_callback() {
    $factory = new TestFactory();
    $factory->new_defaultvalue = create_function('', 'return new StdClass();');
    // For PHP 5.3+
    // $factory->new_defaultvalue = function($container) {
    //   return new StdClass();
    // }
    $bucket = new bucket_Container($factory);
    $this->assertIsA($bucket->get('DefaultValue'), 'StdClass');
  }
  function test_callback_takes_precedence_over_method() {
    $factory = new TestFactory();
    $factory->new_nodependencies = create_function('', 'return new StdClass();');
    // For PHP 5.3+
    // $factory->new_nodependencies = function($container) {
    //   return new StdClass();
    // }
    $bucket = new bucket_Container($factory);
    $this->assertIsA($bucket->get('NoDependencies'), 'StdClass');
  }
  function test_container_can_take_array_of_callbacks_as_argument() {
    $bucket = new bucket_Container(
      array(
        'DefaultValue' => create_function('', 'return new StdClass();')
      )
    );
    // For PHP 5.3+
    // $bucket = new bucket_Container(
    //   array(
    //     'DefaultValue' => function($container) {
    //       return new StdClass();
    //     }
    //   )
    // );
    $this->assertIsA($bucket->get('DefaultValue'), 'StdClass');
  }
}

class TestOfBucketScope extends UnitTestCase {
  function test_a_child_scope_uses_parent_factory() {
    $factory = new TestFactory();
    $bucket = new bucket_Container($factory);
    $scope = $bucket->makeChildContainer();
    $this->assertIsA($scope->get('NoDependencies'), 'NoDependencies');
    $this->assertTrue($factory->invoked);
  }
  function test_get_on_a_child_scope_returns_same_instance_on_subsequent_calls() {
    $factory = new TestFactory();
    $bucket = new bucket_Container($factory);
    $scope = $bucket->makeChildContainer();
    $this->assertSame(
      $scope->get('NoDependencies'),
      $scope->get('NoDependencies'));
  }
  function test_get_on_a_child_scope_returns_parent_state() {
    $factory = new TestFactory();
    $bucket = new bucket_Container($factory);
    $scope = $bucket->makeChildContainer();
    $o = $bucket->get('NoDependencies');
    $this->assertSame(
      $o,
      $scope->get('NoDependencies'));
  }
  function test_parent_scope_doesnt_see_child_state() {
    $factory = new TestFactory();
    $bucket = new bucket_Container($factory);
    $scope = $bucket->makeChildContainer();
    $o = $scope->get('NoDependencies');
    $this->assertFalse($o === $bucket->get('NoDependencies'));
  }
}