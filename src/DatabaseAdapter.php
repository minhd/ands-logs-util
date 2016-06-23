<?php

namespace MinhD\ANDSLogUtil;

class DatabaseAdapter
{
    protected $mysql = null;

    public function __construct($config)
    {
        $this->mysqli = new mysqli('localhost', 'root', 'root', 'snippets');
    }

    public function search($query)
    {
        $sanitized = $this->mysqli->real_escape_string($query);
        $query = $this->mysqli->query("select * where title like {$sanitized}");

        if (!$query->num_rows) {
            return false;
        }

        while ($row = $query->fetch_object()) {
            $rows[] = $row;
        }

        return $result = [
            'count' => $query->num_rows,
            'result' => $rows
        ];
    }
}
