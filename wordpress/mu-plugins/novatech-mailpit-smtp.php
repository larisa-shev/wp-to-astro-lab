<?php
/**
 * Plugin Name: NovaTech Local Mailpit SMTP & REST Media Enhancer
 * Description: Routes all wp_mail() traffic (including Contact Form 7 submissions) to the local Mailpit container inside the Docker network and enriches ACF image fields in the WP REST API.
 * Version: 1.0.0
 * Author: NovaTech Engineering Lab
 * License: MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configure PHPMailer to send all emails exclusively to the internal Mailpit container.
 * No external SMTP servers or public addresses are contacted.
 */
add_action(
	'phpmailer_init',
	function ( $phpmailer ) {
		$phpmailer->isSMTP();
		$phpmailer->Host        = getenv( 'MAILPIT_SMTP_HOST' ) ? getenv( 'MAILPIT_SMTP_HOST' ) : 'mailpit';
		$phpmailer->Port        = getenv( 'MAILPIT_SMTP_PORT' ) ? (int) getenv( 'MAILPIT_SMTP_PORT' ) : 1025;
		$phpmailer->SMTPAuth    = false;
		$phpmailer->SMTPSecure  = '';
		$phpmailer->SMTPAutoTLS = false;
		$phpmailer->CharSet     = 'UTF-8';
	},
	10,
	1
);

/**
 * Ensure a valid local sender address so PHPMailer never fails on hostname validation
 * when WORDPRESS_SITE_URL uses "localhost:8001".
 */
add_filter(
	'wp_mail_from',
	function () {
		return 'no-reply@novatech.local';
	}
);

add_filter(
	'wp_mail_from_name',
	function () {
		return 'NovaTech Solutions Demo';
	}
);

/**
 * Ensure ACF Image fields returned via the WordPress REST API always include
 * expanded attachment metadata (url, width, height, alt) even when queried
 * without ?acf_format=standard.
 */
/**
 * Normalize ACF image arrays by removing the duplicate uppercase 'ID' key
 * that collides with lowercase 'id' in case-insensitive JSON parsers (e.g. PowerShell),
 * while preserving all standard metadata ('id', 'url', 'alt', 'title', 'width', 'height', 'mime').
 */
add_filter(
	'acf/format_value/type=image',
	function ( $value ) {
		if ( is_array( $value ) && isset( $value['ID'] ) && isset( $value['id'] ) ) {
			unset( $value['ID'] );
		}
		return $value;
	},
	20,
	1
);

add_filter(
	'acf/rest/format_value_for_rest/type=image',
	function ( $value_formatted, $post_id, $field, $value, $format ) {
		$att_id = 0;
		if ( is_numeric( $value ) && (int) $value > 0 ) {
			$att_id = (int) $value;
		} elseif ( is_array( $value_formatted ) && ! empty( $value_formatted['id'] ) ) {
			$att_id = (int) $value_formatted['id'];
		} elseif ( is_array( $value_formatted ) && ! empty( $value_formatted['ID'] ) ) {
			$att_id = (int) $value_formatted['ID'];
		}

		if ( $att_id > 0 ) {
			$attachment = acf_get_attachment( $att_id );
			if ( is_array( $attachment ) ) {
				return array(
					'id'     => (int) $attachment['ID'],
					'url'    => (string) $attachment['url'],
					'alt'    => (string) $attachment['alt'],
					'title'  => (string) $attachment['title'],
					'width'  => (int) $attachment['width'],
					'height' => (int) $attachment['height'],
					'mime'   => (string) $attachment['mime_type'],
				);
			}
		}
		return $value_formatted;
	},
	20,
	5
);
