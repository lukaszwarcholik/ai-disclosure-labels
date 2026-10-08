<?php
/**
 * Marking images in the media library and in the block editor.
 *
 * @package AiDisclosureLabels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the meta so the block editor can read and write it over REST.
 */
add_action( 'init', 'aidl_register_meta' );
function aidl_register_meta() {

	register_post_meta(
		'attachment',
		AIDL_META_MODE,
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'aidl_sanitize_mode',
			'auth_callback'     => function () {
				return current_user_can( 'upload_files' );
			},
		)
	);

	register_post_meta(
		'attachment',
		AIDL_META,
		array(
			'type'              => 'boolean',
			'single'            => true,
			'default'           => false,
			'show_in_rest'      => true,
			'sanitize_callback' => 'rest_sanitize_boolean',
			'auth_callback'     => function () {
				return current_user_can( 'upload_files' );
			},
		)
	);
}

/**
 * Keep only a mode we know.
 *
 * @param mixed $mode Raw value.
 * @return string
 */
function aidl_sanitize_mode( $mode ) {

	$mode = is_string( $mode ) ? $mode : '';

	return isset( aidl_modes()[ $mode ] ) ? $mode : '';
}

/**
 * Keep the yes/no flag in step when the mode is written over REST, which is
 * what the editor panel does.
 */
add_action( 'updated_post_meta', 'aidl_sync_flag', 10, 4 );
add_action( 'added_post_meta', 'aidl_sync_flag', 10, 4 );
function aidl_sync_flag( $meta_id, $object_id, $meta_key, $meta_value ) {

	if ( AIDL_META_MODE !== $meta_key ) {
		return;
	}

	if ( '' === (string) $meta_value ) {
		delete_post_meta( $object_id, AIDL_META );
	} else {
		update_post_meta( $object_id, AIDL_META, 1 );
	}
}

/**
 * Mode picker in the media modal and in the attachment details screen.
 *
 * @param array   $form_fields Fields.
 * @param WP_Post $post        Attachment.
 * @return array
 */
add_filter( 'attachment_fields_to_edit', 'aidl_attachment_field', 10, 2 );
function aidl_attachment_field( $form_fields, $post ) {

	if ( ! current_user_can( 'upload_files' ) ) {
		return $form_fields;
	}

	$biezacy = aidl_mode( $post->ID );
	$nazwa   = 'attachments[' . (int) $post->ID . '][aidl_mode]';

	$opcje = '';

	foreach ( aidl_modes() as $wartosc => $etykieta ) {
		$opcje .= '<option value="' . esc_attr( $wartosc ) . '" ' . selected( $biezacy, $wartosc, false ) . '>'
			. esc_html( $etykieta ) . '</option>';
	}

	$html = '<select name="' . esc_attr( $nazwa ) . '" style="max-width:100%;">' . $opcje . '</select>'
		. '<p style="margin:6px 0 0;opacity:.7;font-size:12px;">'
		. esc_html__( 'A disclosure label will be shown with this image. "Modified" is for a real photo an AI has altered.', 'ai-disclosure-labels' )
		. '</p>';

	$form_fields['aidl_mode'] = array(
		'label'         => __( 'AI disclosure', 'ai-disclosure-labels' ),
		'input'         => 'html',
		'html'          => $html,
		'show_in_edit'  => true,
		'show_in_modal' => true,
	);

	return $form_fields;
}

/**
 * Save the picker.
 *
 * @param array $post       Attachment post data.
 * @param array $attachment Submitted fields.
 * @return array
 */
add_filter( 'attachment_fields_to_save', 'aidl_attachment_save', 10, 2 );
function aidl_attachment_save( $post, $attachment ) {

	if ( ! current_user_can( 'upload_files' ) || ! isset( $attachment['aidl_mode'] ) ) {
		return $post;
	}

	aidl_set_mode( $post['ID'], aidl_sanitize_mode( $attachment['aidl_mode'] ) );

	return $post;
}

/**
 * Column in the media library list view, so you can see at a glance which
 * files are marked.
 */
add_filter( 'manage_media_columns', 'aidl_media_column' );
function aidl_media_column( $columns ) {

	$columns['aidl'] = __( 'AI', 'ai-disclosure-labels' );

	return $columns;
}

add_action( 'manage_media_custom_column', 'aidl_media_column_content', 10, 2 );
function aidl_media_column_content( $column, $post_id ) {

	if ( 'aidl' !== $column ) {
		return;
	}

	$mode = aidl_mode( $post_id );

	if ( '' === $mode ) {
		echo '<span aria-hidden="true">&ndash;</span>';
		return;
	}

	$skroty = array(
		'generated' => __( 'generated', 'ai-disclosure-labels' ),
		'modified'  => __( 'modified', 'ai-disclosure-labels' ),
	);

	echo '<span title="' . esc_attr( aidl_modes()[ $mode ] ) . '">'
		. esc_html( isset( $skroty[ $mode ] ) ? $skroty[ $mode ] : $mode )
		. '</span>';
}

/**
 * Panel in the block editor for the featured image.
 */
add_action( 'enqueue_block_editor_assets', 'aidl_editor_assets' );
function aidl_editor_assets() {

	wp_enqueue_script(
		'aidl-editor',
		AIDL_URL . 'assets/editor.js',
		array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ),
		AIDL_VERSION,
		true
	);

	wp_set_script_translations( 'aidl-editor', 'ai-disclosure-labels', AIDL_PATH . 'languages' );
}
