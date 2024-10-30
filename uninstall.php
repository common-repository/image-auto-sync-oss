<?php

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

delete_option('iaso_options');
delete_option('iaso_sync_all_history_status');
delete_transient('iaso_sync_all_history_process');