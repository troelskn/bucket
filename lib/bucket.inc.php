<?php
  /**
   * Bucket.
   * Basic di-container for php.
   */

/**
 * Exceptions that are raised if the conatiner can't fulfil a dependency during creation.
 */
class bucket_CreationException extends Exception {}

/**
 * Internally used by `bucket_Container` to hold instances.
 */
class bucket_Scope {
  protected $top;
  protected $instances = array();
  protected $implementations = array();
  function __construct(bucket_Scope $top = null) {
    $this->top = $top;
  }
  function has($classname) {
    return isset($this->instances[$classname]) || ($this->top && $this->top->has($classname));
  }
  function get($classname) {
    return isset($this->instances[$classname])
      ? $this->instances[$classname]
      : ($this->top
         ? $this->top->get($classname)
         : null);
  }
  function set($classname, $instance) {
    return $this->instances[$classname] = $instance;
  }
  function getImplementation($interface) {
    return isset($this->implementations[$interface])
      ? $this->implementations[$interface]
      : ($this->top
         ? $this->top->getImplementation($interface)
         : $interface);
  }
  function setImplementation($interface, $use_class) {
    $this->implementations[$interface] = strtolower($use_class);
  }
}

/**
 * The main container class.
 */
class bucket_Container {
  protected $factory;
  protected $scope;
  function __construct($factory = null, $scope = null) {
    if (is_array($factory)) {
      $this->factory = new StdClass();
      foreach ($factory as $classname => $callback) {
        $this->factory->{'new_' . strtolower($classname)} = $callback;
      }
    } else {
      $this->factory = $factory ? $factory : new StdClass();
    }
    $this->scope = new bucket_Scope($scope);
  }
  /**
   * Clones the container, with a new sub-scope.
   */
  function makeChildContainer() {
    return new self($this->factory, $this->scope);
  }
  /**
   * Gets a shared instance of a class.
   */
  function get($classname) {
    $classname = strtolower($classname);
    if (!$this->scope->has($classname)) {
      $this->scope->set($classname, $this->create($classname));
    }
    return $this->scope->get($classname);
  }
  /**
   * Creates a new (transient) instance of a class.
   */
  function create($classname) {
    $classname = $this->scope->getImplementation($classname);
    if (isset($this->factory->{'new_' . strtolower($classname)})) {
      return call_user_func($this->factory->{'new_'.$classname}, $this);
    }
    if (method_exists($this->factory, 'new_' . $classname)) {
      return $this->factory->{'new_'.$classname}($this);
    }
    return $this->createThroughReflection($classname);
  }
  /**
   * Sets the concrete implementation class to use for an interface/abstract class dependency.
   */
  function registerImplementation($interface, $use_class) {
    $this->scope->setImplementation(strtolower($interface), $use_class);
  }
  /**
   * Explicitly sets the implementation for a concrete class.
   */
  function set($instance, $classname = null) {
    if (!is_object($instance)) {
      throw new Exception("First argument must be an object");
    }
    $classname = $classname ? strtolower($classname) : strtolower(get_class($instance));
    $this->scope->set($classname, $instance);
  }
  protected function createThroughReflection($classname) {
    spl_autoload_call($classname);
    $classname = strtolower($classname);
    $klass = new ReflectionClass($classname);
    if ($klass->isInterface() || $klass->isAbstract()) { // TODO: is this redundant?
      $candidates = array();
      foreach (get_declared_classes() as $klassname) {
        $candidate_klass = new ReflectionClass($klassname);
        if (!$candidate_klass->isInterface() && !$candidate_klass->isAbstract()) {
          if ($candidate_klass->implementsInterface($classname)) {
            $candidates[] = $klassname;
          } elseif ($candidate_klass->isSubclassOf($klass)) {
            $candidates[] = $klassname;
          }
        }
      }
      throw new bucket_CreationException("No implementation registered for '$classname'. Possible candidates are: ". implode(', ', $candidates));
    }
    $dependencies = array();
    $ctor = $klass->getConstructor();
    if ($ctor) {
      foreach ($ctor->getParameters() as $parameter) {
        if (!$parameter->isOptional()) {
          $param_klass = $parameter->getClass();
          if (!$param_klass) {
            throw new bucket_CreationException("Can't auto-assign parameter '" . $parameter->getName() . "' for '" . $klass->getName(). "'");
          }
          $dependencies[] = $this->get($param_klass->getName());
        }
      }
      return $klass->newInstanceArgs($dependencies);
    }
    return $klass->newInstance();
  }
}
