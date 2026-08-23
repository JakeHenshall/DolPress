<?php
/**
 * Preview and persist REST endpoints.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Rest;

use Nought\DolPress\Contracts\ParserInterface;
use Nought\DolPress\Contracts\RendererInterface;
use Nought\DolPress\Rendering\RenderContext;
use Nought\DolPress\Support\SettingsRepository;

final class PreviewController {
	private const RATE_LIMIT_MAX_HITS  = 30;
	private const RATE_LIMIT_WINDOW    = 60;

	public function __construct(
		private readonly SettingsRepository $settings,
		private readonly RendererInterface $renderer,
		private readonly ?ParserInterface $parser = null
	) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			'dolpress/v1',
			'/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( $this, 'can_preview' ),
				'args'                => array(
					'postId' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'source' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			'dolpress/v1',
			'/parse',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'parse' ),
				'permission_callback' => array( $this, 'can_preview' ),
				'args'                => array(
					'postId' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'source' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			'dolpress/v1',
			'/mode',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_mode' ),
				'permission_callback' => static fn() => is_user_logged_in(),
				'args'                => array(
					'mode' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	public function can_preview( \WP_REST_Request $request ): bool {
		$post_id = (int) $request->get_param( 'postId' );
		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	public function preview( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$source = (string) $request->get_param( 'source' );
		$source = $this->enforce_source_cap( $source );
		if ( null === $source ) {
			return new \WP_Error(
				'dolpress_source_too_large',
				__( 'Source exceeds the maximum document size.', 'dolpress' ),
				array( 'status' => 413 )
			);
		}

		if ( ! $this->allow_request( 'preview' ) ) {
			return new \WP_Error(
				'dolpress_rate_limited',
				__( 'Too many render requests; try again shortly.', 'dolpress' ),
				array( 'status' => 429 )
			);
		}

		$post_id = (int) $request->get_param( 'postId' );

		$context = RenderContext::for_post( $post_id, true, true );
		$result  = $this->renderer->render( $source, $context );

		return new \WP_REST_Response(
			array(
				'html'        => $result->html,
				'diagnostics' => array_map( static fn( $item ) => $item->to_array(), $result->diagnostics ),
			)
		);
	}

	public function parse( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! $this->allow_request( 'parse' ) ) {
			return new \WP_Error(
				'dolpress_rate_limited',
				__( 'Too many parse requests; try again shortly.', 'dolpress' ),
				array( 'status' => 429 )
			);
		}

		$source = (string) $request->get_param( 'source' );
		$source = $this->enforce_source_cap( $source );
		if ( null === $source ) {
			return new \WP_Error(
				'dolpress_source_too_large',
				__( 'Source exceeds the maximum document size.', 'dolpress' ),
				array( 'status' => 413 )
			);
		}

		$parsed = $this->parser
			? $this->parser->parse( $source )
			: ( \Nought\DolPress\Plugin::instance()?->parser()->parse( $source ) ?? null );

		return new \WP_REST_Response( $parsed ? $parsed->to_array() : array() );
	}

	public function save_mode( \WP_REST_Request $request ): \WP_REST_Response {
		$mode = sanitize_key( (string) $request->get_param( 'mode' ) );
		if ( ! in_array( $mode, array( 'source', 'rendered', 'split' ), true ) ) {
			return new \WP_REST_Response( array( 'ok' => false ), 400 );
		}

		update_user_meta( get_current_user_id(), 'dolpress_editor_mode', $mode );
		return new \WP_REST_Response(
			array(
				'ok'   => true,
				'mode' => $mode,
			)
		);
	}

	/**
	 * Applies the configured source cap at the API boundary instead of relying
	 * on the lexer two layers down.
	 *
	 * @return string|null The capped source, or null when it exceeds the cap outright.
	 */
	private function enforce_source_cap( string $source ): ?string {
		$max = (int) $this->settings->get( 'max_source_bytes', 102400 );
		if ( strlen( $source ) > $max ) {
			return null;
		}

		return $source;
	}

	/**
	 * Fixed-window per-user throttle for compute-heavy endpoints. Best effort:
	 * object-cache-backed transients make this per-site under a shared cache.
	 */
	private function allow_request( string $bucket ): bool {
		$user_id  = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		$identity = $user_id > 0
			? 'u' . $user_id
			: substr( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ), 0, 45 );

		if ( '' === $identity ) {
			return true;
		}

		$key      = 'dolpress_rl_' . md5( $bucket . '|' . $identity );
		$now      = time();
		$existing = get_transient( $key );
		$window   = is_array( $existing ) && isset( $existing['start'], $existing['n'] ) ? $existing : array(
			'start' => $now,
			'n'     => 0,
		);

		if ( $now - (int) $window['start'] >= self::RATE_LIMIT_WINDOW ) {
			$window = array(
				'start' => $now,
				'n'     => 0,
			);
		}

		++$window['n'];
		set_transient( $key, $window, self::RATE_LIMIT_WINDOW + 5 );

		return $window['n'] <= self::RATE_LIMIT_MAX_HITS;
	}
}
