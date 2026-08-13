<?php
/**
 * Token kinds and a source token.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Parser;

final class Token {
	public const TEXT           = 'TEXT';
	public const COMMAND_START  = 'COMMAND_START';
	public const COMMAND_END    = 'COMMAND_END';
	public const CLOSE_MARK     = 'CLOSE_MARK';
	public const CODE           = 'CODE';
	public const FLAG           = 'FLAG';
	public const COMMA          = 'COMMA';
	public const EQUALS         = 'EQUALS';
	public const STRING         = 'STRING';
	public const NUMBER         = 'NUMBER';
	public const BOOLEAN        = 'BOOLEAN';
	public const IDENT          = 'IDENT';
	public const LITERAL_DOLLAR = 'LITERAL_DOLLAR';
	public const ERROR          = 'ERROR';
	public const EOF            = 'EOF';

	public function __construct(
		public readonly string $kind,
		public readonly string $value,
		public readonly int $offset,
		public readonly int $length,
		public readonly int $line,
		public readonly int $column
	) {}

	public function end_offset(): int {
		return $this->offset + $this->length;
	}
}
