<?php

class Container
{
    public array $definitions = [];
    public array $instances = [];

    public function make(string $className, array $parameters = []): ?object
    {
        $definition = $this->definitions[$className] ?? $this->autowire($className, $parameters);

        return $definition($this);
    }

    public function register(string $name, Closure $definition): self
    {
        $this->definitions[$name] = $definition;

        return $this;
    }

    public function singleton(string $name, Closure $definition): self
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
}