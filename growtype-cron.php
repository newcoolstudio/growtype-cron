<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://growtype.com/
 * @since             1.0.0
 * @package           growtype_cron
 *
 * @wordpress-plugin
 * Plugin Name:       Growtype - Cron
 * Plugin URI:        http://growtype.com/
 * Description:       Advanced Cron features, jobs and schedules.
 * Version:           1.0.0
 * Author:            Growtype
 * Author URI:        http://growtype.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       growtype-cron
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('GROWTYPE_CRON_VERSION', '1.0.0');

/**
 * Plugin text domain
 */
define('GROWTYPE_CRON_TEXT_DOMAIN', 'growtype-cron');

/**
 * Plugin dir path
 */
define('GROWTYPE_CRON_PATH', plugin_dir_path(__FILE__));

/**
 * Plugin url
 */
define('GROWTYPE_CRON_URL', plugin_dir_url(__FILE__));

/**
 * Plugin url public
 */
define('GROWTYPE_CRON_URL_PUBLIC', plugin_dir_url(__FILE__) . 'public/');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-growtype-cron-activator.php
 */
function activate_growtype_cron()
{
    require_once GROWTYPE_CRON_PATH . 'includes/class-growtype-cron-activator.php';
    Growtype_Cron_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-growtype-cron-deactivator.php
 */
function deactivate_growtype_cron()
{
    require_once GROWTYPE_CRON_PATH . 'includes/class-growtype-cron-deactivator.php';
    Growtype_Cron_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_growtype_cron');
register_deactivation_hook(__FILE__, 'deactivate_growtype_cron');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require GROWTYPE_CRON_PATH . 'includes/class-growtype-cron.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_growtype_cron()
{
    $plugin = new Growtype_Cron();
    $plugin->run();
}

run_growtype_cron();
