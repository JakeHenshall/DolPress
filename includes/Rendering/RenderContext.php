<?php
/**
 * Render-time context and resource visibility.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Rendering;

final class RenderContext {
	public int $query_count = 0;
	public int $media_count = 0;
	public int $depth       = 0;

	/**
	 * @var array<string, list<int|string>>
	 */
	public array $dependencies = array();

	/**
	 * @param array<int, bool> $seen_posts
	 */
	public function __construct(
		public readonly int $post_id,
		public readonly bool $is_preview,
		public readonly bool $is_editor,
		public readonly int $user_id,
		public array $seen_posts = array()
	) {
		if ( $post_id > 0 ) {
			$this->seen_posts[ $post_id ] = true;
		}
	}

	/** @var array<string, int> */
	public array $anchor_ids = array();

	public static function for_post( int $post_id, bool $is_preview = false, bool $is_editor = false ): self {
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

		return new self( $post_id, $is_preview, $is_editor, $user_id );
	}

	public function can_view_post( int $id ): bool {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$status = get_post_status( $post );
		if ( post_password_required( $post ) ) {
			return $this->is_preview && current_user_can( 'edit_post', $id );
		}

		if ( 'publish' === $status ) {
			return true;
		}

		return $this->is_preview && current_user_can( 'edit_post', $id );
	}

	public function can_view_attachment( int $id ): bool {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
			return false;
		}

		$parent = (int) $post->post_parent;
		if ( $parent > 0 ) {
			return $this->can_view_post( $parent );
		}

		return current_user_can( 'read_post', $id ) || 'publish' === get_post_status( $post );
	}

	public function note_dependency( string $type, int|string $id ): void {
		$this->dependencies[ $type ] ??= array();
		$this->dependencies[ $type ][] = $id;
	}

	public function child( int $post_id ): ?self {
		if ( isset( $this->seen_posts[ $post_id ] ) || $this->depth >= 2 ) {
			return null;
		}

		$child                         = new self( $post_id, $this->is_preview, $this->is_editor, $this->user_id, $this->seen_posts );
		$child->seen_posts[ $post_id ] = true;
		$child->depth                  = $this->depth + 1;
		$child->query_count            = $this->query_count;
		$child->media_count            = $this->media_count;

		return $child;
	}

	/**
	 * @return array<string, int|string>
	 */
	public function cache_key_parts(): array {
		return array(
			'post'    => $this->post_id,
			'preview' => $this->is_preview ? 1 : 0,
			'editor'  => $this->is_editor ? 1 : 0,
			'user'    => $this->is_preview || $this->is_editor ? $this->user_id : 0,
			'locale'  => function_exists( 'determine_locale' ) ? determine_locale() : 'en_US',
		);
	}
}
