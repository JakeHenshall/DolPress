<?php
/**
 * Diagnostic produced by the parser or command validation.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Parser;

final class Diagnostic {
	public function __construct(
		public readonly string $severity,
		public readonly string $code,
		public readonly string $message,
		public readonly int $line,
		public readonly int $column,
		public readonly int $offset,
		public readonly int $length,
		public readonly ?string $help = null
	) {}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'severity' => $this->severity,
			'code'     => $this->code,
			'message'  => $this->message,
			'line'     => $this->line,
			'column'   => $this->column,
			'offset'   => $this->offset,
			'length'   => $this->length,
			'help'     => $this->help,
		);
	}
}
