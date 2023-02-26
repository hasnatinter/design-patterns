<?php

$container = new Container();

$container->register(QueryBuilder::class, fn () => new QueryBuilder(new MySql()));