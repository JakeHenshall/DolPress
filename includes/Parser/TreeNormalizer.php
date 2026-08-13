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
	/**
	 * @param list<Node> $nodes
	 * @return list<Node>
	 */
	public function normalize( array $nodes ): array {
		$out   = array();
		$count = count( $nodes );
		$i     = 0;

		while ( $i < $count ) {
			$node = $nodes[ $i ];
			if ( $node instanceof CommandNode && 'TR' === strtoupper( $node->code ) && array() === $node->children ) {
				$body     = array();
				$consumed = $this->collect_body( $nodes, $i + 1, $body );
				$out[]    = $this->with_children( $node, $this->normalize( $body ) );
				$i       += 1 + $consumed;
				continue;
			}

			$out[] = $this->normalize_node( $node );
			++$i;
		}

		return $out;
	}

	/**
	 * @param list<Node> $nodes
	 * @param list<Node> $body
	 */
	private function collect_body( array $nodes, int $start, array &$body ): int {
		$indent  = 0;
		$started = false;
		$count   = count( $nodes );
		$i       = $start;

		while ( $i < $count ) {
			$node = $nodes[ $i ];

			if ( $node instanceof CommandNode && 'ID' === strtoupper( $node->code ) ) {
				$delta = $this->indent_delta( $node );
				if ( ! $started ) {
					if ( $delta <= 0 ) {
						return 0;
					}
					$started = true;
					$indent += $delta;
					$body[]  = $this->normalize_node( $node );
					++$i;
					continue;
				}
				$indent += $delta;
				$body[]  = $this->normalize_node( $node );
				++$i;
				if ( $indent <= 0 ) {
					return $i - $start;
				}
				continue;
			}

			if ( ! $started ) {
				return 0;
			}

			if ( $node instanceof CommandNode && 'TR' === strtoupper( $node->code ) && array() === $node->children ) {
				$inner    = array();
				$consumed = $this->collect_body( $nodes, $i + 1, $inner );
				$body[]   = $this->with_children( $node, $this->normalize( $inner ) );
				$i       += 1 + $consumed;
				continue;
			}

			$body[] = $this->normalize_node( $node );
			++$i;
		}

		return $started ? $i - $start : 0;
	}

	private function indent_delta( CommandNode $node ): int {
		$named = $node->named_arguments();
		foreach ( array( 'DELTA', 'N', '_0' ) as $key ) {
			if ( isset( $named[ $key ] ) && is_numeric( $named[ $key ] ) ) {
				return (int) $named[ $key ];
			}
		}

		foreach ( $node->arguments as $argument ) {
			if ( is_numeric( $argument['value'] ?? null ) ) {
				return (int) $argument['value'];
			}
		}

		return 0;
	}

	private function normalize_node( Node $node ): Node {
		if ( $node instanceof CommandNode && array() !== $node->children ) {
			return $this->with_children( $node, $this->normalize( $node->children ) );
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
