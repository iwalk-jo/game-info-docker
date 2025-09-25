<?php
/**
 * PandaScore Tracker Plugin Uninstall
 *
 * This file is executed when the plugin is deleted through the WordPress admin.
 * It handles cleanup of plugin data and settings.
 */

// cspell:ignore multisite wpdb

// If uninstall not called from WordPress, then exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// WordPress function compatibility checks for IDE support
if ( ! function_exists( 'delete_option' ) ) {
    /**
     * Fallback for delete_option - should never be called in WordPress
     * @param string $option
     * @return bool
     */
    function delete_option( $option ) {
        unset( $option );
        return false;
    }
}

if ( ! function_exists( 'is_multisite' ) ) {
    /**
     * Fallback for is_multisite - should never be called in WordPress
     * @return bool
     */
    function is_multisite() {
        return false;
    }
}

if ( ! function_exists( 'switch_to_blog' ) ) {
    /**
     * Fallback for switch_to_blog - should never be called in WordPress
     * @param int $blog_id
     * @return bool
     */
    function switch_to_blog( $blog_id ) {
        unset( $blog_id );
        return false;
    }
}

if ( ! function_exists( 'restore_current_blog' ) ) {
    /**
     * Fallback for restore_current_blog - should never be called in WordPress
     * @return bool
     */
    function restore_current_blog() {
        return false;
    }
}

if ( ! function_exists( 'wp_cache_flush' ) ) {
    /**
     * Fallback for wp_cache_flush - should never be called in WordPress
     */
    function wp_cache_flush() {
        // Fallback - do nothing
    }
}

// Delete plugin options
delete_option( 'pandascore_tracker_options' );

// For multisite installations, delete options from all sites
if ( is_multisite() ) {
    global $wpdb;

    // Ensure $wpdb is available (fallback for IDE support)
    if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
        // This should never happen in WordPress, but provides IDE support
        return;
    }

    // Get all blog IDs
    if ( method_exists( $wpdb, 'get_col' ) ) {
        $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
    } else {
        $blog_ids = array();
    }

    if ( is_array( $blog_ids ) ) {
        foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            delete_option( 'pandascore_tracker_options' );
            restore_current_blog();
        }
    }
}

// Clear any cached data
wp_cache_flush();

// Note: We don't delete user meta or posts created by users using the plugin
// as that would be destructive to user content.
