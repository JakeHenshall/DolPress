<?php
/**
 * Command schema REST export.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Rest;

use Nought\DolPress\Contracts\CommandRegistryInterface;

final class CommandSchemaController {
	public function __construct( private readonly CommandRegistryInterface $commands ) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			'dolpress/v1',
			'/commands',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'schemas' ),
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
			)
		);
	}

	public function schemas(): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'grammar'  => DOLPRESS_GRAMMAR_VERSION,
				'commands' => $this->commands->schemas(),
			)
		);
	}
}
