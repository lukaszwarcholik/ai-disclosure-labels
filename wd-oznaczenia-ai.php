<?php
/**
 * Plugin Name:       Oznaczenia AI
 * Plugin URI:        https://webdesign.net.pl/
 * Description:       Oznaczanie grafik wygenerowanych przez AI. Przy obrazku wyroznionym pojawia sie pole wyboru, a pod kazda oznaczona grafika na stronie automatyczny podpis. Odpowiada na wymog przejrzystosci z art. 50 AI Act, obowiazujacy od 2 sierpnia 2026.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Lukasz Warcholik
 * Author URI:        https://webdesign.net.pl/
 * License:           GPL-2.0-or-later
 * Text Domain:       wd-oznaczenia-ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WD_AI_WERSJA', '1.0.0' );

/* Flaga siedzi na ZALACZNIKU, nie na wpisie. Dzieki temu raz oznaczony plik
   jest podpisany wszedzie, gdzie go uzyjesz - jako obrazek wyrozniony, w tresci
   wpisu i w galerii. Gdyby flaga byla na wpisie, ta sama grafika w innym
   miejscu zostalaby bez podpisu. */
const WD_AI_META = '_wd_ai_grafika';

/**
 * Rejestracja pola, zeby bylo dostepne przez REST (edytor blokow).
 */
add_action( 'init', 'wd_ai_rejestruj_pole' );
function wd_ai_rejestruj_pole() {

	register_post_meta(
		'attachment',
		WD_AI_META,
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
 * Czy dany zalacznik jest oznaczony jako AI.
 */
function wd_ai_czy_oznaczona( $id_zalacznika ) {
	return (bool) get_post_meta( (int) $id_zalacznika, WD_AI_META, true );
}

/**
 * Tresc podpisu. Filtr zostawiony celowo - w kolejnej wersji podepniemy
 * pod niego ustawienia wtyczki.
 */
function wd_ai_tresc_podpisu( $id_zalacznika = 0 ) {
	return apply_filters( 'wd_ai_tresc_podpisu', 'Grafika wygenerowana przez AI', $id_zalacznika );
}

/* ---------------------------------------------------------------
 * Pole wyboru w bibliotece mediow (modal i widok szczegolow).
 * --------------------------------------------------------------- */
add_filter( 'attachment_fields_to_edit', 'wd_ai_pole_w_mediach', 10, 2 );
function wd_ai_pole_w_mediach( $pola, $zalacznik ) {

	if ( ! current_user_can( 'upload_files' ) ) {
		return $pola;
	}

	$zaznaczone = wd_ai_czy_oznaczona( $zalacznik->ID );

	$pola['wd_ai_grafika'] = array(
		'label'      => 'Grafika z AI',
		'input'      => 'html',
		'html'       => '<label style="display:flex;gap:8px;align-items:flex-start;line-height:1.4;">'
			. '<input type="checkbox" name="attachments[' . (int) $zalacznik->ID . '][wd_ai_grafika]" value="1" ' . checked( $zaznaczone, true, false ) . ' style="margin-top:2px;">'
			. '<span>Wygenerowana przez AI<br><span style="opacity:.7;font-size:12px;">Pod grafika pojawi sie podpis informujacy odbiorce.</span></span>'
			. '</label>',
		'show_in_edit' => true,
		'show_in_modal' => true,
	);

	return $pola;
}

add_filter( 'attachment_fields_to_save', 'wd_ai_zapisz_pole', 10, 2 );
function wd_ai_zapisz_pole( $post, $zalacznik ) {

	if ( ! current_user_can( 'upload_files' ) ) {
		return $post;
	}

	/* Checkbox nieodznaczony nie przychodzi w ogole, wiec brak klucza
	   traktujemy jako "nie". */
	if ( empty( $zalacznik['wd_ai_grafika'] ) ) {
		delete_post_meta( $post['ID'], WD_AI_META );
	} else {
		update_post_meta( $post['ID'], WD_AI_META, 1 );
	}

	return $post;
}

/* ---------------------------------------------------------------
 * Panel w edytorze blokow - pole wyboru dla obrazka wyroznionego.
 * --------------------------------------------------------------- */
add_action( 'enqueue_block_editor_assets', 'wd_ai_skrypt_edytora' );
function wd_ai_skrypt_edytora() {

	wp_enqueue_script(
		'wd-ai-edytor',
		plugins_url( 'edytor.js', __FILE__ ),
		array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch' ),
		WD_AI_WERSJA,
		true
	);
}

/* ---------------------------------------------------------------
 * Podpis na stronie.
 * --------------------------------------------------------------- */

/**
 * Sam znacznik podpisu. Dwa warianty, bo ta sama grafika pokazuje sie
 * w dwoch bardzo roznych kontekstach:
 *  - "pelny"  - podpis pod duzym obrazkiem na wpisie,
 *  - "plakietka" - mala etykieta na miniaturce w archiwum, zeby ujawnienie
 *    bylo obecne juz przy pierwszym zetknieciu z grafika, a nie dopiero
 *    po wejsciu w tresc.
 */
function wd_ai_podpis_html( $id_zalacznika, $wariant = 'pelny' ) {

	$klasa = ( 'plakietka' === $wariant ) ? 'wd-ai-podpis wd-ai-podpis--plakietka' : 'wd-ai-podpis';

	if ( 'plakietka' === $wariant ) {
		$tresc = apply_filters( 'wd_ai_tresc_plakietki', 'AI', $id_zalacznika );
		$tytul = wd_ai_tresc_podpisu( $id_zalacznika );

		return '<span class="' . esc_attr( $klasa ) . '" title="' . esc_attr( $tytul ) . '">'
			. esc_html( $tresc ) . '<span class="screen-reader-text"> — ' . esc_html( $tytul ) . '</span></span>';
	}

	return '<span class="' . esc_attr( $klasa ) . '">' . esc_html( wd_ai_tresc_podpisu( $id_zalacznika ) ) . '</span>';
}

/**
 * Obrazek wyrozniony - na wpisie pelny podpis, na listingach plakietka.
 */
add_filter( 'post_thumbnail_html', 'wd_ai_podpis_pod_wyroznionym', 20, 5 );
function wd_ai_podpis_pod_wyroznionym( $html, $id_wpisu, $id_zalacznika, $rozmiar, $atrybuty ) {

	if ( is_admin() || '' === $html || ! $id_zalacznika || ! wd_ai_czy_oznaczona( $id_zalacznika ) ) {
		return $html;
	}

	/* Na listingu ujawnienie tez musi byc - tam odbiorca styka sie z grafika
	   po raz pierwszy. Zeby nie rozbijac ukladu kafli, zamiast podpisu pod
	   spodem dajemy mala plakietke w rogu obrazka. */
	$wariant = is_singular() ? 'pelny' : 'plakietka';
	$klasa   = ( 'plakietka' === $wariant ) ? 'wd-ai-obrazek wd-ai-obrazek--plakietka' : 'wd-ai-obrazek';

	return '<span class="' . esc_attr( $klasa ) . '">' . $html . wd_ai_podpis_html( $id_zalacznika, $wariant ) . '</span>';
}

/* Podpisy w tresci wpisu celowo NIE sa doklejane automatycznie.
   Obrazki w tresci maja wlasne pole podpisu w edytorze i to tam
   wpisuje sie informacje o AI - recznie, per grafika. Automat
   obslugujemy tylko dla obrazka wyroznionego, bo ten nie ma
   wlasnego pola podpisu. */

/* ---------------------------------------------------------------
 * Style. W kolejnej wersji do ustawien wtyczki.
 * --------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', 'wd_ai_style' );
function wd_ai_style() {

	$css = '.wd-ai-obrazek{display:block;}'
		. '.wd-ai-podpis{display:block;margin-top:8px;font-size:13px;line-height:1.4;opacity:.65;}'
		. '.wd-ai-obrazek--plakietka{position:relative;}'
		. '.wd-ai-podpis--plakietka{position:absolute;left:8px;bottom:8px;margin:0;display:inline-block;'
		. 'padding:2px 7px;border-radius:4px;background:rgba(16,22,34,.78);color:#fff;'
		. 'font-size:11px;line-height:1.5;letter-spacing:.04em;opacity:1;pointer-events:none;}';

	wp_register_style( 'wd-ai-oznaczenia', false, array(), WD_AI_WERSJA );
	wp_enqueue_style( 'wd-ai-oznaczenia' );
	wp_add_inline_style( 'wd-ai-oznaczenia', $css );
}
