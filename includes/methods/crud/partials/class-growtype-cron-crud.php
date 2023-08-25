<?php

class Growtype_Cron_Crud
{
    public static function get_single_record($table, $params)
    {
        return !empty(self::get_records($table, $params)) ? self::get_records($table, $params)[0] : null;
    }

    public static function get_records($table, $params = null, $condition = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . $table;

        if (empty($params)) {
            return $wpdb->get_results("SELECT * FROM " . $table, ARRAY_A);
        }

        $records = [];

        if (!empty($condition) && $condition === 'where') {
            $query_where = [];

            foreach ($params as $param) {
                array_push($query_where, $param['key'] . "='" . $param['value'] . "'");
            }

            $query = "SELECT * FROM " . $table . " where " . implode(' AND ', $query_where);

            $records = $wpdb->get_results($query, ARRAY_A);
        } else {
            foreach ($params as $param) {
                $limit = isset($param['limit']) ? $param['limit'] : 1000;
                $offset = isset($param['offset']) ? $param['offset'] : 0;
                $search = isset($param['search']) ? $param['search'] : null;
                $orderby = isset($param['orderby']) ? $param['orderby'] : 'created_at';
                $order = isset($param['order']) ? $param['order'] : 'desc';
                $values = isset($param['values']) ? $param['values'] : null;
                $key = isset($param['key']) ? $param['key'] : null;

                if (!empty($values) && !empty($key)) {
                    $placeholders = implode(', ', array_fill(0, count($values), '%s'));
                    $query = "SELECT * FROM " . $table . " WHERE " . $key . " IN($placeholders)";
                    $query = $wpdb->prepare($query, $values);
                } elseif (!empty($search)) {
                    $query = "SELECT * from {$table} WHERE id Like '%{$search}%' OR prompt Like '%{$search}%' OR negative_prompt Like '%{$search}%' OR reference_id Like '%{$search}%' ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";
                } else {
                    $query = "SELECT * from {$table} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";
                }

                $results = $wpdb->get_results($query, ARRAY_A);

                $records = array_merge($records, $results);
            }
        }

        return $records;
    }

    public static function get_pivot_records($pivot_table, $records_table, $source, $params = null)
    {
        $records = self::get_records($pivot_table, $params);

        if (empty($records)) {
            return [];
        }

        $ids = array_pluck($records, $source);

        return self::get_records($records_table, [
            [
                'key' => 'id',
                'values' => $ids,
            ]
        ]);
    }

    public static function insert_record($table, $data)
    {
        global $wpdb;

        $table = $wpdb->prefix . $table;

        $wpdb->insert($table, $data);

        return $wpdb->insert_id;
    }

    public static function update_record($table, $data, $id)
    {
        global $wpdb;

        $table = $wpdb->prefix . $table;

        $wpdb->update($table, $data, array ('id' => $id));
    }

    public static function update_records($table, $retrieve_data, $record_params, $update_data)
    {
        $records = self::get_records($table, $retrieve_data);

        foreach ($records as $record) {
            $record_key = $record[$record_params['reference_key']];
            if (isset($update_data[$record_key])) {
                $update_value = $update_data[$record_key];
                self::update_record($table, [$record_params['update_value'] => $update_value], $record['id']);
            }
        }

        foreach ($update_data as $key => $value) {
            if (!in_array($key, array_pluck($records, $record_params['reference_key']))) {
                self::insert_record($table, [
                    $retrieve_data[0]['key'] => $retrieve_data[0]['values'][0],
                    $record_params['reference_key'] => $key,
                    $record_params['update_value'] => $value
                ]);
            }
        }
    }

    public static function delete_records($table_name, $ids)
    {
        global $wpdb;

        if (empty($ids)) {
            return;
        }

        $table = $wpdb->prefix . $table_name;

        $ids = implode(',', array_map('absint', $ids));

        $wpdb->query("DELETE FROM " . $table . " WHERE ID IN($ids)");
    }

    public static function delete_single_record($table_name, $params)
    {
        global $wpdb;

        $table = $wpdb->prefix . $table_name;

        $query_where = [];
        foreach ($params as $param) {
            array_push($query_where, $param['key'] . "='" . $param['value'] . "'");
        }

        $query_where = "DELETE FROM " . $table . " where " . implode(' AND ', $query_where);

        $wpdb->query($query_where);
    }
}
