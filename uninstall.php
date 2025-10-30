<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('wc_dynamic_rules');
// Remove any future options, transients, or DB tables here
