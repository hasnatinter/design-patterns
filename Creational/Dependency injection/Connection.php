<?php

interface Connection
{
    public function __construct();

    public function execute(string $query): bool|array|null;
}