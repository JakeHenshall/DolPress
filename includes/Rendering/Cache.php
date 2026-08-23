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
	public const META_KEY          = '_dolpress_render_cache';
	public const HASH_KEY          = '_dolpress_render_hash';
	public const SNAP_KEY          = '_dolpress_snapshot';
	private const VERSION_PREFIX   = 'dolpress_cache_version_';
	private const DEPENDENCY_TYPES = array( 'attachment', 'bin', 'comment', 'menu', 'meta', 'option', 'post', 'term', 'user' );

	/** @var array<string, true> */
	private array $bumped = array();

	public function __construct( private readonly SettingsRepository $settings ) {}

	public function register(): void {
		add_action( 'save_post', array( $this, 'invalidate_post' ) );
		add_action( 'deleted_post', array( $this, 'invalidate_post' ) );
		add_action( 'edited_term', fn() => $this->bump( 'term' ) );
		add_action( 'created_term', fn() => $this->bump( 'term' ) );
		add_action( 'delete_term', fn() => $this->bump( 'term' ) );
		add_action( 'wp_update_nav_menu', fn() => $this->bump( 'menu' ) );
		add_action( 'comment_post', fn() => $this->bump( 'comment' ) );
		add_action( 'edit_comment', fn() => $this->bump( 'comment' ) );
		add_action( 'deleted_comment', fn() => $this->bump( 'comment' ) );
		add_action( 'profile_update', fn() => $this->bump( 'user' ) );
		add_action( 'added_post_meta', array( $this, 'on_post_meta' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'on_post_meta' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'on_post_meta' ), 10, 4 );
		add_action( 'updated_option', array( $this, 'on_option' ) );
	}

	public function enabled(): bool {
		return (bool) $this->settings->get( 'cache_enabled', true );
	}

	public function get( int $post_id, string $source, RenderContext $context ): ?string {
		if ( ! $this->enabled() || $context->is_preview ) {
			return null;
		}

		$stored = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $stored ) || ! isset( $stored['dependencies'] ) || ! is_array( $stored['dependencies'] ) ) {
			return null;
		}

		if ( ! isset( $stored['v'] ) || 'meta-keys' !== $stored['v'] ) {
			return null; // Cache entry predates per-key invalidation.
		}

		$hash = $this->hash( $source, $context, $stored['dependencies'] );
		if ( ( $stored['hash'] ?? '' ) !== $hash ) {
			return null;
		}

		return is_string( $stored['html'] ?? null ) ? $stored['html'] : null;
	}

	/**
	 * @param array<string, mixed> $dependencies
	 */
	public function put( int $post_id, string $source, RenderContext $context, string $html, array $dependencies = array() ): void {
		if ( ! $this->enabled() || $context->is_preview || ( $context->user_id > 0 && ! $context->is_editor ) ) {
			return;
		}

		if ( $context->user_id > 0 ) {
			return;
		}

		$types = $this->dependency_types( $dependencies );
		$hash  = $this->hash( $source, $context, $types );
		update_post_meta(
			$post_id,
			self::META_KEY,
			array(
				'v'            => 'meta-keys',
				'hash'         => $hash,
				'html'         => $html,
				'dependencies' => $types,
			)
		);
		update_post_meta( $post_id, self::HASH_KEY, $hash );
		if ( 'snapshot' === $this->settings->get( 'fallback_behaviour', 'escaped' ) ) {
			update_post_meta( $post_id, self::SNAP_KEY, $html );
		}
	}

	public function invalidate_post( int $post_id ): void {
		if ( ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) || ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) ) ) {
			return;
		}

		delete_post_meta( $post_id, self::META_KEY );
		delete_post_meta( $post_id, self::HASH_KEY );
		$this->bump( 'post' );
		if ( 'attachment' === get_post_type( $post_id ) ) {
			$this->bump( 'attachment' );
		}
	}

	public function flush_all(): void {
		foreach ( self::DEPENDENCY_TYPES as $type ) {
			$this->bump( $type );
		}
	}

	public function on_option( string $option ): void {
		if ( SettingsRepository::OPTION_KEY === $option ) {
			$this->settings->flush_cache();
			$this->flush_all();
			return;
		}

		if ( in_array( $option, array( 'blogname', 'blogdescription', 'site_icon', 'home', 'siteurl' ), true ) ) {
			$this->bump( 'option' );
		}
	}

	public function on_post_meta( mixed $meta_id, int $post_id, string $meta_key, mixed $meta_value = null ): void {
		unset( $meta_id, $post_id, $meta_value );
		if ( in_array( $meta_key, array( self::META_KEY, self::HASH_KEY, self::SNAP_KEY ), true ) ) {
			return;
		}

		if ( '_dolpress_bins' === $meta_key ) {
			$this->bump( 'bin' );
			return;
		}

		if ( str_starts_with( $meta_key, '_' ) ) {
			return; // Private meta never appears in public render output.
		}

		// Public keys invalidate per key; unrelated keys no longer flush every cached document.
		$key = sanitize_key( $meta_key );
		if ( '' === $key || strlen( $key ) > 64 ) {
			$this->bump( 'meta' );
			return;
		}
		$this->bump( 'meta:' . $key );
	}

	public function snapshot( int $post_id ): string {
		$value = get_post_meta( $post_id, self::SNAP_KEY, true );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * @param list<string> $dependencies
	 */
	private function hash( string $source, RenderContext $context, array $dependencies ): string {
		$versions = array();
		foreach ( $dependencies as $type ) {
			$versions[ $type ] = $this->version( $type );
		}

		return hash( 'sha256', DOLPRESS_VERSION . '|html-flow|' . $source . '|' . wp_json_encode( $context->cache_key_parts() ) . '|' . wp_json_encode( $versions ) );
	}

	/**
	 * @param array<string, mixed> $dependencies
	 * @return list<string>
	 */
	private function dependency_types( array $dependencies ): array {
		$types   = array_values( array_intersect( array_keys( $dependencies ), self::DEPENDENCY_TYPES ) );
		$types[] = 'option';
		$types   = array_values( array_unique( $types ) );
		sort( $types );
		return $types;
	}

	private function version( string $type ): int {
		if ( ! str_starts_with( $type, 'meta:' ) ) {
			if ( ! in_array( $type, self::DEPENDENCY_TYPES, true ) ) {
				return 1;
			}

			return max( 1, (int) get_option( self::VERSION_PREFIX . $type, 1 ) );
		}

		// Per-meta-key counters fall back to the global meta counter so legacy
		// flush_all() invalidation still takes effect for keyed documents.
		$local = (int) get_option( self::VERSION_PREFIX . $type, 1 );
		$base  = (int) get_option( self::VERSION_PREFIX . 'meta', 1 );

		return max( 1, $local + $base );
	}

	private function bump( string $type ): void {
		if ( isset( $this->bumped[ $type ] ) ) {
			return;
		}
		if ( ! in_array( $type, self::DEPENDENCY_TYPES, true ) && ! str_starts_with( $type, 'meta:' ) ) {
			return;
		}

		$this->bumped[ $type ] = true;
		$key                   = self::VERSION_PREFIX . $type;
		update_option( $key, (int) get_option( $key, 1 ) + 1, false );
	}
}
