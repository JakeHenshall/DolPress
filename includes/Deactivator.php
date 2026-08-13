<?php
/**
 * Deactivation: drop runtime hooks by deactivating the plugin; keep content and settings.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress;

final class Deactivator {
	public static function deactivate(): void {
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}
}
