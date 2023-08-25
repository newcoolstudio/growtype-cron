<?php

if (!function_exists('d')) {
    function d($data)
    {
        highlight_string("<?php\n" . var_export($data, true) . ";\n?>");
        die();
    }
}

if (!function_exists('ddd')) {
    function ddd($data)
    {
        return highlight_string("<?php\n" . var_export($data, true) . ";\n?>");
    }
}

/**
 * Include custom view
 */
if (!function_exists('growtype_cron_include_view')) {
    function growtype_cron_include_view($file_path, $variables = array ())
    {
        $fallback_view = GROWTYPE_CRON_PATH . 'resources/views/' . str_replace('.', '/', $file_path) . '.php';
        $child_blade_view = get_stylesheet_directory() . '/views/' . GROWTYPE_CRON_TEXT_DOMAIN . '/' . str_replace('.', '/', $file_path) . '.blade.php';
        $child_view = get_stylesheet_directory() . '/views/' . GROWTYPE_CRON_TEXT_DOMAIN . '/' . str_replace('.', '/', $file_path) . '.php';

        $template_path = $fallback_view;

        if (file_exists($child_blade_view) && function_exists('App\template')) {
            return App\template($child_blade_view, $variables);
        } elseif (file_exists($child_view)) {
            $template_path = $child_view;
        }

        if (file_exists($template_path)) {
            extract($variables);
            ob_start();
            include $template_path;
            $output = ob_get_clean();
        }

        return isset($output) ? $output : '';
    }
}

/**
 * mainly for ajax translations
 */
if (!function_exists('growtype_cron_load_textdomain')) {
    function growtype_cron_load_textdomain($lang)
    {
        global $q_config;

        if (isset($q_config['locale'][$lang])) {
            load_textdomain('growtype-cron', GROWTYPE_CRON_PATH . 'languages/growtype-cron-' . $q_config['locale'][$lang] . '.mo');
        }
    }
}
