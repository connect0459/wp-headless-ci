<?php
// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options
delete_option('wp_headless_ci_options');

// If you have created any custom tables, you would delete them here
// global $wpdb;
// $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}your_table_name");
