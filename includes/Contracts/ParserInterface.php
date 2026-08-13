<?php
/**
 * Parser contract.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Contracts;

use Nought\DolPress\Parser\ParseResult;

interface ParserInterface {
	public function parse( string $source ): ParseResult;

	public function refresh_known(): void;
}
