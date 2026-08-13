<?php
/**
 * Clickable macros mapped to allowlisted actions.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Commands;

use Nought\DolPress\Rendering\Html;
use Nought\DolPress\Rendering\RenderContext;

final class MacroCommand extends AbstractCommand {
	public function __construct( private readonly ActionRegistry $actions ) {}

	public function code(): string {
		return 'MA';
	}

	public function definition(): array {
		return array(
			'code'      => 'MA',
			'label'     => 'Macro',
			'help'      => 'Clickable action. Only allowlisted WordPress actions run; HolyC is never executed.',
			'group'     => 'macro',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$MA,"Back to top",ACTION="top"$', '$MA,"Section",ACTION="jump",AN="intro"$' ),
			'schema'    => array(
				'flags'      => array( 'X', 'UL', 'T', 'PU', 'Q', 'LC', 'RC', 'TC', 'LIS', 'RIS' ),
				'positional' => array(
					array(
						'name'     => 'TEXT',
						'type'     => 'string',
						'required' => true,
					),
				),
				'named'      => array(
					array(
						'name'    => 'ACTION',
						'type'    => 'string',
						'default' => 'top',
					),
					array(
						'name' => 'LM',
						'type' => 'string',
					),
					array(
						'name' => 'RM',
						'type' => 'string',
					),
					array(
						'name' => 'URL',
						'type' => 'url',
					),
					array(
						'name' => 'AN',
						'type' => 'string',
					),
					array(
						'name' => 'LC',
						'type' => 'string',
					),
					array(
						'name' => 'RC',
						'type' => 'string',
					),
					array(
						'name' => 'TC',
						'type' => 'string',
					),
				),
			),
		);
	}

	public function validate( array $arguments, array $flags, RenderContext $context ): array {
		$result = parent::validate( $arguments, $flags, $context );
		$action = ActionRegistry::sanitise_name( (string) ( $result['arguments']['ACTION'] ?? $result['arguments']['LM'] ?? 'top' ) );
		if ( '' === $action || ! $this->actions->is_allowed( $action ) ) {
			$result['ok']            = false;
			$result['diagnostics'][] = array(
				'severity' => 'error',
				'code'     => 'E_BAD_ACTION',
				'message'  => 'Macro action is not allowlisted.',
			);
		}

		return $result;
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$action = ActionRegistry::sanitise_name( (string) ( $arguments['ACTION'] ?? $arguments['LM'] ?? 'top' ) );
		if ( '' === $action || ! $this->actions->is_allowed( $action ) ) {
			return $this->el( 'span', Html::text( (string) ( $arguments['TEXT'] ?? '' ) ), array( 'class' => 'dolpress-ma dolpress-ma--blocked' ) );
		}

		$attrs = array(
			'type'                 => 'button',
			'class'                => 'dolpress-ma',
			'data-dolpress-action' => $action,
		);
		if ( in_array( 'PU', $flags, true ) ) {
			$attrs['data-dolpress-confirm'] = '1';
		}
		if ( in_array( 'X', $flags, true ) || in_array( 'Q', $flags, true ) ) {
			$attrs['data-dolpress-close'] = '1';
		}
		if ( 'url' === $action && ! empty( $arguments['URL'] ) ) {
			$attrs['data-dolpress-url'] = (string) $arguments['URL'];
		}
		if ( 'jump' === $action && ! empty( $arguments['AN'] ) ) {
			$attrs['data-dolpress-target'] = AnchorCommand::id_for( (string) $arguments['AN'] );
		}
		foreach ( array( 'LC', 'RC', 'TC' ) as $hook_flag ) {
			$name = ActionRegistry::sanitise_name( (string) ( $arguments[ $hook_flag ] ?? '' ) );
			if ( '' !== $name && $this->actions->is_allowed( $name ) && ! $this->actions->is_builtin( $name ) ) {
				$attrs[ 'data-dolpress-' . strtolower( $hook_flag ) ] = $name;
			}
		}

		return $this->el( 'button', Html::text( (string) ( $arguments['TEXT'] ?? 'Macro' ) ), $attrs );
	}
}
