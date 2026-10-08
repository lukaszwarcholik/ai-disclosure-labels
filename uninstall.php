<?php
/**
 * Cleanup on uninstall. Settings go, image markings go - leaving orphaned
 * meta behind would be rude.
 *
 * @package AiDisclosureLabels
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'aidl_settings' );

global $wpdb;

$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_aidl_is_ai' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_aidl_mode' ) );
