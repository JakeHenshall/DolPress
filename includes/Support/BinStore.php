<?php
/**
 * Numbered document binary blobs for sprites.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Support;

final class BinStore {
	public const META_KEY = '_dolpress_bins';

	/**
	 * @return list<array{num: int, tag: string, data: string}>
	 */
	public function all( int $post_id ): array {
		if ( $post_id < 1 || ! function_exists( 'get_post_meta' ) ) {
			return array();
		}

		$raw = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$num = (int) ( $item['num'] ?? 0 );
			if ( $num < 1 ) {
				continue;
			}
			$data = (string) ( $item['data'] ?? '' );
			if ( '' === $data ) {
				continue;
			}
			$out[] = array(
				'num'  => $num,
				'tag'  => sanitize_text_field( (string) ( $item['tag'] ?? '' ) ),
				'data' => $data,
			);
		}

		return $out;
	}

	/**
	 * @return array{num: int, tag: string, data: string}|null
	 */
	public function get( int $post_id, int $num ): ?array {
		foreach ( $this->all( $post_id ) as $item ) {
			if ( $item['num'] === $num ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * @return array{num: int, tag: string, data: string}|null
	 */
	public function get_by_tag( int $post_id, string $tag ): ?array {
		$tag = sanitize_text_field( $tag );
		if ( '' === $tag ) {
			return null;
		}

		foreach ( $this->all( $post_id ) as $item ) {
			if ( $item['tag'] === $tag ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * @param array<mixed> $bins
	 */
	public function put( int $post_id, array $bins ): bool {
		if ( $post_id < 1 || ! function_exists( 'update_post_meta' ) ) {
			return false;
		}

		$clean = array();
		$seen  = array();
		foreach ( $bins as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$num = (int) ( $item['num'] ?? 0 );
			if ( $num < 1 || $num > 999 || isset( $seen[ $num ] ) ) {
				continue;
			}
			$data = (string) ( $item['data'] ?? '' );
			if ( strlen( $data ) > 262144 ) {
				$data = substr( $data, 0, 262144 );
			}
			if ( '' === $data ) {
				continue;
			}
			$seen[ $num ] = true;
			$clean[]      = array(
				'num'  => $num,
				'tag'  => sanitize_text_field( (string) ( $item['tag'] ?? '' ) ),
				'data' => $data,
			);
		}

		update_post_meta( $post_id, self::META_KEY, $clean );

		return true;
	}
}
