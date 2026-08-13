<?php
/**
 * Public procedural API.
 *
 * @package DolPress
 */

declare(strict_types=1);

use Nought\DolPress\Commands\AbstractCommand;
use Nought\DolPress\Rendering\RenderContext;

/**
 * @param array<string, mixed> $args
 */
function dolpress_register_command( string $code, array $args ): void {
	add_action(
		'dolpress/register_commands',
		static function ( $registry ) use ( $code, $args ): void {
			$registry->add(
				new class( $code, $args ) extends AbstractCommand {
					/**
					 * @param array<string, mixed> $config
					 */
					public function __construct(
						private readonly string $command_code,
						private readonly array $config
					) {}

					public function code(): string {
						return strtoupper( $this->command_code );
					}

					public function definition(): array {
						return array(
							'code'      => $this->code(),
							'label'     => (string) ( $this->config['label'] ?? $this->code() ),
							'help'      => (string) ( $this->config['help'] ?? '' ),
							'group'     => (string) ( $this->config['group'] ?? 'extension' ),
							'cacheable' => (bool) ( $this->config['cacheable'] ?? false ),
							'public'    => (bool) ( $this->config['public'] ?? true ),
							'preview'   => (bool) ( $this->config['preview'] ?? true ),
							'schema'    => $this->config['schema'] ?? array(
								'flags'      => array(),
								'positional' => array(),
								'named'      => array(),
							),
							'examples'  => $this->config['examples'] ?? array(),
						);
					}

					public function render( array $arguments, array $flags, RenderContext $context ): string {
						$callback = $this->config['render_callback'] ?? null;
						if ( ! is_callable( $callback ) ) {
							return '';
						}

						$out = $callback( $arguments, $flags, $context );
						return is_string( $out ) ? $out : '';
					}
				}
			);
		}
	);
}

/**
 * Register a named document macro. The name must also be allowlisted in settings.
 */
function dolpress_register_macro( string $name, callable $callback ): void {
	\Nought\DolPress\Commands\ActionRegistry::register( $name, $callback );
}
