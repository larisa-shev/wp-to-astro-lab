<?php
/**
 * AI Hub Child Theme (NovaTech Solutions Lab) functions and definitions.
 *
 * Provides asset management, ACF Free nested-group normalization helpers,
 * inline SVG icon sprites, and REST API normalization for the Astro migration lab.
 *
 * @package AI_Hub_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Set up theme defaults and document title support.
 */
add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'gallery', 'caption', 'style', 'script' ) );
	}
);

/**
 * Enqueue styles and scripts for the NovaTech Solutions landing page template.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_page_template( 'page-novatech.php' ) ) {
			$theme_version = wp_get_theme()->get( 'Version' );

			// Real Swiper v11 library stylesheet (stored locally in theme vendor directory)
			wp_enqueue_style(
				'novatech-swiper',
				get_stylesheet_directory_uri() . '/assets/vendor/swiper/swiper-bundle.min.css',
				array(),
				'11.2.6'
			);

			// NovaTech B2B Design System stylesheet
			wp_enqueue_style(
				'novatech-landing',
				get_stylesheet_directory_uri() . '/assets/css/novatech.css',
				array( 'novatech-swiper' ),
				$theme_version
			);

			// Real Swiper v11 library JavaScript bundle
			wp_enqueue_script(
				'novatech-swiper',
				get_stylesheet_directory_uri() . '/assets/vendor/swiper/swiper-bundle.min.js',
				array(),
				'11.2.6',
				true
			);

			// NovaTech interactive behaviors (Mobile menu, Swiper #1 & #2, Process Accordion, FAQ Accordion)
			wp_enqueue_script(
				'novatech-landing',
				get_stylesheet_directory_uri() . '/assets/js/novatech.js',
				array( 'novatech-swiper' ),
				$theme_version,
				true
			);
		}
	},
	20
);

/**
 * Normalize an ACF Free fixed sub-group associative array (e.g., card_1..card_6)
 * into a clean, zero-indexed array of enabled items.
 *
 * This helper bridges the gap between ACF Free fixed sub-groups in WordPress
 * and clean array structures used in Astro components.
 *
 * @param mixed $group_container Associative array of sub-groups from get_field().
 * @param bool  $check_enabled   Whether to filter out items where 'enabled' is false.
 * @return array<int, array<string, mixed>>
 */
function nt_normalize_acf_list( $group_container, $check_enabled = true ) {
	if ( ! is_array( $group_container ) ) {
		return array();
	}

	$normalized = array();
	foreach ( $group_container as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		if ( $check_enabled && isset( $item['enabled'] ) && ! $item['enabled'] ) {
			continue;
		}
		$normalized[] = $item;
	}

	return $normalized;
}

/**
 * Normalize an ACF Image field (which may be returned as an attachment array,
 * an attachment ID, or a URL string) into a consistent metadata structure.
 *
 * @param mixed  $image_fieldValue Raw ACF image field value.
 * @param string $fallback_alt     Fallback alt text if none is stored.
 * @return array{url: string, alt: string, width: int, height: int}
 */
function nt_resolve_acf_image( $image_field_value, $fallback_alt = '' ) {
	$result = array(
		'url'    => '',
		'alt'    => $fallback_alt,
		'width'  => 800,
		'height' => 500,
	);

	if ( is_array( $image_field_value ) && ! empty( $image_field_value['url'] ) ) {
		$result['url']    = (string) $image_field_value['url'];
		$result['alt']    = ! empty( $image_field_value['alt'] ) ? (string) $image_field_value['alt'] : $fallback_alt;
		$result['width']  = ! empty( $image_field_value['width'] ) ? (int) $image_field_value['width'] : 800;
		$result['height'] = ! empty( $image_field_value['height'] ) ? (int) $image_field_value['height'] : 500;
		return $result;
	}

	if ( is_numeric( $image_field_value ) && (int) $image_field_value > 0 ) {
		$att_id = (int) $image_field_value;
		$src    = wp_get_attachment_image_src( $att_id, 'full' );
		if ( $src ) {
			$result['url']    = (string) $src[0];
			$result['width']  = (int) $src[1];
			$result['height'] = (int) $src[2];
		}
		$alt = get_post_meta( $att_id, '_wp_attachment_image_alt', true );
		if ( ! empty( $alt ) ) {
			$result['alt'] = (string) $alt;
		}
		return $result;
	}

	if ( is_string( $image_field_value ) && ! empty( $image_field_value ) ) {
		$result['url'] = $image_field_value;
	}

	return $result;
}

/**
 * Return a clean, accessible inline SVG icon for a service card based on its ACF select key.
 *
 * @param string $icon_key Icon identifier from ACF select field.
 * @return string Safe SVG markup.
 */
function nt_get_service_icon_svg( $icon_key ) {
	$icons = array(
		'cloud'         => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>',
		'data'          => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/></svg>',
		'platform'      => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/><path d="m7 8 3 2.5L7 13"/><path d="M12 13h4"/></svg>',
		'security'      => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
		'ai'            => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v3"/><path d="M15 1v3"/><path d="M9 20v3"/><path d="M15 20v3"/><path d="M20 9h3"/><path d="M20 14h3"/><path d="M1 9h3"/><path d="M1 14h3"/></svg>',
		'modernization' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
	);

	return isset( $icons[ $icon_key ] ) ? $icons[ $icon_key ] : $icons['cloud'];
}
