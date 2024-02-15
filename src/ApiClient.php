<?php

namespace CF\EntCache;

use JsonException;
use WP_Error;

/**
 * @method static ApiResponse purgeCache( array $data )
 */
readonly class ApiClient {

	public static function getInstance() {
		static $instance;

		if ( ! isset( $instance ) ) {
			$instance = new self( ... apply_filters( 'cf/ent_cache/api_credentials', [ '', '', '' ] ) );
		}

		return $instance;
	}

	public static function __callStatic( string $name, array $arguments ) {
		return self::getInstance()->$name( ... $arguments );
	}

	private const ZONES = 'https://api.cloudflare.com/client/v4/zones/';

	public function __construct(
		private string $zoneId,
        private string $email,
		private string $apiKey,
	) {
	}

	/**
	 * Send a request to Cloudflare API to purge cached resources.
	 *
	 * @param array{
	 *      files?: string[],
	 *      hosts?: string[],
	 *      prefixes?: string[],
	 *      tags?: string[]
	 *  } $data An associative array with optional keys 'files', 'hosts', 'prefixes', and 'tags'.
	 *          Each key, if present, should have an array with maximum of 30 string values.
	 */
	public function purgeCache( array $data ): ApiResponse {
		try {
			$body = json_encode( $data, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			return ApiResponse::fromException( $e )->addMessage( 'json_encode_error','Unable to encode request.' );
		}

		$response = wp_remote_post( self::ZONES . $this->zoneId . '/purge_cache', [
			'headers' => [
				'X-Auth-Email' => $this->email,
				'X-Auth-Key'   => $this->apiKey,
				'Content-Type' => 'application/json'
			],
			'body'    => $body,
		] );

		if ($response instanceof WP_Error) {
			return ApiResponse::fromWpError($response)->addMessage('request_error', 'Request failed.');
		}

		$responseCode = wp_remote_retrieve_response_code($response);

		if ( $responseCode === 429 ) {
			return ApiResponse::asFailure()
			                  ->addError('cloudflare_rate_limit', 'Cloudflare rate limit reached.')
			                  ->addMessage('retry', (int) wp_remote_retrieve_header($response, 'Retry-After'));
		}

		if ( $responseCode !== 200 ) {
			return ApiResponse::asFailure()->addMessage($responseCode, 'Request failed.');
		}

		try {
			$stdClass = json_decode( wp_remote_retrieve_body( $response ), false, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			return ApiResponse::fromException( $e )->addMessage( 'json_decode_error', 'Unable to decode response.' );
		}

		return ApiResponse::fromStdClass( $stdClass );
	}
}

