<?php
/**
 * Command registry contract.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Contracts;

interface CommandRegistryInterface {
	public function register(): void;

	public function get( string $code ): ?CommandInterface;

	public function has( string $code ): bool;

	/**
	 * @return list<array<string, mixed>>
	 */
	public function schemas(): array;
}
