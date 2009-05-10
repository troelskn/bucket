Bucket - Basic di-container for php.
==

Bucket is a very minimal, yet useful [di-container](http://www.picocontainer.org/patterns.html) for PHP. It's easy to get started with and offers an open ended migration-path to a more full-featured framework, should you get the need later.

Unlike many other containers, Bucket doesn't have a very intelligent factory. This means **no configuration**, and a very **short learning-path**. It also means that you can use Bucket as a front-end for a more sophisticated di-container.

Bucket is a simple container that delegates creation to an external factory. It has default creational-logic, that relies on typehints+reflection, which is usually adequate for simpler applications. The container completely hides the factory, so as the application complexity grows, you can attach a more sophisticated factory to the container, without having to change your application code.

Basic usage of Bucket
--

Test classes:

    interface Zap {}
    class Foo implements Zap {}
    class Bar {
      function __construct(Foo $foo) {}
    }
    class Cuux {
      function __construct(Zap $zap) {}
    }

To instantiate a class:

    $bucket = new bucket_Container();
    $foo = $bucket->create('Foo');

To get a shared instance of a class:

    $bucket = new bucket_Container();
    $foo = $bucket->get('Foo');

Dependencies
--

Bucket can resolve simple dependencies (concrete-class typehints):

    $bucket = new bucket_Container();
    $bar = $bucket->get('Bar');

Bucket can also resolve interface type-hints, if you specify the implementation to use:

    $bucket = new bucket_Container();
    $bucket->registerImplementation('Zap', 'Foo');
    $bar = $bucket->get('Cuux');

Factories
--

If you need more complicated creational logic, you can attatch a factory to the container:

    class MyFactory {
      function new_PDO($container) {
        return new PDO("mysql:host=localhost;dbname=addressbook", "root", "secret");
      }
    }

    $bucket = new bucket_Container(new MyFactory());
    $db = $bucket->get('pdo');

The container is passed to factories, so it can resolve further dependencies.

Bucket also supports callbacks for factories. This isn't very useful at the moment, but once PHP 5.3 is released, you can use anonymous functions for registering factories:

    // Requires php 5.3+
    $factory = new StdClass();
    $factory->new_pdo = function($container) {
      return new PDO("mysql:host=localhost;dbname=addressbook", "root", "secret");
    }
    $bucket = new bucket_Container($factory);

or:

    // Requires php 5.3+
    $bucket = new bucket_Container(
      array(
        'pdo' => function($container) {
          return new PDO("mysql:host=localhost;dbname=addressbook", "root", "secret");
        }
      )
    );

Scopes
--

Bucket supports nested scopes for fine-tuned management of lifecycles.

    $top = new bucket_Container();
    $scope = $top->makeChildContainer();
    $bar = $scope->get('Cuux');

In the above example, state is maintained on the scoped container `$scope` - not the on `$top`.

Alternatives
==

If Bucket didn't suit your needs, you might prefer one of the following:

* [Twittee](http://github.com/fabpot/twittee/tree/master)
* [sfServiceContainer](http://fabien.potencier.org/article/13/introduction-to-the-symfony-service-container)
* [Phemto](http://phemto.sourceforge.net/)
* [Pico](http://svn.picocontainer.codehaus.org/browse/picocontainer/php/)
* [Stubbles](http://www.stubbles.net/wiki/Docs/IOC)
* [Sphicy](http://www.beberlei.de/sphicy/)
* [Substrate](http://substrate-php.org/)
* [Crafty](http://phpcrafty.sourceforge.net/index.php)
