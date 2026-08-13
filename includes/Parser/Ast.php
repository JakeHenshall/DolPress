<?php
/**
 * Immutable AST nodes.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Parser;

abstract class Node {
	public function __construct(
		public readonly int $offset,
		public readonly int $length,
		public readonly int $line,
		public readonly int $column
	) {}

	/**
	 * @return array<string, mixed>
	 */
	abstract public function to_array(): array;
}

final class DocumentNode extends Node {
	/**
	 * @param list<Node> $children
	 */
	public function __construct(
		public readonly array $children,
		int $offset,
		int $length,
		int $line = 1,
		int $column = 1
	) {
		parent::__construct( $offset, $length, $line, $column );
	}

	public function to_array(): array {
		return array(
			'type'     => 'document',
			'offset'   => $this->offset,
			'length'   => $this->length,
			'children' => array_map( static fn( Node $node ) => $node->to_array(), $this->children ),
		);
	}
}

final class TextNode extends Node {
	public function __construct(
		public readonly string $value,
		int $offset,
		int $length,
		int $line,
		int $column
	) {
		parent::__construct( $offset, $length, $line, $column );
	}

	public function to_array(): array {
		return array(
			'type'   => 'text',
			'value'  => $this->value,
			'offset' => $this->offset,
			'length' => $this->length,
			'line'   => $this->line,
			'column' => $this->column,
		);
	}
}

final class CommandNode extends Node {
	/**
	 * @param list<string>              $flags
	 * @param list<array<string, mixed>> $arguments
	 * @param list<Node>                $children
	 */
	public function __construct(
		public readonly string $code,
		public readonly array $flags,
		public readonly array $arguments,
		public readonly array $children,
		public readonly bool $unknown,
		public readonly bool $malformed,
		public readonly string $raw,
		int $offset,
		int $length,
		int $line,
		int $column
	) {
		parent::__construct( $offset, $length, $line, $column );
	}

	public function to_array(): array {
		return array(
			'type'      => 'command',
			'code'      => $this->code,
			'flags'     => $this->flags,
			'arguments' => $this->arguments,
			'children'  => array_map( static fn( Node $node ) => $node->to_array(), $this->children ),
			'unknown'   => $this->unknown,
			'malformed' => $this->malformed,
			'raw'       => $this->raw,
			'offset'    => $this->offset,
			'length'    => $this->length,
			'line'      => $this->line,
			'column'    => $this->column,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function named_arguments(): array {
		$named = array();
		$index = 0;

		foreach ( $this->arguments as $argument ) {
			if ( isset( $argument['name'] ) && is_string( $argument['name'] ) && '' !== $argument['name'] ) {
				$named[ strtoupper( $argument['name'] ) ] = $argument['value'] ?? null;
				continue;
			}

			$named[ '_' . $index ] = $argument['value'] ?? null;
			++$index;
		}

		return $named;
	}
}
