# Dependency Injection

A dependency injection is a technique where we inject a class dependency from outside. 
Or when we initialise an object we also give it other objects it needs to work with.

There are several benefits of dependency injection but main two benefits are

1- It encourages coding to interface.

2- A class can work with multiple different other classes that implement one interface. So we can test our class without having to worry about other classes it needs to work with.

## Example:

Lets assume a class named QueryBuilder that provides a query builder for databases. 

```php
class QueryBuilder
{
    private string $select = '';
    private string $from = '';
		private MysqlConnection $mysqlConnection;

    public function __construct()
    {
			$this->mysqlConnection = new MysqlConnection();
		}

    public function select(string $select): self
    {
        $this->select = $this->mysqlBuilder->select($select);
        return $this;
    }

    public function from(string $from): self
    {
        $this->from = $this->mysqlBuilder->from($from);
        return $this;
    }

    public function get(): array
    {
        $query = $this->select . $this->from;
				return $this->mysqlBuilder->execute($query);
    }
}
```

And the MySqlConnection will look like this

```php
class MySqlConnection
{
    private mysqli $conn;

    public function __construct()
    {
        $this->conn = new mysqli(
            '127.0.0.1',
            'root',
            '',
            'mydb',
        );
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function execute(string $query): bool|array|null
    {
        $result = $this->conn->query($query);
        return mysqli_fetch_array($result);
    }
}
```

And our class can be consumed in following way

```php
//index.php
$queryBuilder = new QueryBuilder();
$queryBuilder->select('*')->from('users')->get();
```

There is nothing too wrong here if we want our QueryBuilder class to only use MySQL and we know we are not going to change our database. However if we want a generic QueryBuilder class that can work with several databases we can design our class in such a way that consumer of classes decide the connection.

So lets work our way into improving this by few steps

## Step 1: Allow consumer to send database connection type

Lets change our **QueryBuilder** class to accept the database connection. We can do this by accepting the parameter in constructor method.

```php
class QueryBuilder
{
	public function __construct(
      public $connection
  )
  {}
}
```

And the we can use this class now as

```php
//index.php
$queryBuilder = new QueryBuilder(new MySqlConnection());
$queryBuilder->select('*')->from('users')->get();
```

## Step 2: Use a service container to resolve dependencies

In most places where dependency injection is used, we also have a service container which resolves dependencies for us. In short it is globally available and resolves or builds our classes for us. 
Lets see how a service container will look like

```php
class Container
{
    public array $definitions = [];

    public function make(string $className, array $parameters = []): ?object
    {
        $definition = $this->definitions[$className] ?? fn () => null;

        return $definition($this);
    }

    public function register(string $name, Closure $definition): self
    {
        $this->definitions[$name] = $definition;

        return $this;
    }
```

We can register our classes and to access them we can use the make method.

```php
$container = new Container();

$container->register(MySqlConnection::class, fn () => new MySqlBuilder());

$container->register(Query::class, fn(
    Container $container) => new Query(
        $container->make(MySqlConnection::class)
    ));
```

Notice in our we are returning instance of container from make method, to solve nested dependencies, which comes in handy when registering the QueryBuilder class.

Now our dependency container works well. We are building our classes at one place and can manage any change there.

The next problem is in our QueryBuilder class, it accepts a connection and calls its execute method. But what if the execute method is not there. We can say that our design violates the dependency inversion principle because our QueryBuilder class relies on concrete class instead of an abstraction.

> One other problem with not requiring an interface type in QueryBuilder is that our static code analysis will not work properly
> 

## Step 3:  Code to abstraction

3.1 In order to solve this we will first make a new interface of Connection. 

```php
interface Connection
{
    public function execute(string $query): bool|array|null;
}
```

3.2 Then all our concrete database connection classes should implement this interface.

```php
class MySqlConnection implements Connection
```

3.3 Our QueryBuilder class should accept only this type.

```php
class QueryBuilder
{
	public function __construct(
      public Connection $connection
  )
  {}
}
```

3.4 And lastly we will register MySqlConnection in such a way that when a Connection class is required we return an instance of MySqlConnection Class.

```php
$container->register(Connection::class, fn() => new MySqlConnection());

$container->register(Query::class, fn(
    Container $container) => new Query(
    $container->make(Connection::class)
));
```

## Step 4: Add a singleton method to dependency container

Singletons are objects that instantiated only once during a lifecycle and that same instance is returned each time we want to access that class. Our connection classes can be implanted as such. So lets a add a singleton method our container class

```php
public array $instances = [];
public function singleton(string $name): self
{
	$this->register($name, function () use ($name, $definition) {
	  if (array_key_exists($name, $this->instances)) {
	      return $this->instances[$name];
	  }
	  $this->instances[$name] = $definition($this);
	
	  return $this->instances[$name];
	});
	
	return $this;
}
```

Now instead of registering directly we can use our singleton method to register connection class.

```php
//index.php
$container->singleton(Connection::class, fn() => new MySqlConnection());
```

## Step 5: Auto resolve our classes and dependencies without registering them.

Currently we have to register all our classes and dependencies. This cannot work in a real environment since there are many classes and dependencies of those classes that are used. Hence we introduce a new class that will take benefit of PHP class reflection and try to auto resolve a given class name and its dependencies.

```php
public function autowire(string $name, array $parameters): Closure
{
    return function () use ($name, $parameters) {
        $class = new ReflectionClass($name);

        $constructorArguments = $class
            ->getConstructor()
            ->getParameters();

        $dependencies = array_map(
            function(ReflectionParameter $reflectionParameter) use ($parameters) {
                if (array_key_exists($reflectionParameter->getName(), $parameters)) {
                    return $parameters[$reflectionParameter->getName()];
                }
                 return $this->make($reflectionParameter->getType());
            },
            $constructorArguments
        );

        return new $name(...$dependencies);
    };
}
```

Lets make a simple logger class that is also passed into QueryBuilder class.

```php
class ConsoleLogger implements Log
{
    public function __construct()
    {}

    public static function info(string $message)
    {
        print(date("Y-m-d h:i:sa") . " : " . $message);
    }

    public static function warning(string $message)
    {
        print(date("Y-m-d h:i:sa") . " : " . $message);
    }
}
```

Our QueryBuilder class will accept a new constructor

```php
class QueryBuilder
{
    public function __construct(
        public Connection $connection,
        public Log $logger,
    )
    {}
		// ... Rest of the class
		public function get(): array
    {
        $query = $this->select . $this->from;
        $this->logger::info("Executed $query");
        return $this->connection->execute($query);
    }
}
```

This new class can be added using the container→make method even if its not registered. 

```php
// index.php
$container->register(QueryBuilder::class, fn(
    Container $container) => new QueryBuilder(
    $container->make(Connection::class),
    $container->make(ConsoleLogger::class),
));
```