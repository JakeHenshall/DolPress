<?php
/**
 * TempleOS CSprite stream to SVG. JSON ops are also accepted.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Sprites;

use Nought\DolPress\Rendering\Html;

final class Decoder {
	public const SPT_END             = 0;
	public const SPT_COLOR           = 1;
	public const SPT_DITHER_COLOR    = 2;
	public const SPT_THICK           = 3;
	public const SPT_PLANAR_SYMMETRY = 4;
	public const SPT_TRANSFORM_ON    = 5;
	public const SPT_TRANSFORM_OFF   = 6;
	public const SPT_SHIFT           = 7;
	public const SPT_PT              = 8;
	public const SPT_POLYPT          = 9;
	public const SPT_LINE            = 10;
	public const SPT_POLYLINE        = 11;
	public const SPT_RECT            = 12;
	public const SPT_ROTATED_RECT    = 13;
	public const SPT_CIRCLE          = 14;
	public const SPT_ELLIPSE         = 15;
	public const SPT_POLYGON         = 16;
	public const SPT_BSPLINE2        = 17;
	public const SPT_BSPLINE2_CLOSED = 18;
	public const SPT_BSPLINE3        = 19;
	public const SPT_BSPLINE3_CLOSED = 20;
	public const SPT_FLOOD_FILL      = 21;
	public const SPT_FLOOD_FILL_NOT  = 22;
	public const SPT_BITMAP          = 23;
	public const SPT_MESH            = 24;
	public const SPT_SHIFTABLE_MESH  = 25;
	public const SPT_ARROW           = 26;
	public const SPT_TEXT            = 27;
	public const SPT_TEXT_BOX        = 28;
	public const SPT_TEXT_DIAMOND    = 29;

	private const PALETTE = array(
		'#000000',
		'#0000AA',
		'#00AA00',
		'#00AAAA',
		'#AA0000',
		'#AA00AA',
		'#AA5500',
		'#AAAAAA',
		'#555555',
		'#5555FF',
		'#55FF55',
		'#55FFFF',
		'#FF5555',
		'#FF55FF',
		'#FFFF55',
		'#FFFFFF',
	);

	public function to_svg( string $data ): string {
		$trim = ltrim( $data );
		$ops  = str_starts_with( $trim, '{' ) || str_starts_with( $trim, '[' )
			? $this->ops_from_json( $trim )
			: $this->ops_from_binary( $this->binary( $data ) );

		if ( array() === $ops ) {
			return '<svg class="dolpress-sp" viewBox="0 0 16 16" width="16" height="16" role="img" aria-label="Sprite"></svg>';
		}

		$parts  = array();
		$color  = self::PALETTE[7];
		$thick  = 1;
		$shiftx = 0;
		$shifty = 0;
		$minx   = 0;
		$miny   = 0;
		$maxx   = 16;
		$maxy   = 16;

		$track = static function ( int $x, int $y ) use ( &$minx, &$miny, &$maxx, &$maxy ): void {
			$minx = min( $minx, $x );
			$miny = min( $miny, $y );
			$maxx = max( $maxx, $x );
			$maxy = max( $maxy, $y );
		};

		foreach ( $ops as $op ) {
			$type = (string) ( $op['t'] ?? '' );
			if ( 'color' === $type ) {
				$idx   = max( 0, min( 15, (int) ( $op['c'] ?? 7 ) ) );
				$color = self::PALETTE[ $idx ];
				continue;
			}
			if ( 'thick' === $type ) {
				$thick = max( 1, min( 16, (int) ( $op['n'] ?? 1 ) ) );
				continue;
			}
			if ( 'shift' === $type ) {
				$shiftx += (int) ( $op['x'] ?? 0 );
				$shifty += (int) ( $op['y'] ?? 0 );
				continue;
			}

			$x1 = (int) ( $op['x1'] ?? $op['x'] ?? 0 ) + $shiftx;
			$y1 = (int) ( $op['y1'] ?? $op['y'] ?? 0 ) + $shifty;
			$x2 = (int) ( $op['x2'] ?? $x1 ) + $shiftx;
			$y2 = (int) ( $op['y2'] ?? $y1 ) + $shifty;
			$track( $x1, $y1 );
			$track( $x2, $y2 );

			if ( 'pt' === $type ) {
				$parts[] = sprintf( '<circle cx="%d" cy="%d" r="%d" fill="%s" />', $x1, $y1, $thick, $color );
			} elseif ( 'line' === $type ) {
				$parts[] = sprintf(
					'<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s" stroke-width="%d" />',
					$x1,
					$y1,
					$x2,
					$y2,
					$color,
					$thick
				);
			} elseif ( 'rect' === $type ) {
				$parts[] = sprintf(
					'<rect x="%d" y="%d" width="%d" height="%d" fill="none" stroke="%s" stroke-width="%d" />',
					min( $x1, $x2 ),
					min( $y1, $y2 ),
					abs( $x2 - $x1 ),
					abs( $y2 - $y1 ),
					$color,
					$thick
				);
			} elseif ( 'circle' === $type ) {
				$r = max( 1, (int) ( $op['r'] ?? 1 ) );
				$track( $x1 - $r, $y1 - $r );
				$track( $x1 + $r, $y1 + $r );
				$parts[] = sprintf( '<circle cx="%d" cy="%d" r="%d" fill="none" stroke="%s" stroke-width="%d" />', $x1, $y1, $r, $color, $thick );
			} elseif ( 'ellipse' === $type ) {
				$w = max( 1, (int) ( $op['w'] ?? 1 ) );
				$h = max( 1, (int) ( $op['h'] ?? 1 ) );
				$track( $x1 - $w, $y1 - $h );
				$track( $x1 + $w, $y1 + $h );
				$parts[] = sprintf( '<ellipse cx="%d" cy="%d" rx="%d" ry="%d" fill="none" stroke="%s" stroke-width="%d" />', $x1, $y1, $w, $h, $color, $thick );
			} elseif ( 'arrow' === $type ) {
				$parts[] = sprintf(
					'<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s" stroke-width="%d" marker-end="url(#dolpress-arrow)" />',
					$x1,
					$y1,
					$x2,
					$y2,
					$color,
					$thick
				);
			} elseif ( 'polyline' === $type || 'spline' === $type ) {
				$pts = $op['pts'] ?? array();
				if ( is_array( $pts ) && array() !== $pts ) {
					$d = array();
					foreach ( $pts as $pt ) {
						if ( ! is_array( $pt ) ) {
							continue;
						}
						$px = (int) ( $pt[0] ?? 0 ) + $shiftx;
						$py = (int) ( $pt[1] ?? 0 ) + $shifty;
						$track( $px, $py );
						$d[] = $px . ',' . $py;
					}
					$fill    = ! empty( $op['closed'] ) ? $color : 'none';
					$parts[] = sprintf(
						'<polyline points="%s" fill="%s" stroke="%s" stroke-width="%d" />',
						esc_attr( implode( ' ', $d ) ),
						$fill,
						$color,
						$thick
					);
				}
			} elseif ( 'text' === $type || 'text-box' === $type || 'text-diamond' === $type ) {
				$text    = Html::text( (string) ( $op['s'] ?? '' ) );
				$parts[] = sprintf( '<text x="%d" y="%d" fill="%s" font-size="12">%s</text>', $x1, $y1, $color, $text );
			} elseif ( 'bitmap' === $type || 'mesh' === $type || 'flood' === $type ) {
				$w = max( 8, (int) ( $op['w'] ?? 16 ) );
				$h = max( 8, (int) ( $op['h'] ?? 16 ) );
				$track( $x1 + $w, $y1 + $h );
				$parts[] = sprintf(
					'<rect x="%d" y="%d" width="%d" height="%d" class="dolpress-sp-placeholder" fill="%s" fill-opacity="0.2" />',
					$x1,
					$y1,
					$w,
					$h,
					$color
				);
			}
		}

		$pad   = 4;
		$minx -= $pad;
		$miny -= $pad;
		$w     = max( 16, $maxx - $minx + $pad );
		$h     = max( 16, $maxy - $miny + $pad );

		return sprintf(
			'<svg class="dolpress-sp" viewBox="%d %d %d %d" width="%d" height="%d" role="img" aria-label="Sprite"><defs><marker id="dolpress-arrow" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto"><polygon points="0 0, 6 3, 0 6" fill="currentColor" /></marker></defs>%s</svg>',
			$minx,
			$miny,
			$w,
			$h,
			min( 640, $w ),
			min( 640, $h ),
			implode( '', $parts )
		);
	}

	/**
	 * Packed little-endian line sprite for tests and the editor insert helper.
	 */
	public static function encode_line( int $x1, int $y1, int $x2, int $y2, int $color = 4, int $thick = 1 ): string {
		$bin  = self::pack_color( $color );
		$bin .= self::pack_thick( $thick );
		$bin .= pack( 'CxxxVVVV', self::SPT_LINE, $x1, $y1, $x2, $y2 );
		$bin .= pack( 'C', self::SPT_END );

		return $bin;
	}

	private static function pack_color( int $color ): string {
		return pack( 'CC', self::SPT_COLOR, max( 0, min( 15, $color ) ) );
	}

	private static function pack_thick( int $thick ): string {
		return pack( 'CxxxV', self::SPT_THICK, max( 1, $thick ) );
	}

	private function binary( string $data ): string {
		if ( '' === $data ) {
			return '';
		}
		$first = ord( $data[0] );
		if ( $first < 30 ) {
			return $data;
		}
		$decoded = base64_decode( $data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- sprite bin payload.

		return is_string( $decoded ) && '' !== $decoded ? $decoded : $data;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function ops_from_json( string $json ): array {
		$decoded = json_decode( $json, true );
		if ( isset( $decoded['ops'] ) && is_array( $decoded['ops'] ) ) {
			$decoded = $decoded['ops'];
		}
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$out = array();
		foreach ( $decoded as $item ) {
			if ( is_array( $item ) && isset( $item['t'] ) ) {
				$out[] = $item;
			}
		}

		return $out;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function ops_from_binary( string $bin ): array {
		$ops   = array();
		$len   = strlen( $bin );
		$i     = 0;
		$guard = 0;

		while ( $i < $len && $guard < 512 ) {
			++$guard;
			$type = ord( $bin[ $i ] ) & 0x7F;
			if ( self::SPT_END === $type ) {
				break;
			}

			if ( self::SPT_COLOR === $type ) {
				$color = $i + 1 < $len ? ord( $bin[ $i + 1 ] ) : 7;
				$ops[] = array(
					't' => 'color',
					'c' => $color,
				);
				$i    += 2;
				continue;
			}

			if ( self::SPT_DITHER_COLOR === $type ) {
				$i += 4;
				continue;
			}

			if ( self::SPT_THICK === $type ) {
				$thick = $this->u32( $bin, $i + 4 );
				$ops[] = array(
					't' => 'thick',
					'n' => $thick,
				);
				$i    += 8;
				continue;
			}

			if ( in_array( $type, array( self::SPT_TRANSFORM_ON, self::SPT_TRANSFORM_OFF ), true ) ) {
				++$i;
				continue;
			}

			if ( self::SPT_SHIFT === $type || self::SPT_PT === $type || self::SPT_FLOOD_FILL === $type || self::SPT_FLOOD_FILL_NOT === $type ) {
				$x     = $this->i32( $bin, $i + 4 );
				$y     = $this->i32( $bin, $i + 8 );
				$label = self::SPT_SHIFT === $type ? 'shift' : ( self::SPT_PT === $type ? 'pt' : 'flood' );
				$ops[] = array(
					't' => $label,
					'x' => $x,
					'y' => $y,
				);
				$i    += 12;
				continue;
			}

			if ( self::SPT_LINE === $type || self::SPT_RECT === $type || self::SPT_ARROW === $type || self::SPT_PLANAR_SYMMETRY === $type ) {
				$map   = array(
					self::SPT_LINE  => 'line',
					self::SPT_RECT  => 'rect',
					self::SPT_ARROW => 'arrow',
				);
				$ops[] = array(
					't'  => $map[ $type ] ?? 'line',
					'x1' => $this->i32( $bin, $i + 4 ),
					'y1' => $this->i32( $bin, $i + 8 ),
					'x2' => $this->i32( $bin, $i + 12 ),
					'y2' => $this->i32( $bin, $i + 16 ),
				);
				$i    += 20;
				continue;
			}

			if ( self::SPT_CIRCLE === $type ) {
				$ops[] = array(
					't' => 'circle',
					'x' => $this->i32( $bin, $i + 4 ),
					'y' => $this->i32( $bin, $i + 8 ),
					'r' => $this->i32( $bin, $i + 12 ),
				);
				$i    += 16;
				continue;
			}

			if ( self::SPT_ELLIPSE === $type ) {
				$ops[] = array(
					't' => 'ellipse',
					'x' => $this->i32( $bin, $i + 4 ),
					'y' => $this->i32( $bin, $i + 8 ),
					'w' => $this->i32( $bin, $i + 12 ),
					'h' => $this->i32( $bin, $i + 16 ),
				);
				$i    += 32;
				continue;
			}

			if ( self::SPT_ROTATED_RECT === $type ) {
				$ops[] = array(
					't'  => 'rect',
					'x1' => $this->i32( $bin, $i + 4 ),
					'y1' => $this->i32( $bin, $i + 8 ),
					'x2' => $this->i32( $bin, $i + 12 ),
					'y2' => $this->i32( $bin, $i + 16 ),
				);
				$i    += 32;
				continue;
			}

			if ( self::SPT_POLYGON === $type ) {
				$ops[] = array(
					't' => 'circle',
					'x' => $this->i32( $bin, $i + 4 ),
					'y' => $this->i32( $bin, $i + 8 ),
					'r' => max( 1, $this->i32( $bin, $i + 12 ) ),
				);
				$i    += 36;
				continue;
			}

			if ( self::SPT_POLYLINE === $type || self::SPT_BSPLINE2 === $type || self::SPT_BSPLINE3 === $type || self::SPT_BSPLINE2_CLOSED === $type || self::SPT_BSPLINE3_CLOSED === $type ) {
				$num  = $this->i32( $bin, $i + 4 );
				$i   += 8;
				$pts  = array();
				$step = ( self::SPT_POLYLINE === $type ) ? 8 : 12;
				$num  = max( 0, min( 256, $num ) );
				for ( $n = 0; $n < $num; $n++ ) {
					$pts[] = array( $this->i32( $bin, $i ), $this->i32( $bin, $i + 4 ) );
					$i    += $step;
				}
				$ops[] = array(
					't'      => 'polyline',
					'pts'    => $pts,
					'closed' => in_array( $type, array( self::SPT_BSPLINE2_CLOSED, self::SPT_BSPLINE3_CLOSED ), true ),
				);
				continue;
			}

			if ( in_array( $type, array( self::SPT_TEXT, self::SPT_TEXT_BOX, self::SPT_TEXT_DIAMOND ), true ) ) {
				$x     = $this->i32( $bin, $i + 4 );
				$y     = $this->i32( $bin, $i + 8 );
				$i    += 12;
				$end   = strpos( $bin, "\0", $i );
				$text  = false === $end ? substr( $bin, $i ) : substr( $bin, $i, $end - $i );
				$i     = false === $end ? $len : $end + 1;
				$ops[] = array(
					't' => 'text',
					'x' => $x,
					'y' => $y,
					's' => $text,
				);
				continue;
			}

			if ( self::SPT_BITMAP === $type ) {
				$w     = $this->i32( $bin, $i + 12 );
				$h     = $this->i32( $bin, $i + 16 );
				$ops[] = array(
					't' => 'bitmap',
					'x' => $this->i32( $bin, $i + 4 ),
					'y' => $this->i32( $bin, $i + 8 ),
					'w' => max( 1, $w ),
					'h' => max( 1, $h ),
				);
				$i    += 20 + ( ( ( $w + 7 ) & ~7 ) * max( 0, $h ) );
				continue;
			}

			if ( self::SPT_MESH === $type || self::SPT_SHIFTABLE_MESH === $type ) {
				$ops[] = array(
					't' => 'mesh',
					'w' => 32,
					'h' => 32,
				);
				break;
			}

			if ( self::SPT_POLYPT === $type ) {
				$num = $this->i32( $bin, $i + 4 );
				$i  += 16 + ( ( $num * 3 + 7 ) >> 3 );
				continue;
			}

			++$i;
		}

		return $ops;
	}

	private function i32( string $bin, int $offset ): int {
		if ( $offset + 4 > strlen( $bin ) ) {
			return 0;
		}
		$unpacked = unpack( 'V', substr( $bin, $offset, 4 ) );
		$n        = is_array( $unpacked ) ? (int) $unpacked[1] : 0;
		if ( $n >= 0x80000000 ) {
			$n -= 0x100000000;
		}

		return $n;
	}

	private function u32( string $bin, int $offset ): int {
		if ( $offset + 4 > strlen( $bin ) ) {
			return 0;
		}
		$unpacked = unpack( 'V', substr( $bin, $offset, 4 ) );

		return is_array( $unpacked ) ? (int) $unpacked[1] : 0;
	}
}
