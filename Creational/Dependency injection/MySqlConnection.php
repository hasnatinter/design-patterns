<?php

class MySqlConnection implements Connection
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