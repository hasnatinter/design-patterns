<?php

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