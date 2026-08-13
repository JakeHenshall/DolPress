<?php
/**
 * Allowlisted document actions. Never evaluates HolyC or free-form code.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Commands;

use Nought\DolPress\Support\SettingsRepository;

final class ActionRegistry {
	public const BUILTINS = array(
		'top',
		'print',
		'toggle-source',
		'preview',
		'jump',
		'url',
		'toggle-tree',
		'submit-form',
	);

	/**
	 * @var array<string, callable>
	 */
	private static array $hooks = array();

	public static function sanitise_name( string $name ): string {
		$name = strtolower( trim( $name ) );
		if ( ! preg_match( '/^[a-z0-9_-]{1,64}$/', $name ) ) {
			return '';
		}

		return $name;
	}

	public static function register( string $name, callable $callback ): void {
		$name = self::sanitise_name( $name );
		if ( '' === $name ) {
			return;
		}

		self::$hooks[ $name ] = $callback;
	}

	/**
	 * @return array<string, callable>
	 */
	public static function hooks(): array {
		return self::$hooks;
	}

	public function __construct( private readonly SettingsRepository $settings ) {}

	public function is_builtin( string $name ): bool {
		return in_array( strtolower( $name ), self::BUILTINS, true );
	}

	public function is_allowed( string $name ): bool {
		$name = self::sanitise_name( $name );
		if ( '' === $name ) {
			return false;
		}
		if ( $this->is_builtin( $name ) ) {
			return true;
		}

		$allowed = $this->settings->allowed_macros();

		return in_array( $name, $allowed, true ) && isset( self::$hooks[ $name ] );
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public function dispatch( string $name, array $payload = array() ): bool {
		$name = self::sanitise_name( $name );
		if ( ! $this->is_allowed( $name ) || $this->is_builtin( $name ) ) {
			return false;
		}

		$callback = self::$hooks[ $name ] ?? null;
		if ( ! is_callable( $callback ) ) {
			return false;
		}

		$callback( $payload );
		do_action( 'dolpress/macro/' . $name, $payload );

		return true;
	}
}
