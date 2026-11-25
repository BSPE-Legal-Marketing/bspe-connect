<?php
/**
 * Minimal WordPress stubs so the custom-form classes run on bare PHP.
 * Run the suite: php tests/php/run.php
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'BSPE_CONNECT_VERSION', '3.9.0-test' );
define( 'BSPE_CONNECT_DIR', dirname( __DIR__, 2 ) . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['t_options']   = [];
$GLOBALS['t_post_meta'] = [];
$GLOBALS['t_http']      = [];
$GLOBALS['t_purges']    = [];

function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
function esc_attr_e( $s, $d = null ) { echo esc_attr( $s ); }
function esc_url( $u ) { return (string) $u; }
function esc_url_raw( $u, $p = null ) { $u = trim( (string) $u ); return preg_match( '#^https?://#i', $u ) ? $u : ''; }
function sanitize_text_field( $s ) { $s = strip_tags( (string) $s ); $s = preg_replace( '/[\r\n\t ]+/', ' ', $s ); return trim( $s ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_email( $s ) { $s = trim( (string) $s ); return filter_var( $s, FILTER_VALIDATE_EMAIL ) ? $s : ''; }
function is_email( $s ) { return (bool) filter_var( (string) $s, FILTER_VALIDATE_EMAIL ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }
function sanitize_html_class( $s ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $s ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_strip_all_tags( $s, $remove_breaks = false ) { $s = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $s ); $s = strip_tags( $s ); if ( $remove_breaks ) { $s = preg_replace( '/[\r\n\t ]+/', ' ', $s ); } return trim( $s ); }
function wp_check_invalid_utf8( $s ) { return mb_check_encoding( (string) $s, 'UTF-8' ) ? (string) $s : ''; }
function has_action( $t ) { return false; }
function do_action( $t ) {}
function nitropack_sdk_purge( $u = null, $t = null, $r = null ) { $GLOBALS['t_purges'][] = $r; return true; }
define( 'MINUTE_IN_SECONDS', 60 );
function remove_accents( $s ) { return iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $s ) ?: (string) $s; }
function wp_kses( $s, $allowed ) { return strip_tags( (string) $s, '<' . implode( '><', array_keys( $allowed ) ) . '>' ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function apply_filters( $tag, $v ) { return $v; }
function add_action() {}
function add_filter() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['t_options'] ) ? $GLOBALS['t_options'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['t_options'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['t_options'][ $k ] ); return true; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['t_post_meta'][ $id ][ $key ] ?? ''; }
function current_time( $t ) { return 'c' === $t ? '2026-11-25T12:00:00-05:00' : '2026-11-25 12:00:00'; }
function get_bloginfo( $k ) { return 'BT Law Group'; }
function home_url( $p = '' ) { return 'https://btattorneys.test' . $p; }
function admin_url( $p = '' ) { return 'https://btattorneys.test/wp-admin/' . $p; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_http_validate_url( $u ) { return (bool) preg_match( '#^https://#', (string) $u ); }
function wp_create_nonce( $a ) { return 'nonce'; }
function checked( $a, $b = true, $echo = true ) { $r = ( (string) $a === (string) $b ) ? ' checked="checked"' : ''; if ( $echo ) { echo $r; } return $r; }
function selected( $a, $b = true, $echo = true ) { $r = ( (string) $a === (string) $b ) ? ' selected="selected"' : ''; if ( $echo ) { echo $r; } return $r; }
function disabled( $a, $b = true, $echo = true ) { $r = ( (bool) $a === (bool) $b ) ? ' disabled="disabled"' : ''; if ( $echo ) { echo $r; } return $r; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function wp_get_current_user() { return new class { public $display_name = 'Admin'; public $user_email = 'a@b.test'; public function exists() { return true; } }; }
function wp_remote_post( $url, $args ) { $GLOBALS['t_http'][] = [ $url, $args ]; return [ 'response' => [ 'code' => 200 ] ]; }
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code'] ?? 0; }

class WP_Error {
	public $code; public $message; public $data;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

class WP_REST_Response {
	public $data; public $status; public $headers = [];
	public function __construct( $d = null, $s = 200 ) { $this->data = $d; $this->status = $s; }
	public function header( $k, $v ) { $this->headers[ $k ] = $v; }
}

class WP_REST_Request {
	private $json; private $params;
	public function __construct( $json = null, $params = [] ) { $this->json = $json; $this->params = $params; }
	public function get_json_params() { return $this->json; }
	public function get_body_params() { return []; }
	public function get_param( $k ) { return $this->params[ $k ] ?? null; }
}

