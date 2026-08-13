<?php
/**
 * Frontend the_content integration.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Rendering;

use Nought\DolPress\Admin\SafeMode;
use Nought\DolPress\Contracts\RendererInterface;
use Nought\DolPress\Support\SettingsRepository;

final class Frontend {
	private bool $rendering = false;

	public function __construct(
		private readonly SettingsRepository $settings,
		private readonly RendererInterface $renderer,
		private readonly SafeMode $safe_mode,
		private readonly Cache $cache
	) {}

	public function register(): void {
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function filter_content( string $content ): string {
		if ( $this->rendering || ! is_singular() || is_admin() ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post instanceof \WP_Post || ! $this->settings->is_enabled_post_type( $post->post_type ) ) {
			return $content;
		}

		if ( $this->safe_mode->is_globally_disabled() ) {
			return $this->fallback( $content, $post->ID );
		}

		$this->rendering = true;
		try {
			$context = RenderContext::for_post( $post->ID, is_preview(), false );
			$context = apply_filters( 'dolpress/render_context', $context, $post );
			$cached  = $this->cache->get( $post->ID, $post->post_content, $context );
			if ( is_string( $cached ) ) {
				return $cached;
			}

			$result = $this->renderer->render( $post->post_content, $context );
			if ( '' === trim( $result->html ) ) {
				return $this->fallback( $content, $post->ID );
			}

			$this->cache->put( $post->ID, $post->post_content, $context, $result->html );

			return $result->html;
		} catch ( \Throwable $exception ) {
			return $this->fallback( $content, $post->ID );
		} finally {
			$this->rendering = false;
		}
	}

	public function enqueue(): void {
		if ( is_admin() ) {
			return;
		}

		$path = DOLPRESS_PATH . 'assets/dist/frontend.css';
		if ( ! is_readable( $path ) ) {
			return;
		}

		wp_enqueue_style(
			'dolpress-frontend',
			DOLPRESS_URL . 'assets/dist/frontend.css',
			array(),
			DOLPRESS_VERSION
		);
	}

	private function fallback( string $source, int $post_id ): string {
		$behaviour = (string) $this->settings->get( 'fallback_behaviour', 'escaped' );
		if ( 'snapshot' === $behaviour ) {
			$snapshot = get_post_meta( $post_id, Cache::SNAP_KEY, true );
			if ( is_string( $snapshot ) && '' !== $snapshot ) {
				return Html::kses( $snapshot );
			}
		}

		$notice = '';
		if ( current_user_can( 'edit_post', $post_id ) ) {
			$notice = '<p class="dolpress-fallback-notice">' . esc_html__( 'DolPress is showing escaped source because rendering is unavailable.', 'dolpress' ) . '</p>';
		}

		return $notice . '<pre class="dolpress-fallback">' . esc_html( $source ) . '</pre>';
	}
}
