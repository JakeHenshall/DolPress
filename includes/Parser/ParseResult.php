<?php
/**
 * Parser output.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Parser;

final class ParseResult {
	/**
	 * @param list<Diagnostic> $diagnostics
	 */
	public function __construct(
		public readonly DocumentNode $document,
		public readonly array $diagnostics,
		public readonly string $source,
		public readonly string $grammar_version = DOLPRESS_GRAMMAR_VERSION
	) {}

	public function has_errors(): bool {
		foreach ( $this->diagnostics as $diagnostic ) {
			if ( 'error' === $diagnostic->severity ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'grammar'     => $this->grammar_version,
			'document'    => $this->document->to_array(),
			'diagnostics' => array_map( static fn( Diagnostic $item ) => $item->to_array(), $this->diagnostics ),
		);
	}
}
