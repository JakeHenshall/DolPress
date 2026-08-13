<?php
/**
 * Command handler contract.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Contracts;

use Nought\DolPress\Rendering\RenderContext;

interface CommandInterface {
	public function code(): string;

	/**
	 * @return array<string, mixed>
	 */
	public function definition(): array;

	/**
	 * @param array<string, mixed> $arguments
	 * @param list<string>         $flags
	 * @return array{ok: bool, arguments: array<string, mixed>, diagnostics: list<array<string, mixed>>}
	 */
	public function validate( array $arguments, array $flags, RenderContext $context ): array;

	/**
	 * @param array<string, mixed> $arguments
	 * @param list<string>         $flags
	 */
	public function render( array $arguments, array $flags, RenderContext $context ): string;
}
