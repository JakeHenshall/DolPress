<?php
/**
 * Renderer output.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Rendering;

use Nought\DolPress\Parser\Diagnostic;

final class RenderResult {
	/**
	 * @param list<Diagnostic> $diagnostics
	 * @param array<string, mixed> $dependencies
	 */
	public function __construct(
		public readonly string $html,
		public readonly array $diagnostics,
		public readonly array $dependencies = array()
	) {}
}
