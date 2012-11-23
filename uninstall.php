<?php 
/**
 * Uninstall process
 */
if ( !defined( 'ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') )
    exit();

// Remove options
delete_option('Internal_Link_Manager-maxuse');
delete_option('Internal_Link_Manager-case');
delete_option('Internal_Link_Manager-keywords');
delete_option('Internal_Link_Manager-metabox');
