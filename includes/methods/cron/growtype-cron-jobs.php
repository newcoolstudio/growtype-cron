<?php

class Growtype_Cron_Jobs
{
    const JOBS_TABLE = 'wp_growtype_cron_jobs';
    const GROWTYPE_CRON_JOBS = 'growtype_cron_jobs';

    const RETRIEVE_JOBS_LIMIT = 3;
    const JOBS_ATTEMPTS_LIMIT = 3;

    public function __construct()
    {
        add_action(self::GROWTYPE_CRON_JOBS, array ($this, 'process_jobs'));

        add_filter('cron_schedules', array ($this, 'cron_custom_intervals'));

        add_action('wp_loaded', array (
            $this,
            'cron_activation'
        ));

        add_action('wp_loaded', array (
            $this,
            'load_jobs'
        ));
    }

    public static function create($job_name, $payload, $delay = 5)
    {
        $record = Growtype_Cron_Crud::insert_record(Growtype_Cron_Database::JOBS_TABLE, [
            'queue' => $job_name,
            'payload' => $payload,
            'attempts' => 0,
            'reserved_at' => wp_date('Y-m-d H:i:s'),
            'available_at' => date('Y-m-d H:i:s', strtotime(wp_date('Y-m-d H:i:s')) + $delay),
            'reserved' => 0
        ]);

        return $record;
    }

    function init_job($job)
    {
        try {
            $jobs = $this->get_available_jobs();

            if (!isset($jobs[$job['queue']])) {
                throw new Exception('No job class registered');
            }

            /**
             * Run job
             */
            $classname = $jobs[$job['queue']]['classname'];
            $job_class = new $classname();

            $job_class->run(json_decode($job['payload'], true));

            /**
             * Delete job
             */

            error_log('Delete: ' . $job['id']);

            Growtype_Cron_Crud::delete_records(Growtype_Cron_Database::JOBS_TABLE, [$job['id']]);
        } catch (AError|BError $e) {
            Growtype_Cron_Crud::update_record(Growtype_Cron_Database::JOBS_TABLE, [
                'exception' => $e->getMessage(),
                'reserved' => 0
            ], $job['id']);
        } catch (Exception $e) {
            Growtype_Cron_Crud::update_record(Growtype_Cron_Database::JOBS_TABLE, [
                'exception' => $e->getMessage(),
                'reserved' => 0
            ], $job['id']);
        }
    }

    function get_available_jobs()
    {
        return apply_filters('growtype_cron_load_jobs', []);
    }

    /**
     * Load the required traits for this plugin.
     */
    function load_jobs()
    {
        $jobs = $this->get_available_jobs();

        foreach ($jobs as $job_key => $job_class_details) {
            if (isset($job_class_details['path']) && file_exists($job_class_details['path'])) {
                include $job_class_details['path'];
            }
        }
    }

    function cron_custom_intervals()
    {
        $schedules['every10seconds'] = array (
            'interval' => 10,
            'display' => __('Once Every 10 seconds')
        );

        $schedules['every20seconds'] = array (
            'interval' => 20,
            'display' => __('Once Every 20 seconds')
        );

        $schedules['every30seconds'] = array (
            'interval' => 30,
            'display' => __('Once Every 30 seconds')
        );

        $schedules['everyminute'] = array (
            'interval' => 60,
            'display' => __('Once Every Minute')
        );

        $schedules['every5minute'] = array (
            'interval' => 60 * 5,
            'display' => __('Once Every 5 Minute')
        );

        $schedules['every10minute'] = array (
            'interval' => 60 * 10,
            'display' => __('Once Every 10 Minute')
        );

        $schedules['every30minute'] = array (
            'interval' => 60 * 30,
            'display' => __('Once Every 30 Minute')
        );

        return $schedules;
    }

    function cron_activation()
    {
        if (!wp_next_scheduled(self::GROWTYPE_CRON_JOBS)) {
            wp_schedule_event(time(), 'every10seconds', self::GROWTYPE_CRON_JOBS);
        }
    }

    function process_jobs()
    {
        $jobs_reserved = Growtype_Cron_Crud::get_records(Growtype_Cron_Database::JOBS_TABLE, [
            [
                'key' => 'reserved',
                'value' => 1,
            ]
        ], 'where');

        if (!empty($jobs_reserved)) {
            exit();
        }

        $jobs = Growtype_Cron_Crud::get_records(Growtype_Cron_Database::JOBS_TABLE, [
            [
                'key' => 'reserved',
                'value' => 0,
            ]
        ], 'where');

        if (!empty($jobs)) {
            error_log('Jobs left ' . count($jobs));
        }

        foreach ($jobs as $job) {
            $attempts = isset($job['attempts']) && !empty($job['attempts']) ? (int)$job['attempts'] : 0;
            $job_date = $job['available_at'];

            if ($job_date > wp_date('Y-m-d H:i:s')) {
                continue;
            }

            /**
             * Check if new job is available
             */
            if (!$this->new_generate_job_is_available($job['queue'])) {
                continue;
            }

            /**
             * Limit attempts
             */
            if ((int)$job['attempts'] >= (self::JOBS_ATTEMPTS_LIMIT > 0 ? self::JOBS_ATTEMPTS_LIMIT : 1)) {
                continue;
            }

            /**
             * If already reserved, skip
             */
            if ((int)$job['reserved'] > 0) {
                continue;
            }

            Growtype_Cron_Crud::update_record(Growtype_Cron_Database::JOBS_TABLE, [
                'reserved' => 1,
                'attempts' => ($attempts + 1),
            ], $job['id']);

            error_log('Job processing. Init job: ' . json_encode([
                    'job_id' => $job['id'],
                    'job_attempts' => $attempts,
                ]), 0);

            $this->init_job($job);
        }
    }

    function new_generate_job_is_available($queue)
    {
        $retrieve_jobs = Growtype_Cron_Crud::get_records(Growtype_Cron_Database::JOBS_TABLE, [
            [
                'key' => 'queue',
                'values' => [$queue],
            ]
        ]);

        $reserved_jobs = [];
        foreach ($retrieve_jobs as $job) {
            if ($job['reserved']) {
                $reserved_at = strtotime($job['reserved_at']);
                $now = strtotime(wp_date('Y-m-d H:i:s'));

                $minutes_diff = round(abs($now - $reserved_at) / 60, 2);

                /**
                 * If job is reserved for more than 5 minutes, reset it
                 */
                if ($minutes_diff > 5) {
                    Growtype_Cron_Crud::update_record(Growtype_Cron_Database::JOBS_TABLE, [
                        'reserved' => 0
                    ], $job['id']);
                } else {
                    array_push($reserved_jobs, $job['id']);
                }
            }
        }

        $active_jobs = count($reserved_jobs);

        /**
         * Do not generate more than retrieve jobs at the same time
         */
        if ($active_jobs > self::RETRIEVE_JOBS_LIMIT) {
            return false;
        }

        return true;
    }
}
