<?php
include 'autoload.php';
include 'initialize.php';

$container = new Container();

$container->singleton(Connection::class, fn() => new MySqlConnection());

$container->register(QueryBuilder::class, fn(
    Container $container) => new QueryBuilder(
    $container->make(Connection::class),
    // not registered but will be sorted using autowiring
    $container->make(ConsoleLogger::class),
));

$queryClass = $container->make(QueryBuilder::class);
var_dump($queryClass->select('*')->from('users')->get());

// a new instance of query builder will be returned but instance of conneciton will be same
$queryClass = $container->make(QueryBuilder::class);
var_dump($queryClass->select('name')->from('users')->get());
