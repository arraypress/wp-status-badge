<?php
/**
 * PHPUnit bootstrap.
 *
 * @package ArrayPress\StatusBadge
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// wp-composer-assets works out an asset's URL from where the file sits
// relative to these, so it needs both to resolve one at all.
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', dirname( __DIR__, 3 ) );
}

if ( ! defined( 'WP_CONTENT_URL' ) ) {
	define( 'WP_CONTENT_URL', 'https://example.test/wp-content' );
}

/*
 * The four WordPress functions this library touches, and nothing else. A
 * fuller set would be a second answer to "what does esc_attr do in a test",
 * which is how a suite ends up passing on markup that would not escape.
 */
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

/*
 * Enqueues and registrations are recorded rather than performed, so a test can
 * ask what the library asked WordPress for.
 *
 * These have to be the WordPress functions rather than wp-composer-assets'
 * own: PHPUnit loads the autoloader before it loads this file, so the real
 * wp_register_composer_style() is already defined by the time anything here
 * runs and cannot be shadowed. Stubbing underneath it is the better test
 * anyway — the path from "register a style" to "a handle and a URL" is the
 * part that would actually break.
 */
if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( $handle, $src, $deps = [], $ver = false, $media = 'all' ) {
		$GLOBALS['sb_registered'][ $handle ] = [
			'src'   => $src,
			'deps'  => $deps,
			'ver'   => $ver,
			'media' => $media,
		];

		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, ...$args ) {
		$GLOBALS['sb_enqueued'][] = $handle;
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		return str_replace( '\\', '/', (string) $path );
	}
}

if ( ! function_exists( 'site_url' ) ) {
	function site_url( $path = '' ) {
		return 'https://example.test' . $path;
	}
}

if ( ! function_exists( 'content_url' ) ) {
	function content_url( $path = '' ) {
		return 'https://example.test/wp-content' . $path;
	}
}

if ( ! function_exists( '_doing_it_wrong' ) ) {
	function _doing_it_wrong( $function, $message, $version ) {
	}
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
