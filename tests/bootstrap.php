<?php

declare(strict_types=1);

$plugin_root = dirname( __DIR__ );

require_once $plugin_root . '/vendor/autoload.php';
require_once $plugin_root . '/tests/Support/OptionsStore.php';
require_once $plugin_root . '/tests/stubs/woocommerce/WC_Email.php';

/** @var list<array{to:string,subject:string,message:string}> */
$GLOBALS['pks_oi_test_mail_log'] = [];

if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
		if ( ! empty( $GLOBALS['pks_oi_test_mail_fail'] ) ) {
			return false;
		}

		$GLOBALS['pks_oi_test_mail_log'][] = [
			'to'      => (string) $to,
			'subject' => (string) $subject,
			'message' => (string) $message,
		];

		return true;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url, $protocols = null ) {
		unset( $protocols );
		$url = trim( (string) $url );
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return '';
		}

		return $url;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}

		return (object) [
			'ID'           => $user_id,
			'user_email'   => 'user' . $user_id . '@example.com',
			'display_name' => 'User ' . $user_id,
		];
	}
}

use PrikOgStreg\OnlineInvitations\Tests\Support\OptionsStore;

if ( ! defined( 'PKS_OI_VERSION' ) ) {
	define( 'PKS_OI_VERSION', '0.1.0' );
}
if ( ! defined( 'PKS_OI_DB_VERSION' ) ) {
	define( 'PKS_OI_DB_VERSION', '1' );
}
if ( ! defined( 'PKS_OI_PLUGIN_FILE' ) ) {
	define( 'PKS_OI_PLUGIN_FILE', $plugin_root . '/prikogstreg-online-invitations.php' );
}
if ( ! defined( 'PKS_OI_PLUGIN_PATH' ) ) {
	define( 'PKS_OI_PLUGIN_PATH', $plugin_root . '/' );
}
if ( ! defined( 'PKS_OI_PLUGIN_URL' ) ) {
	define( 'PKS_OI_PLUGIN_URL', 'https://example.test/wp-content/plugins/prikogstreg-online-invitations/' );
}
if ( ! defined( 'PKS_OI_TEXT_DOMAIN' ) ) {
	define( 'PKS_OI_TEXT_DOMAIN', 'prikogstreg-online-invitations' );
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $plugin_root . '/tests/stubs/wp/' );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ) {
		return true;
	}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $file, $callback ) {
		return true;
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {
		return true;
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return trailingslashit( dirname( $file ) );
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'https://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return OptionsStore::get( (string) $option, $default );
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $option, $value, $deprecated = '', $autoload = 'yes' ) {
		if ( array_key_exists( (string) $option, OptionsStore::$values ) ) {
			return false;
		}

		OptionsStore::set( (string) $option, $value );

		return true;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		OptionsStore::set( (string) $option, $value );

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		OptionsStore::delete( (string) $option );

		return true;
	}
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules( $hard = true ) {
		return true;
	}
}

if ( ! function_exists( 'add_rewrite_tag' ) ) {
	function add_rewrite_tag( $tag, $regex, $query = '' ) {
		unset( $tag, $regex, $query );
	}
}

if ( ! function_exists( 'add_rewrite_rule' ) ) {
	function add_rewrite_rule( $regex, $query, $after = 'bottom' ) {
		unset( $regex, $query, $after );
	}
}

if ( ! function_exists( 'locate_template' ) ) {
	function locate_template( $template_names, $load = false, $load_once = true ) {
		return '';
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string, $remove_breaks = false ) {
		$string = (string) $string;
		$string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string ) ?? $string;
		$string = strip_tags( $string );

		return trim( $string );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( (string) $str );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( (string) $str );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		$email = trim( (string) $email );

		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
	}
}

/** @var array<string, mixed> */
$GLOBALS['pks_oi_test_transients'] = [];

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		$key = (string) $transient;

		return $GLOBALS['pks_oi_test_transients'][ $key ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		$GLOBALS['pks_oi_test_transients'][ (string) $transient ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		unset( $GLOBALS['pks_oi_test_transients'][ (string) $transient ] );

		return true;
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'pks-oi-test-salt-' . (string) $scheme;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wc_string_to_bool' ) ) {
	function wc_string_to_bool( $value ): bool {
		return in_array( strtolower( (string) $value ), [ '1', 'true', 'yes', 'on' ], true );
	}
}

if ( ! function_exists( 'headers_sent' ) ) {
	function headers_sent( &$file = null, &$line = null ): bool {
		unset( $file, $line );

		return false;
	}
}

if ( ! function_exists( 'header' ) ) {
	/**
	 * @var list<string>
	 */
	$GLOBALS['pks_oi_test_headers'] = [];

	function header( $header, $replace = true, $response_code = 0 ) {
		unset( $replace, $response_code );
		$GLOBALS['pks_oi_test_headers'][] = (string) $header;
	}
}

if ( ! function_exists( 'status_header' ) ) {
	function status_header( $code, $description = null ) {
		unset( $code, $description );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return is_object( $thing ) && method_exists( $thing, 'get_error_code' );
	}
}

if ( ! function_exists( 'dbDelta' ) ) {
	/**
	 * @param list<string>|string $queries
	 */
	function dbDelta( $queries = '' ): void {
		// Test stub: schema application is verified separately.
	}
}
