<?php
/**
 * Preview and persist REST endpoints.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Rest;

use Nought\DolPress\Contracts\RendererInterface;
use Nought\DolPress\Rendering\RenderContext;
use Nought\DolPress\Support\SettingsRepository;

final class PreviewController {
	public function __construct(
		private readonly SettingsRepository $settings,
		private readonly RendererInterface $renderer
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

	public function preview( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request->get_param( 'postId' );
		$source  = (string) $request->get_param( 'source' );
		$max     = (int) $this->settings->get( 'max_source_bytes', 102400 );
		if ( strlen( $source ) > $max ) {
			$source = substr( $source, 0, $max );
		}

		$context = RenderContext::for_post( $post_id, true, true );
		$result  = $this->renderer->render( $source, $context );

		return new \WP_REST_Response(
			array(
				'html'        => $result->html,
				'diagnostics' => array_map( static fn( $item ) => $item->to_array(), $result->diagnostics ),
			)
		);
	}

	public function parse( \WP_REST_Request $request ): \WP_REST_Response {
		$plugin = \Nought\DolPress\Plugin::instance();
		$source = (string) $request->get_param( 'source' );
		$parsed = $plugin ? $plugin->parser()->parse( $source ) : null;

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
}
