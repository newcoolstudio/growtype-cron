<?php

class Growtype_Cron_Jobs
{
    const GROWTYPE_CRON_JOBS = 'growtype_cron_jobs';
    const PROCESS_PER_QUEUE_LIMIT = 3;
    const TOTAL_PROCESS_LIMIT = 4;
    const PROCESS_ATTEMPTS_LIMIT = 3;

    private $log_info;

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

        $this->log_info = !empty(getenv('GROWTYPE_CRON_LOG_INFO')) ? getenv('GROWTYPE_CRON_LOG_INFO') : false;
    }

    public static function create($queue_name, $payload, $delay = 5)
    {
        $available_at = date('Y-m-d H:i:s', strtotime(wp_date('Y-m-d H:i:s')) + $delay);

        $existing_jobs = self::specific_jobs($queue_name);

        if (!empty($existing_jobs)) {
            $existing_job = array_reverse($existing_jobs)[0];

            $available_at = date('Y-m-d H:i:s', strtotime($existing_job['available_at']) + $delay);
        }

        $max_length = 50;
        if (strlen($queue_name) > $max_length) {
            $queue_name = substr($queue_name, 0, $max_length);

            error_log(sprintf(
                'Queue name is too long - %s. Trimming to %s characters.',
                $queue_name,
                $max_length
            ));
        }

        $data = [
            'queue' => $queue_name,
            'payload' => $payload,
            'attempts' => 0,
            'reserved_at' => wp_date('Y-m-d H:i:s'),
            'available_at' => $available_at,
            'reserved' => 0
        ];

        $record = Growtype_Cron_Crud::insert_record(Growtype_Cron_Database::JOBS_TABLE, $data);

        return $record;
    }

    public static function create_if_not_exists($cron_event_name, $payload, $delay = 5)
    {
        $job_exists = Growtype_Cron_Jobs::specific_jobs_exists(Growtype_Cron_Jobs::format_queue_name($cron_event_name), $payload);

        if ($job_exists) {
            return false;
        }

        Growtype_Cron_Jobs::create(Growtype_Cron_Jobs::format_queue_name($cron_event_name), $payload, $delay);

        return true;
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

            $job_class->run($job);

            /**
             * Delete job
             */
            if ($this->log_info) {
                error_log('growtype-cron. Delete job. Id: ' . $job['id']);
            }

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
        $schedules['growtype_cron_every_10_seconds'] = array (
            'interval' => 10,
            'display' => __('Once Every 10 seconds')
        );

        $schedules['growtype_cron_every_20_seconds'] = array (
            'interval' => 20,
            'display' => __('Once Every 20 seconds')
        );

        $schedules['growtype_cron_every_30_seconds'] = array (
            'interval' => 30,
            'display' => __('Once Every 30 seconds')
        );

        $schedules['growtype_cron_every_minute'] = array (
            'interval' => 60,
            'display' => __('Once Every Minute')
        );

        $schedules['growtype_cron_every_5_minute'] = array (
            'interval' => 60 * 5,
            'display' => __('Once Every 5 Minute')
        );

        $schedules['growtype_cron_every_10_minute'] = array (
            'interval' => 60 * 10,
            'display' => __('Once Every 10 Minute')
        );

        $schedules['growtype_cron_every_30_minute'] = array (
            'interval' => 60 * 30,
            'display' => __('Once Every 30 Minute')
        );

        $schedules['growtype_cron_every_month'] = array (
            'interval' => 2630000,
            'display' => __('Once Every Month')
        );

        $schedules['growtype_cron_every_3_months'] = array (
            'interval' => 7890000,
            'display' => __('Once Every 3 Months')
        );

        return $schedules;
    }

    function cron_activation()
    {
        if (!wp_next_scheduled(self::GROWTYPE_CRON_JOBS)) {
            wp_schedule_event(time(), 'growtype_cron_every_10_seconds', self::GROWTYPE_CRON_JOBS);
        }
    }

    function process_jobs()
    {
        if ($this->log_info) {
            error_log('---PROCESS JOBS INIT---');
        }

        $jobs_reserved = Growtype_Cron_Crud::get_records(Growtype_Cron_Database::JOBS_TABLE, [
            [
                'key' => 'reserved',
                'value' => 1,
            ]
        ], 'where');

        if (count($jobs_reserved) > self::TOTAL_PROCESS_LIMIT) {
            if ($this->log_info) {
                error_log(sprintf('Total jobs reserved: %s, Total process limit: %s', count($jobs_reserved), self::TOTAL_PROCESS_LIMIT));
            }
            exit();
        }

        $jobs = Growtype_Cron_Crud::get_records(Growtype_Cron_Database::JOBS_TABLE, [
            [
                'key' => 'reserved',
                'value' => 0,
            ],
            [
                'key' => 'attempts',
                'operator' => '<',
                'value' => 3,
            ]
        ], 'where');

        if ($this->log_info) {
            error_log('TOTAL Jobs found ' . count($jobs));
        }

        $already_processed_jobs = [];
        foreach ($jobs as $job) {

            /**
             * Prevent duplicate jobs
             */
            if (in_array($job['id'], $already_processed_jobs)) {
                continue;
            }

            array_push($already_processed_jobs, $job['id']);

            /**
             * Check attempts
             */
            $attempts = isset($job['attempts']) && !empty($job['attempts']) ? (int)$job['attempts'] : 0;
            $job_date = $job['available_at'];

            if ($job_date > wp_date('Y-m-d H:i:s')) {
                if ($this->log_info) {
                    error_log(sprintf('Job not available yet. Job id: %s, Job date: %s, Current date: %s', $job['id'], $job_date, wp_date('Y-m-d H:i:s')));
                }
                continue;
            }

            /**
             * Check if new job is available
             */
            if (!$this->new_job_is_available($job['queue'])) {
                continue;
            }

            /**
             * Limit attempts
             */
            if ((int)$job['attempts'] >= (self::PROCESS_ATTEMPTS_LIMIT > 0 ? self::PROCESS_ATTEMPTS_LIMIT : 1)) {
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

            if ($this->log_info) {
                error_log('Job processing. Init job: ' . json_encode([
                        'job_id' => $job['id'],
                        'job_attempts' => $attempts,
                    ]), 0);
            }

            $this->init_job($job);
        }
    }

    function new_job_is_available($queue)
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
        if ($active_jobs > self::PROCESS_PER_QUEUE_LIMIT) {
            return false;
        }

        return true;
    }

    public static function waiting_in_queue()
    {
        $all_jobss = Growtype_Cron_Crud::get_records(Growtype_Cron_Database::JOBS_TABLE);

        $waiting_jobs = [];
        foreach ($all_jobss as $job) {
            if ((int)$job['attempts'] < self::PROCESS_ATTEMPTS_LIMIT) {
                array_push($waiting_jobs, $job['queue']);
            }
        }

        return $waiting_jobs;
    }

    public static function format_queue_name($cron_event_name)
    {
        return str_replace('growtype-cron-', '', str_replace('_', '-', $cron_event_name));
    }

    public static function specific_jobs($queue, $payload = null)
    {
        $keys = [
            [
                'key' => 'queue',
                'value' => $queue,
            ],
        ];

        if (!empty($payload)) {
            $keys[] = [
                'key' => 'payload',
                'value' => $payload,
            ];
        }

        $jobs = Growtype_Cron_Crud::get_records(Growtype_Cron_Database::JOBS_TABLE, $keys, 'where');

        return $jobs;
    }

    public static function specific_jobs_exists($queue, $payload)
    {
        $jobs_amount = self::specific_jobs_amount($queue, $payload);

        return !empty($jobs_amount) ? true : false;
    }

    public static function specific_jobs_amount($queue, $payload)
    {
        $jobs = self::specific_jobs($queue, $payload);

        return count($jobs);
    }
}
