<?php
/**
 * Supported WordPress editor replacement.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Admin;

use Nought\DolPress\Support\PostTypePolicy;
use Nought\DolPress\Support\SettingsRepository;

final class EditorReplacement {
	public function __construct(
		private readonly SettingsRepository $settings,
		private readonly SafeMode $safe_mode
	) {}

	public function register(): void {
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_block_editor_type' ), 100, 2 );
		add_filter( 'use_block_editor_for_post', array( $this, 'disable_block_editor_post' ), 100, 2 );
		add_filter( 'replace_editor', array( $this, 'replace_editor' ), 10, 2 );
	}

	public function should_replace( \WP_Post $post ): bool {
		if ( $this->safe_mode->is_globally_disabled() || $this->safe_mode->is_request_safe() ) {
			return false;
		}

		if ( PostTypePolicy::is_blocked( $post->post_type ) ) {
			return false;
		}

		if ( ! $this->settings->is_enabled_post_type( $post->post_type ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * @param bool   $use
	 * @param string $post_type
	 */
	public function disable_block_editor_type( $use, $post_type ): bool {
		if ( $this->safe_mode->is_globally_disabled() || $this->safe_mode->is_request_safe() ) {
			return (bool) $use;
		}

		if ( is_string( $post_type ) && $this->settings->is_enabled_post_type( $post_type ) ) {
			return false;
		}

		return (bool) $use;
	}

	/**
	 * @param bool              $use
	 * @param \WP_Post|int|null $post
	 */
	public function disable_block_editor_post( $use, $post ): bool {
		$resolved = $post instanceof \WP_Post ? $post : get_post( $post );
		if ( $resolved instanceof \WP_Post && $this->should_replace( $resolved ) ) {
			return false;
		}

		return (bool) $use;
	}

	/**
	 * @param bool     $replace
	 * @param \WP_Post $post
	 */
	public function replace_editor( $replace, $post ): bool {
		if ( ! $post instanceof \WP_Post || ! $this->should_replace( $post ) ) {
			return (bool) $replace;
		}

		add_action( 'edit_form_after_title', array( $this, 'mount' ) );
		add_action(
			'add_meta_boxes',
			function () use ( $post ): void {
				remove_post_type_support( $post->post_type, 'editor' );
			},
			0
		);

		return false;
	}

	public function mount( \WP_Post $post ): void {
		if ( ! $this->should_replace( $post ) ) {
			return;
		}

		echo '<div id="dolpress-editor-root" class="dolpress-editor-root" data-post-id="' . esc_attr( (string) $post->ID ) . '">';
		echo '<noscript><p>' . esc_html__( 'DolPress requires JavaScript. Use safe mode to restore the native editor.', 'dolpress' ) . '</p></noscript>';
		echo '</div>';
		echo '<textarea id="content" name="content" class="dolpress-source-fallback screen-reader-text">' . esc_textarea( $post->post_content ) . '</textarea>';
	}
}
