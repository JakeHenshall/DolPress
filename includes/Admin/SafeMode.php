<?php
/**
 * Emergency disable and per-request safe mode.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Admin;

use Nought\DolPress\Support\SettingsRepository;

final class SafeMode {
	public const QUERY = 'dolpress_safe';

	public function __construct( private readonly SettingsRepository $settings ) {}

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_filter( 'plugin_action_links_' . DOLPRESS_BASENAME, array( $this, 'action_links' ) );
	}

	public function is_globally_disabled(): bool {
		return $this->settings->is_globally_disabled();
	}

	public function is_request_safe(): bool {
		if ( $this->is_globally_disabled() ) {
			return true;
		}

		if ( empty( $_GET[ self::QUERY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return wp_verify_nonce( $nonce, 'dolpress_safe_mode' );
	}

	public function bypass_url( int $post_id = 0 ): string {
		$args = array(
			self::QUERY => '1',
			'_wpnonce'  => wp_create_nonce( 'dolpress_safe_mode' ),
		);

		if ( $post_id > 0 ) {
			$args['post']   = $post_id;
			$args['action'] = 'edit';
			return add_query_arg( $args, admin_url( 'post.php' ) );
		}

		return add_query_arg( $args, admin_url( 'options-general.php?page=dolpress' ) );
	}

	public function notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( $this->is_globally_disabled() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'DolPress emergency disable is on. Gutenberg or the Classic Editor is restored until you turn this off.', 'dolpress' ) . '</p></div>';
		}

		if ( $this->is_request_safe() && ! $this->is_globally_disabled() ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'DolPress safe mode is active for this request.', 'dolpress' ) . '</p></div>';
		}
	}

	/**
	 * @param array<string, string> $links
	 * @return array<string, string>
	 */
	public function action_links( array $links ): array {
		$links['settings'] = '<a href="' . esc_url( admin_url( 'options-general.php?page=dolpress' ) ) . '">' . esc_html__( 'Settings', 'dolpress' ) . '</a>';
		return $links;
	}
}
