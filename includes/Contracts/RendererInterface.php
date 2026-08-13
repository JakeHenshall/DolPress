<?php
/**
 * Renderer contract.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Contracts;

use Nought\DolPress\Rendering\RenderContext;
use Nought\DolPress\Rendering\RenderResult;

interface RendererInterface {
	public function render( string $source, RenderContext $context ): RenderResult;
}
