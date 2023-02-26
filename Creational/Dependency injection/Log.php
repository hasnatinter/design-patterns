<?php

interface Log
{
    public function __construct();

    public static function info(string $message);

    public static function warning(string $message);
}