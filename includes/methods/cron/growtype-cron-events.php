<?php

class Growtype_Cron_Events
{
    public function __construct()
    {
        add_filter('growtype_cron_load_jobs', [$this, 'growtype_cron_load_jobs_callback'], 10);

        add_action('init', array (
            $this,
            'cron_activation'
        ));

        add_action('init', array (
            $this,
            'jobs_activation'
        ));
    }

    function get_scheduled_events()
    {
        return apply_filters('growtype_cron_scheduled_events', []);
    }

    function jobs_activation()
    {
        foreach ($this->get_scheduled_events() as $hook_name => $event) {
            $queue_name = Growtype_Cron_Jobs::format_queue_name($hook_name);
            add_action($hook_name, function () use ($event, $queue_name) {
                growtype_cron_init_job($queue_name, json_encode([]), 10);
            });
        }
    }

    function cron_activation()
    {
        foreach ($this->get_scheduled_events() as $hook_name => $event) {
            if (!wp_next_scheduled($hook_name)) {
                wp_schedule_event(time(), $event['recurrence'], $hook_name);
            }
        }
    }

    function growtype_cron_load_jobs_callback($jobs)
    {
        $new_jobs = [];

        foreach ($this->get_scheduled_events() as $hook_name => $event) {
            $queue_name = Growtype_Cron_Jobs::format_queue_name($hook_name);
            $new_jobs[$queue_name] = [
                'classname' => $event['job_name'],
                'path' => $event['job_path'],
            ];
        }

        $jobs = array_merge($jobs, $new_jobs);

        return $jobs;
    }
}
