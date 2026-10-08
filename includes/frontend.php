<?php
/**
 * Showing the disclosure label on the site.
 *
 * @package AiDisclosureLabels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Regex for one image in rendered HTML.
 *
 * A <picture> is matched whole: it may contain nothing but <source> and
 * <img>, so a wrapper has to go around the outside. That is also how WebP
 * converters render images.
 */
define( 'AIDL_IMG_PATTERN', '#<picture\b[^>]*>.*?</picture>|<img\b[^>]*>#is' );

/**
 * Label markup.
 *
 * Two variants, because the same image appears in very different contexts: a
 * full caption under a large image, and a compact badge on a thumbnail - so
 * the disclosure is present at the first exposure, not only after the
 * visitor opens the post.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $variant       'caption' or 'badge'.
 * @return string
 */
function aidl_label_html( $attachment_id, $variant = 'caption' ) {

	$mode = aidl_mode( $attachment_id );

	if ( 'modified' === $mode ) {
		$tekst     = aidl_get( 'label_text_modified' );
		$plakietka = aidl_get( 'badge_text_modified' );
	} else {
		$tekst     = aidl_get( 'label_text' );
		$plakietka = aidl_get( 'badge_text' );
	}

	$text = (string) apply_filters( 'aidl_label_text', $tekst, $attachment_id, $variant, $mode );

	if ( 'badge' === $variant ) {

		$badge = (string) apply_filters( 'aidl_badge_text', $plakietka, $attachment_id, $mode );

		$klasy = 'aidl-label aidl-label--badge aidl-label--' . $mode
			. ' aidl-at-' . aidl_corner()
			. ' aidl-style-' . aidl_style_for( 'badge' );

		return '<span class="' . esc_attr( $klasy ) . '" title="' . esc_attr( $text ) . '">'
			. esc_html( $badge )
			. '<span class="screen-reader-text"> ' . esc_html( $text ) . '</span></span>';
	}

	$klasy = 'aidl-label aidl-label--' . $mode . ' aidl-style-' . aidl_style_for( 'caption' );

	return '<span class="' . esc_attr( $klasy ) . '">' . esc_html( $text ) . '</span>';
}

/**
 * Is this a small image size?
 *
 * A caption is wider than a 150px thumbnail, so on one it reads as a caption
 * and on the other as a broken layout. Sidebar widgets and related-post
 * lists ask for small sizes even on a single post, so the page type alone is
 * not enough to decide.
 *
 * @param string|array $size Size name or array( width, height ).
 * @return bool
 */
function aidl_size_small( $size ) {

	$szerokosc = 0;

	if ( is_array( $size ) ) {

		$szerokosc = isset( $size[0] ) ? (int) $size[0] : 0;

	} elseif ( is_string( $size ) && '' !== $size ) {

		$zarejestrowane = wp_get_registered_image_subsizes();

		if ( isset( $zarejestrowane[ $size ]['width'] ) ) {
			$szerokosc = (int) $zarejestrowane[ $size ]['width'];
		}
	}

	return $szerokosc > 0 && $szerokosc < 320;
}

/**
 * Which variant fits: a caption under the image, or a badge on it.
 *
 * @param string|array $size Optional size being rendered.
 * @return string
 */
function aidl_variant( $size = '' ) {

	$placement = (string) aidl_get( 'placement' );

	if ( ! isset( aidl_placements()[ $placement ] ) ) {
		$placement = 'auto';
	}

	if ( 'below' === $placement ) {
		return 'caption';
	}

	if ( 'auto' !== $placement ) {
		return 'badge';
	}

	// Automatic: a caption needs room, so a thumbnail gets the badge.
	if ( $size && aidl_size_small( $size ) ) {
		return 'badge';
	}

	return is_singular() ? 'caption' : 'badge';
}

/**
 * Which corner a badge goes in.
 *
 * @return string
 */
function aidl_corner() {

	$placement = (string) aidl_get( 'placement' );

	$narozniki = array( 'bottom-left', 'bottom-right', 'top-left', 'top-right' );

	return in_array( $placement, $narozniki, true ) ? $placement : 'bottom-left';
}

/**
 * Which style class to use for a variant.
 *
 * @param string $variant 'caption' or 'badge'.
 * @return string
 */
function aidl_style_for( $variant ) {

	$style = (string) aidl_get( 'style' );

	if ( isset( aidl_styles_list()[ $style ] ) && 'auto' !== $style ) {
		return $style;
	}

	return 'badge' === $variant ? 'dark' : 'subtle';
}

/**
 * Is labelling switched on for the current view?
 *
 * @return bool
 */
function aidl_enabled_here() {
	return is_singular() ? (bool) aidl_get( 'show_single' ) : (bool) aidl_get( 'show_archive' );
}

/**
 * Attach the label to an image.
 *
 * The label goes directly against the <img>, never around the container the
 * theme built. Wrapping a <figure> looks tempting and is wrong twice over:
 * the label ends up below the caption and below everything else the figure
 * holds, which reads as a stray line of text rather than a caption on the
 * picture; and the figure stops being a direct child of its parent, so every
 * theme rule written as `.parent > figure` quietly stops matching.
 *
 * @param string $html          Image markup, possibly wrapped by the theme.
 * @param int    $attachment_id Attachment ID.
 * @param string $variant       Optional forced variant.
 * @return string
 */
function aidl_wrap( $html, $attachment_id, $variant = '' ) {

	$variant = $variant ? $variant : aidl_variant();
	$class   = 'badge' === $variant ? 'aidl-image aidl-image--badge' : 'aidl-image';
	$label   = aidl_label_html( $attachment_id, $variant );
	$otwarcie = '<span class="' . esc_attr( $class ) . '">';

	/*
	 * Marker for the catch-all pass, which sees only the finished HTML and
	 * cannot tell that this image already has its label. Stripped again
	 * before the page is sent.
	 */
	if ( aidl_get( 'catch_all' ) && false === strpos( $html, 'data-aidl-done' ) ) {
		$html = preg_replace( '#<img\b#i', '<img data-aidl-done="1"', $html, 1 );
	}

	// Already just an image: wrap it as it is.
	if ( preg_match( '#^\s*<(img|picture)\b#i', $html ) ) {
		return $otwarcie . $html . $label . '</span>';
	}

	// Theme markup around the image: reach inside and take the image only.
	$ile = 0;

	$zawiniete = preg_replace_callback(
		AIDL_IMG_PATTERN,
		function ( $m ) use ( $otwarcie, $label ) {
			return $otwarcie . $m[0] . $label . '</span>';
		},
		$html,
		1,
		$ile
	);

	return $ile ? $zawiniete : $otwarcie . $html . $label . '</span>';
}

/**
 * Shared bail-out test, so every entry point behaves the same.
 *
 * @param string $html          Markup to label.
 * @param int    $attachment_id Attachment ID.
 * @return bool
 */
function aidl_should_label( $html, $attachment_id ) {

	if ( is_admin() || is_feed() || '' === $html ) {
		return false;
	}

	if ( false !== strpos( $html, 'aidl-label' ) ) {
		return false;
	}

	if ( ! $attachment_id || ! aidl_is_ai( $attachment_id ) ) {
		return false;
	}

	return aidl_enabled_here();
}

/**
 * Does this image already carry a label?
 *
 * The marker attribute covers the same request. The context check covers a
 * fragment that came back from a cache after the marker had been stripped:
 * the wrapper class sits just before the image, the label just after it.
 *
 * @param string $html  Full markup being scanned.
 * @param int    $start Offset of the matched image.
 * @param int    $end   Offset just after it.
 * @return bool
 */
function aidl_already_labelled( $html, $start, $end ) {

	$przed = substr( $html, max( 0, $start - 200 ), min( 200, $start ) );
	$po    = substr( $html, $end, 120 );

	return false !== strpos( $przed, 'aidl-image' )
		|| false !== strpos( $przed, 'aidl-figure' )
		|| false !== strpos( $po, 'aidl-label' );
}

/**
 * Walk rendered HTML and label every marked image in it.
 *
 * @param string   $html      Markup.
 * @param callable $rozpoznaj Receives one image fragment, returns an attachment ID or 0.
 * @return string
 */
function aidl_scan( $html, $rozpoznaj ) {

	if ( ! preg_match_all( AIDL_IMG_PATTERN, $html, $trafienia, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}

	$out    = '';
	$kursor = 0;

	foreach ( $trafienia[0] as $trafienie ) {

		$fragment = $trafienie[0];
		$pozycja  = $trafienie[1];

		$out   .= substr( $html, $kursor, $pozycja - $kursor );
		$kursor = $pozycja + strlen( $fragment );

		if ( false !== strpos( $fragment, 'data-aidl-done' )
			|| false !== strpos( $fragment, 'aidl-label' )
			|| aidl_already_labelled( $html, $pozycja, $kursor ) ) {

			$out .= $fragment;
			continue;
		}

		$id = (int) call_user_func( $rozpoznaj, $fragment );

		$out .= ( $id && aidl_is_ai( $id ) ) ? aidl_wrap( $fragment, $id, aidl_variant_for( $fragment ) ) : $fragment;
	}

	return $out . substr( $html, $kursor );
}

/**
 * Variant for an image found in finished HTML, where no size name is
 * available - the rendered width in the tag is the next best thing.
 *
 * @param string $fragment Image markup.
 * @return string
 */
function aidl_variant_for( $fragment ) {

	if ( preg_match( '#\swidth=["\']?(\d+)#i', $fragment, $m ) ) {
		return aidl_variant( array( (int) $m[1] ) );
	}

	return aidl_variant();
}

/**
 * Attachment ID from the wp-image-<id> class.
 *
 * Both the block editor and the classic editor put it there, and it is the
 * only reliable way back to the attachment - the src alone breaks on every
 * CDN, resized file and migrated upload path.
 *
 * @param string $fragment Image markup.
 * @return int
 */
function aidl_id_from_class( $fragment ) {
	return preg_match( '/wp-image-(\d+)/', $fragment, $m ) ? (int) $m[1] : 0;
}

/**
 * Featured image.
 *
 * @param string $html              Image markup.
 * @param int    $post_id           Post ID.
 * @param int    $post_thumbnail_id Attachment ID.
 * @param mixed  $size              Size.
 * @return string
 */
add_filter( 'post_thumbnail_html', 'aidl_featured_image', 20, 4 );
function aidl_featured_image( $html, $post_id, $post_thumbnail_id, $size = '' ) {

	if ( ! aidl_should_label( $html, $post_thumbnail_id ) ) {
		return $html;
	}

	return aidl_wrap( $html, $post_thumbnail_id, aidl_variant( $size ) );
}

/**
 * Any image a theme renders with wp_get_attachment_image() - product
 * galleries, custom loops, sidebar widgets, ACF fields output through that
 * function.
 *
 * get_the_post_thumbnail() calls wp_get_attachment_image() internally, so
 * without the two flags below the featured image would get two labels.
 *
 * @param string $html          Image markup.
 * @param int    $attachment_id Attachment ID.
 * @param mixed  $size          Size.
 * @return string
 */
add_filter( 'wp_get_attachment_image', 'aidl_attachment_image', 20, 3 );
function aidl_attachment_image( $html, $attachment_id, $size = '' ) {

	if ( aidl_in_thumbnail() || ! aidl_should_label( $html, $attachment_id ) ) {
		return $html;
	}

	return aidl_wrap( $html, $attachment_id, aidl_variant( $size ) );
}

/**
 * Flag set while the featured image is being rendered.
 *
 * @param bool|null $set Internal.
 * @return bool
 */
function aidl_in_thumbnail( $set = null ) {

	static $w_trakcie = false;

	if ( null !== $set ) {
		$w_trakcie = (bool) $set;
	}

	return $w_trakcie;
}

add_action( 'begin_fetch_post_thumbnail_html', 'aidl_thumbnail_start' );
function aidl_thumbnail_start() {
	aidl_in_thumbnail( true );
}

add_action( 'end_fetch_post_thumbnail_html', 'aidl_thumbnail_end' );
function aidl_thumbnail_end() {
	aidl_in_thumbnail( false );
}

/**
 * Images in post content.
 *
 * Runs after do_blocks(), so it sees block output as well - which the
 * render_block filter has usually already labelled, and the guards in
 * aidl_scan() keep it from happening twice. What this adds is everything the
 * block filter cannot see: content written in the classic editor, and
 * anything a shortcode printed.
 *
 * @param string $html Content.
 * @return string
 */
add_filter( 'the_content', 'aidl_content_images', 25 );
function aidl_content_images( $html ) {

	if ( is_admin() || is_feed() || '' === $html || ! aidl_enabled_here() ) {
		return $html;
	}

	if ( false === stripos( $html, '<img' ) || false === strpos( $html, 'wp-image-' ) ) {
		return $html;
	}

	return aidl_scan( $html, 'aidl_id_from_class' );
}

/**
 * Images in blocks, wherever a block is rendered - content, template parts,
 * query loops.
 *
 * @param string $html  Rendered block.
 * @param array  $block Block data.
 * @return string
 */
add_filter( 'render_block', 'aidl_render_block', 20, 2 );
function aidl_render_block( $html, $block ) {

	$obslugiwane = array( 'core/image', 'core/media-text', 'core/gallery', 'core/post-featured-image' );

	if ( empty( $block['blockName'] ) || ! in_array( $block['blockName'], $obslugiwane, true ) ) {
		return $html;
	}

	if ( is_admin() || is_feed() || '' === $html || false === strpos( $html, 'wp-image-' ) || ! aidl_enabled_here() ) {
		return $html;
	}

	return aidl_scan( $html, 'aidl_id_from_class' );
}

/**
 * For themes that print images straight from an ACF field or a hand-written
 * <img>, where no filter can reach them. Drop this in the template:
 *
 *     aidl_label( $attachment_id );
 *
 * or use the [ai_disclosure id="123"] shortcode in content.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $variant       'caption', 'badge' or '' for automatic.
 * @param bool   $echo          Print or return.
 * @return string
 */
function aidl_label( $attachment_id, $variant = '', $echo = true ) {

	$out = '';

	if ( $attachment_id && aidl_is_ai( $attachment_id ) && aidl_enabled_here() ) {
		$out = aidl_label_html( (int) $attachment_id, $variant ? $variant : aidl_variant() );
	}

	if ( $echo ) {
		echo $out; // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped in aidl_label_html().
	}

	return $out;
}

add_shortcode( 'ai_disclosure', 'aidl_shortcode' );
function aidl_shortcode( $atts ) {

	$atts = shortcode_atts(
		array(
			'id'      => 0,
			'variant' => '',
		),
		$atts,
		'ai_disclosure'
	);

	return aidl_label( (int) $atts['id'], $atts['variant'], false );
}

/**
 * Styles. Inline and tiny - no reason to make the browser fetch a file for
 * a handful of rules.
 */
add_action( 'wp_enqueue_scripts', 'aidl_styles' );
function aidl_styles() {

	$size    = (int) aidl_get( 'font_size' );
	$opacity = max( 0, min( 100, (int) aidl_get( 'opacity' ) ) ) / 100;

	$css = '.aidl-image{display:block;}'
		. '.aidl-image--badge{position:relative;display:inline-block;line-height:0;max-width:100%;}'

		// A caption under the image.
		. '.aidl-label{display:block;margin-top:8px;font-size:' . $size . 'px;line-height:1.4;}'

		// A badge on the image. The corner comes from one of the aidl-at-* classes.
		. '.aidl-label--badge{position:absolute;margin:0;display:inline-block;max-width:calc(100% - 16px);'
		. 'padding:2px 7px;border-radius:4px;font-size:' . max( 10, $size - 2 ) . 'px;line-height:1.5;'
		. 'letter-spacing:.04em;pointer-events:none;}'
		. '.aidl-at-bottom-left{left:8px;bottom:8px;}'
		. '.aidl-at-bottom-right{right:8px;bottom:8px;}'
		. '.aidl-at-top-left{left:8px;top:8px;}'
		. '.aidl-at-top-right{right:8px;top:8px;}'

		/*
		 * Styles. Opacity applies to plain text only: fading a pill fades its
		 * background with it, and a half-transparent box over a photograph is
		 * exactly where a disclosure stops being legible.
		 */
		. '.aidl-style-subtle{background:none;color:inherit;padding:0;opacity:' . $opacity . ';}'
		. '.aidl-style-dark{background:rgba(16,22,34,.82);color:#fff;}'
		. '.aidl-style-light{background:rgba(255,255,255,.92);color:#101622;'
		. 'box-shadow:0 1px 3px rgba(0,0,0,.18);}'
		. '.aidl-style-glass{background:rgba(16,22,34,.42);color:#fff;'
		. '-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);}'

		// A text-shadow keeps a plain caption readable if it lands on a photo.
		. '.aidl-label--badge.aidl-style-subtle{color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.7);opacity:1;}';

	wp_register_style( 'aidl', false, array(), AIDL_VERSION );
	wp_enqueue_style( 'aidl' );
	wp_add_inline_style( 'aidl', $css );
}
