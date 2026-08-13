<?php
/**
 * Source serializer. Canonical documents keep original source; this rebuilds from the AST.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Parser;

final class Serializer {
	public function serialize( DocumentNode $document ): string {
		return $this->nodes( $document->children );
	}

	/**
	 * @param list<Node> $nodes
	 */
	private function nodes( array $nodes ): string {
		$out = '';
		foreach ( $nodes as $node ) {
			$out .= $this->node( $node );
		}

		return $out;
	}

	private function node( Node $node ): string {
		if ( $node instanceof TextNode ) {
			return str_replace( '$', '$$', $node->value );
		}

		if ( ! $node instanceof CommandNode ) {
			return '';
		}

		$out = '$' . $node->code;
		foreach ( $node->flags as $flag ) {
			$out .= '+' . $flag;
		}

		$parts = array();
		foreach ( $node->arguments as $argument ) {
			$value   = $this->value( $argument['value'] ?? '', (string) ( $argument['kind'] ?? 'ident' ) );
			$name    = (string) ( $argument['name'] ?? '' );
			$parts[] = '' !== $name ? $name . '=' . $value : $value;
		}

		if ( array() !== $parts ) {
			$out .= ',' . implode( ',', $parts );
		}

		$out .= '$';

		if ( array() !== $node->children ) {
			$out .= $this->nodes( $node->children ) . '$/' . $node->code . '$';
		}

		return $out;
	}

	private function value( mixed $value, string $kind ): string {
		if ( 'string' === $kind || ( is_string( $value ) && ( str_contains( (string) $value, ' ' ) || str_contains( (string) $value, ',' ) || str_contains( (string) $value, '$' ) ) ) ) {
			$escaped = str_replace(
				array( '\\', '"', '$', "\n" ),
				array( '\\\\', '\"', '\$', '\\n' ),
				(string) $value
			);
			return '"' . $escaped . '"';
		}

		if ( is_bool( $value ) ) {
			return $value ? 'TRUE' : 'FALSE';
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		return (string) $value;
	}
}
