<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Growtype_Cron
 * @subpackage growtype_cron/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Growtype_Cron
 * @subpackage growtype_cron/admin
 * @author     Your Name <email@example.com>
 */
class Growtype_Cron_Admin
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $growtype_cron The ID of this plugin.
     */
    private $growtype_cron;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    /**
     * Traits
     */

    /**
     * Initialize the class and set its properties.
     *
     * @param string $growtype_cron The name of this plugin.
     * @param string $version The version of this plugin.
     * @since    1.0.0
     */
    public function __construct($growtype_cron, $version)
    {
        $this->growtype_cron = $growtype_cron;
        $this->version = $version;

        if (is_admin()) {
            /**
             * Load methods
             */
            add_action('init', array ($this, 'add_pages'));
        }
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style($this->growtype_cron, plugin_dir_url(__FILE__) . 'css/growtype-cron-admin.css', array (), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script($this->growtype_cron, plugin_dir_url(__FILE__) . 'js/growtype-cron-admin.js', array ('jquery'), $this->version, false);
    }

    /**
     * Load the required methods for this plugin.
     *
     */
    public function add_pages()
    {
    }
}
