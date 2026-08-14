<?php
/**
 * Form field persistence and document bins / named macros.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Rest;

use Nought\DolPress\Commands\ActionRegistry;
use Nought\DolPress\Support\BinStore;
use Nought\DolPress\Support\SettingsRepository;

final class DocumentController {
	public function __construct(
		private readonly SettingsRepository $settings,
		private readonly BinStore $bins,
		private readonly ActionRegistry $actions
	) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			'dolpress/v1',
			'/form',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_form' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);
		register_rest_route(
			'dolpress/v1',
			'/bins',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_bins' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_bins' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);
		register_rest_route(
			'dolpress/v1',
			'/macro',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'run_macro' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);
	}

	public function can_edit( \WP_REST_Request $request ): bool {
		$post_id = (int) $request->get_param( 'postId' );
		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	public function save_form( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request->get_param( 'postId' );
		$values  = $request->get_param( 'values' );
		if ( ! is_array( $values ) ) {
			return new \WP_REST_Response( array( 'ok' => false ), 400 );
		}

		$saved = array();
		foreach ( array_slice( $values, 0, 100, true ) as $key => $value ) {
			$key = is_string( $key ) ? $key : (string) $key;
			if ( ! $this->settings->is_meta_key_allowed( $key ) || str_starts_with( $key, '_' ) ) {
				continue;
			}
			if ( is_array( $value ) || is_object( $value ) ) {
				continue;
			}
			$clean = sanitize_text_field( substr( (string) $value, 0, 4096 ) );
			update_post_meta( $post_id, $key, $clean );
			$saved[] = $key;
		}

		return new \WP_REST_Response(
			array(
				'ok'    => true,
				'saved' => $saved,
			)
		);
	}

	public function get_bins( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request->get_param( 'postId' );
		$bins    = array();
		foreach ( $this->bins->all( $post_id ) as $item ) {
			$bins[] = array(
				'num'  => $item['num'],
				'tag'  => $item['tag'],
				'data' => base64_encode( $item['data'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary sprite transport.
			);
		}

		return new \WP_REST_Response( array( 'bins' => $bins ) );
	}

	public function save_bins( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request->get_param( 'postId' );
		$raw     = $request->get_param( 'bins' );
		if ( ! is_array( $raw ) ) {
			return new \WP_REST_Response( array( 'ok' => false ), 400 );
		}

		$bins = array();
		$max  = (int) $this->settings->get( 'max_media_count', 40 );
		foreach ( array_slice( $raw, 0, $max ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$data   = (string) ( $item['data'] ?? '' );
			$bin    = base64_decode( $data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- binary sprite transport.
			$bins[] = array(
				'num'  => (int) ( $item['num'] ?? 0 ),
				'tag'  => (string) ( $item['tag'] ?? '' ),
				'data' => is_string( $bin ) && '' !== $bin ? $bin : $data,
			);
		}

		$ok = $this->bins->put( $post_id, $bins );

		return new \WP_REST_Response( array( 'ok' => $ok ) );
	}

	public function run_macro( \WP_REST_Request $request ): \WP_REST_Response {
		$name    = ActionRegistry::sanitise_name( (string) $request->get_param( 'name' ) );
		$payload = $request->get_param( 'payload' );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$payload['postId'] = (int) $request->get_param( 'postId' );

		if ( $this->actions->is_builtin( $name ) ) {
			return new \WP_REST_Response(
				array(
					'ok'      => true,
					'builtin' => true,
				)
			);
		}

		$ok = $this->actions->dispatch( $name, $payload );

		return new \WP_REST_Response(
			array(
				'ok' => $ok,
			),
			$ok ? 200 : 403
		);
	}
}
