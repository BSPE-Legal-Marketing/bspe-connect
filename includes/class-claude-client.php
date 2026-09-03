<?php
/**
 * Minimal Claude Messages API client for the translation feature.
 *
 * @package BSPE\Connect
 */

namespace BSPE\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to https://api.anthropic.com/v1/messages with wp_remote_post, the
 * same way Licensing and the Turnstile check already do HTTP. The PHP SDK
 * is not vendored in this plugin, so the request body is built by hand
 * following the current Messages API shape (adaptive thinking by default,
 * effort control). The model is asked for a JSON object keyed like the
 * input, and every key is checked on the way back.
 */
final class Claude_Client {

	public const ENDPOINT    = 'https://api.anthropic.com/v1/messages';
	public const API_VERSION = '2023-06-01';

	public const MODELS = [
		'claude-opus-5'   => 'Claude Opus 5 (best quality)',
		'claude-sonnet-5' => 'Claude Sonnet 5 (faster, cheaper)',
	];

	public const LANGUAGES = [
		'es' => 'Spanish',
	];

	/** Human language names for the prompt, keyed by WPML code. */
	private const LANGUAGE_NAMES = [
		'en' => 'English',
		'es' => 'Spanish',
		'fr' => 'French',
		'pt' => 'Portuguese',
		'de' => 'German',
		'it' => 'Italian',
		'zh' => 'Chinese',
		'ru' => 'Russian',
		'ar' => 'Arabic',
		'ko' => 'Korean',
		'vi' => 'Vietnamese',
	];

	private string $api_key;
	private string $model;

	public function __construct( string $api_key, string $model ) {
		$this->api_key = $api_key;
		$this->model   = array_key_exists( $model, self::MODELS ) ? $model : 'claude-opus-5';
	}

	/** Client built from the saved plugin settings, or null when no key. */
	public static function from_settings(): ?Claude_Client {
		$key = (string) Settings::get( 'translate.api_key', '' );
		if ( '' === $key ) {
			return null;
		}
		return new self( $key, (string) Settings::get( 'translate.model', 'claude-opus-5' ) );
	}

	public static function language_name( string $code ): string {
		return self::LANGUAGE_NAMES[ $code ] ?? strtoupper( $code );
	}

	/**
	 * Translate a batch of segments. Every input id must come back.
	 *
	 * @param array<string,string> $segments id => source text
	 * @param array{firm_name?: string}   $opts
	 *
	 * @return array{translations: array<string,string>, usage: array{input: int, output: int}}|\WP_Error
	 */
	public function translate_batch( array $segments, string $target, string $source, array $opts = [] ) {
		if ( empty( $segments ) ) {
			return [ 'translations' => [], 'usage' => [ 'input' => 0, 'output' => 0 ] ];
		}

		$body = [
			'model'         => $this->model,
			'max_tokens'    => 16000,
			'system'        => self::system_prompt( $target, $source, $opts ),
			'output_config' => [ 'effort' => 'medium' ],
			'messages'      => [
				[
					'role'    => 'user',
					'content' => "Translate every value in this JSON object. Reply with one JSON object only (no code fence, no commentary) that has exactly the same keys, each mapped to its translation.\n\n"
						. wp_json_encode( $segments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				],
			],
		];

		$attempt = 0;
		do {
			$attempt++;
			$response = wp_remote_post(
				self::ENDPOINT,
				[
					'timeout' => 180,
					'headers' => [
						'content-type'      => 'application/json',
						'x-api-key'         => $this->api_key,
						'anthropic-version' => self::API_VERSION,
					],
					'body'    => wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				]
			);

			if ( is_wp_error( $response ) ) {
				return new \WP_Error( 'http', 'Could not reach the Claude API: ' . $response->get_error_message() );
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( in_array( $code, [ 429, 500, 502, 503, 529 ], true ) && $attempt < 3 ) {
				sleep( 2 * $attempt );
				continue;
			}
			break;
		} while ( true );

		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $data ) ? (string) ( $data['error']['message'] ?? '' ) : '';
			if ( '' === $msg ) {
				$msg = 'HTTP ' . $code;
			}
			if ( 401 === $code ) {
				$msg = 'The Claude API key was rejected (401). Check the key under Translate settings.';
			}
			return new \WP_Error( 'api_' . $code, $msg );
		}
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'bad_json', 'The Claude API returned a non-JSON response.' );
		}

		$stop = (string) ( $data['stop_reason'] ?? '' );
		if ( 'refusal' === $stop ) {
			$why = (string) ( $data['stop_details']['explanation'] ?? '' );
			return new \WP_Error( 'refused', 'Claude declined to translate this batch.' . ( '' !== $why ? ' ' . $why : '' ) );
		}
		if ( 'max_tokens' === $stop ) {
			return new \WP_Error( 'max_tokens', 'The batch was too long for one response. Try again; the plugin will use smaller batches.' );
		}

		$text = '';
		foreach ( (array) ( $data['content'] ?? [] ) as $block ) {
			if ( is_array( $block ) && 'text' === ( $block['type'] ?? '' ) ) {
				$text .= (string) ( $block['text'] ?? '' );
			}
		}
		$out = json_decode( trim( $text ), true );
		if ( ! is_array( $out ) ) {
			// Tolerate a code fence or a sentence around the object.
			$open  = strpos( $text, '{' );
			$close = strrpos( $text, '}' );
			if ( false !== $open && false !== $close && $close > $open ) {
				$out = json_decode( substr( $text, $open, $close - $open + 1 ), true );
			}
		}
		if ( ! is_array( $out ) ) {
			return new \WP_Error( 'bad_response', 'Claude did not return valid JSON for this batch.' );
		}

		$translations = [];
		$missing      = [];
		foreach ( $segments as $id => $src ) {
			$val = $out[ $id ] ?? null;
			if ( ! is_string( $val ) || '' === trim( $val ) ) {
				$missing[] = $id;
				continue;
			}
			$translations[ $id ] = $val;
		}
		if ( ! empty( $missing ) ) {
			return new \WP_Error(
				'bad_response',
				sprintf( 'Claude left %d segment(s) untranslated (%s).', count( $missing ), implode( ', ', array_slice( $missing, 0, 5 ) ) )
			);
		}

		return [
			'translations' => $translations,
			'usage'        => [
				'input'  => (int) ( $data['usage']['input_tokens'] ?? 0 ),
				'output' => (int) ( $data['usage']['output_tokens'] ?? 0 ),
			],
		];
	}

	/**
	 * @param array{firm_name?: string} $opts
	 */
	public static function system_prompt( string $target, string $source, array $opts ): string {
		$target_name = self::language_name( $target );
		$source_name = self::language_name( $source );
		$firm        = trim( (string) ( $opts['firm_name'] ?? '' ) );

		$lines = [
			"You are a professional {$source_name} to {$target_name} translator for a law firm's marketing website.",
			"Translate for a general audience in the United States: natural, fluent, persuasive, and legally accurate {$target_name}, the register a reputable law firm would publish. Prefer widely understood terms over regional slang.",
			'Each value you receive may contain HTML, WordPress block comments, shortcodes, or Elementor markup. Keep every tag, attribute, class, id, href, src, shortcode, block comment, and placeholder (such as {firm_name}) exactly as it is; translate only the human-readable text between tags and inside alt, title, and placeholder attributes.',
			'Never translate or alter URLs, email addresses, phone numbers, prices, dates written as digits, brand names, product names, statute names and case citations in their official form, or personal names.',
			'Translate the key named "slug" as a short lowercase URL slug in the target language using plain words separated by spaces (no accents needed, they will be normalized).',
			'Keep meta titles and descriptions (keys starting with "meta:") roughly the same length as the source so they still fit search result snippets.',
			'Do not add notes, explanations, or quotation marks. Do not shorten or summarize. Return only the JSON object.',
		];
		if ( '' !== $firm ) {
			$lines[] = "The firm's name is \"{$firm}\". Never translate it.";
		}
		return implode( "\n", $lines );
	}
}
