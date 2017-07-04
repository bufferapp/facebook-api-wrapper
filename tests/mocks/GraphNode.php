<?php

class GraphNode
{
    private $fields;

    public function __construct($fields)
    {
        $this->fields = $fields;
    }

    public function getField($field)
    {
        return $this->fields[$field];
    }
}
