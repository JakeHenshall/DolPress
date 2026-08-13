<?php
/**
 * Rendered output cache.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Rendering;

use Nought\DolPress\Support\SettingsRepository;

final class Cache {
	public const META_KEY = '_dolpress_render_cache';
	public const HASH_KEY = '_dolpress_render_hash';
	public const SNAP_KEY = '_dolpress_snapshot';

	public function __construct( private readonly SettingsRepository $settings ) {}

	public function register(): void {
		add_action( 'save_post', array( $this, 'invalidate_post' ) );
		add_action( 'deleted_post', array( $this, 'invalidate_post' ) );
		add_action( 'edited_term', array( $this, 'flush_all' ) );
		add_action( 'wp_update_nav_menu', array( $this, 'flush_all' ) );
		add_action( 'comment_post', array( $this, 'flush_all' ) );
		add_action( 'edit_comment', array( $this, 'flush_all' ) );
		add_action( 'updated_option', array( $this, 'on_option' ) );
	}

	public function enabled(): bool {
		return (bool) $this->settings->get( 'cache_enabled', true );
	}

	public function get( int $post_id, string $source, RenderContext $context ): ?string {
		if ( ! $this->enabled() || $context->is_preview ) {
			return null;
		}

		$hash   = $this->hash( $source, $context );
		$stored = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $stored ) || ( $stored['hash'] ?? '' ) !== $hash ) {
			return null;
		}

		return is_string( $stored['html'] ?? null ) ? $stored['html'] : null;
	}

	public function put( int $post_id, string $source, RenderContext $context, string $html ): void {
		if ( ! $this->enabled() || $context->is_preview || ( $context->user_id > 0 && ! $context->is_editor ) ) {
			return;
		}

		if ( $context->user_id > 0 ) {
			return;
		}

		$hash = $this->hash( $source, $context );
		update_post_meta(
			$post_id,
			self::META_KEY,
			array(
				'hash' => $hash,
				'html' => $html,
			)
		);
		update_post_meta( $post_id, self::HASH_KEY, $hash );
		if ( 'snapshot' === $this->settings->get( 'fallback_behaviour', 'escaped' ) ) {
			update_post_meta( $post_id, self::SNAP_KEY, $html );
		}
	}

	public function invalidate_post( int $post_id ): void {
		delete_post_meta( $post_id, self::META_KEY );
		delete_post_meta( $post_id, self::HASH_KEY );
	}

	public function flush_all(): void {
		delete_post_meta_by_key( self::META_KEY );
		delete_post_meta_by_key( self::HASH_KEY );
	}

	public function on_option( string $option ): void {
		if ( in_array( $option, array( 'blogname', 'blogdescription', 'site_icon', 'home', 'siteurl' ), true ) ) {
			$this->flush_all();
		}
	}

	public function snapshot( int $post_id ): string {
		$value = get_post_meta( $post_id, self::SNAP_KEY, true );
		return is_string( $value ) ? $value : '';
	}

	private function hash( string $source, RenderContext $context ): string {
		return hash( 'sha256', DOLPRESS_VERSION . '|html-p|' . $source . '|' . wp_json_encode( $context->cache_key_parts() ) );
	}
}
