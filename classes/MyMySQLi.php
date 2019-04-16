<?php
/**
 * Created by PhpStorm.
 * User: Виталий
 * Date: 18.10.2018
 * Time: 6:33
 */

class MyMySQLi extends mysqli
{
    public function all($table, $order_by_desc = FALSE) {
        $table = $this->real_escape_string($table);
        return $this->query("SELECT * FROM {$table}".($order_by_desc ? " ORDER BY id DESC" : ""))->fetch_all(MYSQLI_ASSOC);
    }

    public function find($table, $value, $key = 'id') {
        $value = $this->real_escape_string($value);
        $table = $this->real_escape_string($table);
        $key = $this->real_escape_string($key);
        $query = "SELECT * FROM {$table} WHERE `{$key}` = '{$value}'";
        $result = $this->query($query);
        if ($result === FALSE || $result->num_rows == 0)
            return FALSE;
        return $result->fetch_assoc();
    }

    public function add($table, array $params) {
        foreach ($params as $key => $value) {
            $params[$key] = "'{$value}'";
        }
        $query = "INSERT INTO $table (".
            implode(", ", array_keys($params)).") VALUES (".implode(", ", $params).")";
        return $this->query($query);
    }

    public function delete($table, $value, $key = 'id') {
        $query = "DELETE FROM $table WHERE $key = '{$value}'";
        return $this->query($query);
    }
}
