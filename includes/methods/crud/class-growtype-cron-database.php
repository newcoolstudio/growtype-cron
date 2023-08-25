<?php

class Growtype_Cron_Database
{
    const JOBS_TABLE = 'growtype_cron_jobs';

    const REBUILD_TABLE = false; // IMPORTANT: Set to true to rebuild the database tables

    public function __construct()
    {
        add_action('init', array ($this, 'create_tables'), 5);

        $this->load_methods();
    }

    public static function get_tables()
    {
        global $wpdb;

        return [
            [
                'name' => $wpdb->prefix . self::JOBS_TABLE,
                'fields' => [
                    array (
                        'data_field' => 'queue',
                        'data_type' => 'TEXT DEFAULT NULL',
                    ),
                    array (
                        'data_field' => 'payload',
                        'data_type' => 'TEXT DEFAULT NULL',
                    ),
                    array (
                        'data_field' => 'exception',
                        'data_type' => 'TEXT DEFAULT NULL',
                    ),
                    array (
                        'data_field' => 'attempts',
                        'data_type' => 'INTEGER',
                    ),
                    array (
                        'data_field' => 'reserved',
                        'data_type' => 'INTEGER',
                    ),
                    array (
                        'data_field' => 'reserved_at',
                        'data_type' => 'DATETIME',
                    ),
                    array (
                        'data_field' => 'available_at',
                        'data_type' => 'DATETIME',
                    )
                ]
            ]
        ];
    }

    /**
     * Create required table
     */
    public function create_tables()
    {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $tables = $this->get_tables();

        foreach ($tables as $table) {
            $table_name = $table['name'];

            if (self::REBUILD_TABLE) {
                $wpdb->query("DROP TABLE IF EXISTS $table_name");
            }

            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                $charset_collate = $wpdb->get_charset_collate();

                $sql = "CREATE TABLE IF NOT EXISTS $table_name (
      id bigint(20) NOT NULL AUTO_INCREMENT,";
                foreach ($table['fields'] as $field) {
                    $sql .= $field['data_field'] . ' ' . $field['data_type'] . ', ';
                }
                $sql .= "created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY id (id)
    ) $charset_collate;";

                dbDelta($sql);
            }
        }
    }

    public function load_methods()
    {
        require_once GROWTYPE_CRON_PATH . 'includes/methods/crud/partials/class-growtype-cron-crud.php';
    }
}
