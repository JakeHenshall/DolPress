<?php
/**
 * Attach indent-scoped tree bodies after a flat parse.
 *
 * Native DolDoc uses $TR$ then $ID,+n$ … $ID,-n$. DolPress also keeps $/TR$.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Parser;

final class TreeNormalizer {
	public function __construct( private readonly int $max_nest = 8 ) {}

	/**
	 * @param list<array<string, mixed>> $arguments
	 */
	public static function delta_from_arguments( array $arguments ): int {
		$index = 0;
		foreach ( $arguments as $argument ) {
			$name  = strtoupper( (string) ( $argument['name'] ?? '' ) );
			$value = $argument['value'] ?? null;
			$key   = '' !== $name ? $name : '_' . $index;
			if ( '' === $name ) {
				++$index;
			}
			if ( ! in_array( $key, array( 'DELTA', 'N', '_0' ), true ) || ! is_numeric( $value ) ) {
				continue;
			}

			return (int) $value;
		}

		return 0;
	}

	/**
	 * @param list<Node>        $nodes
	 * @param list<Diagnostic>  $diagnostics
	 * @return list<Node>
	 */
	public function normalize( array $nodes, array &$diagnostics = array(), int $depth = 0 ): array {
		$out   = array();
		$count = count( $nodes );
		$i     = 0;

		while ( $i < $count ) {
			$node = $nodes[ $i ];
			if ( $node instanceof CommandNode && 'TR' === strtoupper( $node->code ) && array() === $node->children ) {
				if ( $depth >= $this->max_nest ) {
					$diagnostics[] = new Diagnostic(
						'error',
						'E_NESTING_LIMIT',
						sprintf( 'Nesting exceeds the maximum depth of %d.', $this->max_nest ),
						$node->line,
						$node->column,
						$node->offset,
						$node->length
					);
				}

				$body     = array();
				$consumed = $this->collect_body( $nodes, $i + 1, $body, $diagnostics, $depth + 1 );
				$out[]    = $this->with_children( $node, $this->normalize( $body, $diagnostics, $depth + 1 ) );
				$i       += 1 + $consumed;
				continue;
			}

			$out[] = $this->normalize_node( $node, $diagnostics, $depth );
			++$i;
		}

		return $out;
	}

	/**
	 * @param list<Node>       $nodes
	 * @param list<Node>       $body
	 * @param list<Diagnostic> $diagnostics
	 */
	private function collect_body( array $nodes, int $start, array &$body, array &$diagnostics, int $depth ): int {
		$indent  = 0;
		$started = false;
		$count   = count( $nodes );
		$i       = $start;

		while ( $i < $count ) {
			$node = $nodes[ $i ];

			if ( ! $started ) {
				if ( $node instanceof TextNode && '' === trim( $node->value ) ) {
					++$i;
					continue;
				}

				if ( $node instanceof CommandNode && 'ID' === strtoupper( $node->code ) ) {
					$delta = $this->indent_delta( $node );
					if ( $delta <= 0 ) {
						return 0;
					}
					for ( $k = $start; $k < $i; $k++ ) {
						$body[] = $nodes[ $k ];
					}
					$started = true;
					$indent += $delta;
					$body[]  = $this->normalize_node( $node, $diagnostics, $depth );
					++$i;
					continue;
				}

				return 0;
			}

			if ( $node instanceof CommandNode && 'ID' === strtoupper( $node->code ) ) {
				$indent += $this->indent_delta( $node );
				$body[]  = $this->normalize_node( $node, $diagnostics, $depth );
				++$i;
				if ( $indent <= 0 ) {
					return $i - $start;
				}
				continue;
			}

			if ( $node instanceof CommandNode && 'TR' === strtoupper( $node->code ) && array() === $node->children ) {
				$inner    = array();
				$consumed = $this->collect_body( $nodes, $i + 1, $inner, $diagnostics, $depth + 1 );
				if ( $depth >= $this->max_nest ) {
					$diagnostics[] = new Diagnostic(
						'error',
						'E_NESTING_LIMIT',
						sprintf( 'Nesting exceeds the maximum depth of %d.', $this->max_nest ),
						$node->line,
						$node->column,
						$node->offset,
						$node->length
					);
				}
				$body[] = $this->with_children( $node, $this->normalize( $inner, $diagnostics, $depth + 1 ) );
				$i     += 1 + $consumed;
				continue;
			}

			$body[] = $this->normalize_node( $node, $diagnostics, $depth );
			++$i;
		}

		return $started ? $i - $start : 0;
	}

	private function indent_delta( CommandNode $node ): int {
		return self::delta_from_arguments( $node->arguments );
	}

	private function normalize_node( Node $node, array &$diagnostics, int $depth ): Node {
		if ( $node instanceof CommandNode && array() !== $node->children ) {
			return $this->with_children( $node, $this->normalize( $node->children, $diagnostics, $depth ) );
		}

		return $node;
	}

	/**
	 * @param list<Node> $children
	 */
	private function with_children( CommandNode $node, array $children ): CommandNode {
		return new CommandNode(
			$node->code,
			$node->flags,
			$node->arguments,
			$children,
			$node->unknown,
			$node->malformed,
			$node->raw,
			$node->offset,
			$node->length,
			$node->line,
			$node->column
		);
	}
}
