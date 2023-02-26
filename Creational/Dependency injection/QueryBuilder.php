<?php

class QueryBuilder
{
    private string $select = '';
    private string $from = '';

    public function __construct(
        public Connection $connection,
        public Log $logger,
    )
    {}

    public function select(string $select): self
    {
        $this->select = "select $select";
        return $this;
    }

    public function from(string $from): self
    {
        $this->from = " from $from";
        return $this;
    }

    public function get(): array
    {
        $query = $this->select . $this->from;
        $this->logger::info("Executed $query");
        return $this->connection->execute($query);
    }
}