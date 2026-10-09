<?php
/**
 * Cleanup on uninstall.
 *
 * The settings go: they are plugin configuration and nobody wants them back.
 *
 * The image marks stay, unless the user asked otherwise. A mark is a
 * statement the user made about their own photograph, not plugin
 * bookkeeping - and someone who deactivates the plugin for an afternoon
 * should not come back to three hundred images to tick again. A few
 * hundred rows in postmeta cost nothing; re-marking a media library costs
 * an evening.
 *
 * @package AiDisclosureLabels
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$aidl_ustawienia = get_option( 'aidl_settings', array() );
$aidl_kasowac    = is_array( $aidl_ustawienia ) && ! empty( $aidl_ustawienia['delete_data'] );

delete_option( 'aidl_settings' );

if ( $aidl_kasowac ) {

	global $wpdb;

	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_aidl_is_ai' ) );
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_aidl_mode' ) );
}
