<?php
/**
 * Catching images that no WordPress filter can reach.
 *
 * A theme that prints <img src="<?php echo $pole['url']; ?>"> straight from
 * an ACF field, a page builder that renders its own markup, a slider with a
 * hand-written template - none of it passes through wp_get_attachment_image()
 * or through a block, so there is nothing to hook.
 *
 * The only remaining place to act is the finished HTML. The naive way to do
 * that is to parse every <img> on the page and ask the database which
 * attachment each src belongs to - a query per image, and it still fails on
 * every CDN rewrite and every resized file.
 *
 * This does it the other way round: we already know which attachments are
 * marked, which is normally a handful. So we build a filename map for those
 * few files only (original plus every registered size), cache it, and look
 * for exactly those filenames in the output. Nothing to resolve, no queries
 * per image, and because the match is on the file name the domain in front
 * of it is irrelevant - a CDN or an offloaded upload path still matches.
 *
 * @package AiDisclosureLabels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * file name => attachment ID, for marked attachments only.
 *
 * @return array
 */
function aidl_map() {

	$map = get_transient( 'aidl_map' );

	if ( is_array( $map ) ) {
		return $map;
	}

	$ids = get_posts(
		array(
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'posts_per_page'         => 500,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_key'               => AIDL_META,
			'meta_value'             => '1',
		)
	);

	$map = array();

	foreach ( $ids as $id ) {

		$plik = get_post_meta( $id, '_wp_attached_file', true );

		if ( ! $plik ) {
			continue;
		}

		$map[ wp_basename( $plik ) ] = (int) $id;

		$meta = wp_get_attachment_metadata( $id );

		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $rozmiar ) {
				if ( ! empty( $rozmiar['file'] ) ) {
					$map[ $rozmiar['file'] ] = (int) $id;
				}
			}
		}
	}

	set_transient( 'aidl_map', $map, DAY_IN_SECONDS );

	return $map;
}

/**
 * Drop the cached map whenever a mark changes or a file is regenerated.
 */
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
	add_action( $hook, 'aidl_flush_map_meta', 10, 3 );
}

function aidl_flush_map_meta( $meta_id, $object_id, $meta_key ) {

	if ( AIDL_META === $meta_key ) {
		delete_transient( 'aidl_map' );
	}
}

add_action( 'delete_attachment', 'aidl_flush_map' );
add_filter( 'wp_generate_attachment_metadata', 'aidl_flush_map_meta_filter', 10, 2 );

function aidl_flush_map() {
	delete_transient( 'aidl_map' );
}

function aidl_flush_map_meta_filter( $metadata, $attachment_id ) {

	if ( aidl_is_ai( $attachment_id ) ) {
		delete_transient( 'aidl_map' );
	}

	return $metadata;
}

/**
 * Start buffering the page.
 *
 * Only on a normal front-end page view, and only when there is at least one
 * marked image to look for.
 */
add_action( 'template_redirect', 'aidl_buffer_start', 1 );
function aidl_buffer_start() {

	if ( ! aidl_get( 'catch_all' ) ) {
		return;
	}

	if ( is_admin() || is_feed() || is_embed() || is_robots() || is_404() ) {
		return;
	}

	if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	if ( ! aidl_enabled_here() || ! aidl_map() ) {
		return;
	}

	ob_start( 'aidl_buffer_filter' );
}

/**
 * Label every marked image left in the finished HTML.
 *
 * @param string $html Page HTML.
 * @return string
 */
function aidl_buffer_filter( $html ) {

	if ( '' === trim( (string) $html ) || false === stripos( $html, '<img' ) ) {
		return $html;
	}

	$map = aidl_map();

	if ( ! $map ) {
		return $html;
	}

	$html = aidl_scan(
		$html,
		function ( $fragment ) use ( $map ) {
			return aidl_id_from_file( $fragment, $map );
		}
	);

	// The marker only exists to prevent double labelling within one request.
	return str_replace( ' data-aidl-done="1"', '', $html );
}

/**
 * Attachment ID from a file name in the image markup.
 *
 * @param string $fragment Image markup.
 * @param array  $map      File name => attachment ID.
 * @return int
 */
function aidl_id_from_file( $fragment, $map ) {

	if ( ! preg_match_all( '#[\w\-\.%]+\.(?:jpe?g|png|gif|webp|avif|svg)#i', $fragment, $trafienia ) ) {
		return 0;
	}

	foreach ( $trafienia[0] as $nazwa ) {

		$nazwa = rawurldecode( $nazwa );

		if ( isset( $map[ $nazwa ] ) ) {
			return (int) $map[ $nazwa ];
		}
	}

	return 0;
}

