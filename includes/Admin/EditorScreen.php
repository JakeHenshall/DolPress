<?php
/**
 * Editor boot data and assets.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Admin;

use Nought\DolPress\Contracts\CommandRegistryInterface;
use Nought\DolPress\Support\SettingsRepository;

final class EditorScreen {
	public function __construct(
		private readonly SettingsRepository $settings,
		private readonly SafeMode $safe_mode,
		private readonly CommandRegistryInterface $commands
	) {}

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$replacement = new EditorReplacement( $this->settings, $this->safe_mode );
		if ( ! $replacement->should_replace( $post ) ) {
			return;
		}

		$script = DOLPRESS_PATH . 'assets/dist/editor.js';
		$style  = DOLPRESS_PATH . 'assets/dist/editor.css';
		$ver    = DOLPRESS_VERSION;
		if ( is_readable( $script ) ) {
			wp_enqueue_script( 'dolpress-editor', DOLPRESS_URL . 'assets/dist/editor.js', array( 'wp-api-fetch', 'wp-data', 'wp-element' ), $ver, true );
			wp_script_add_data( 'dolpress-editor', 'strategy', 'defer' );
		}

		if ( is_readable( $style ) ) {
			wp_enqueue_style( 'dolpress-editor', DOLPRESS_URL . 'assets/dist/editor.css', array(), $ver );
		}

		wp_add_inline_script(
			'dolpress-editor',
			'window.dolpressEditor = ' . wp_json_encode( $this->boot_data( $post ) ) . ';',
			'before'
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function boot_data( \WP_Post $post ): array {
		$user_mode = get_user_meta( get_current_user_id(), 'dolpress_editor_mode', true );
		$mode      = in_array( $user_mode, array( 'source', 'rendered', 'split' ), true )
			? $user_mode
			: (string) $this->settings->get( 'default_mode', 'source' );

		return array(
			'version'      => DOLPRESS_VERSION,
			'grammar'      => DOLPRESS_GRAMMAR_VERSION,
			'postId'       => $post->ID,
			'source'       => $post->post_content,
			'modifiedGmt'  => $post->post_modified_gmt,
			'mode'         => $mode,
			'settings'     => array(
				'strictDiagnostics' => (bool) $this->settings->get( 'strict_diagnostics', false ),
				'maxSourceBytes'    => (int) $this->settings->get( 'max_source_bytes', 102400 ),
			),
			'commands'     => $this->commands->schemas(),
			'rest'         => array(
				'root'    => esc_url_raw( rest_url() ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'preview' => rest_url( 'dolpress/v1/preview' ),
				'schema'  => rest_url( 'dolpress/v1/commands' ),
				'post'    => rest_url( 'wp/v2/' . $this->rest_base( $post ) . '/' . $post->ID ),
				'form'    => rest_url( 'dolpress/v1/form' ),
				'bins'    => rest_url( 'dolpress/v1/bins' ),
				'macro'   => rest_url( 'dolpress/v1/macro' ),
			),
			'capabilities' => array(
				'edit'    => current_user_can( 'edit_post', $post->ID ),
				'publish' => current_user_can( 'publish_post', $post->ID ),
			),
			'safeModeUrl'  => $this->safe_mode->bypass_url( $post->ID ),
			'previewUrl'   => get_preview_post_link( $post ),
			'workerUrl'    => DOLPRESS_URL . 'assets/dist/parser-worker.js',
			'strings'      => array(
				'source'   => __( 'Source', 'dolpress' ),
				'rendered' => __( 'Rendered', 'dolpress' ),
				'split'    => __( 'Split', 'dolpress' ),
				'save'     => __( 'Save', 'dolpress' ),
				'preview'  => __( 'Preview', 'dolpress' ),
				'palette'  => __( 'Insert command', 'dolpress' ),
				'recovery' => __( 'Open native editor (safe mode)', 'dolpress' ),
			),
		);
	}

	private function rest_base( \WP_Post $post ): string {
		$object = get_post_type_object( $post->post_type );
		if ( $object && ! empty( $object->rest_base ) ) {
			return (string) $object->rest_base;
		}

		return $post->post_type . 's';
	}
}
