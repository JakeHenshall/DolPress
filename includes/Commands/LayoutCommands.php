<?php
/**
 * DolDoc layout and document-chrome commands.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Commands;

use Nought\DolPress\Rendering\Html;
use Nought\DolPress\Rendering\RenderContext;

final class LayoutCommand extends AbstractCommand {
	/**
	 * @param list<string>             $flags
	 * @param list<array<string, mixed>> $positional
	 * @param list<array<string, mixed>> $named
	 * @param list<string>             $examples
	 */
	public function __construct(
		private readonly string $code,
		private readonly string $label,
		private readonly string $help,
		private readonly array $flags = array(),
		private readonly array $positional = array(),
		private readonly array $named = array(),
		private readonly array $examples = array(),
		private readonly string $markup = ''
	) {}

	public function code(): string {
		return $this->code;
	}

	public function definition(): array {
		return array(
			'code'      => $this->code,
			'label'     => $this->label,
			'help'      => $this->help,
			'group'     => 'layout',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => $this->examples,
			'schema'    => array(
				'flags'      => $this->flags,
				'positional' => $this->positional,
				'named'      => $this->named,
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		return $this->markup;
	}
}

final class AnchorCommand extends AbstractCommand {
	public function code(): string {
		return 'AN';
	}

	public function definition(): array {
		return array(
			'code'      => 'AN',
			'label'     => 'Anchor',
			'help'      => 'Named in-document target for links.',
			'group'     => 'layout',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$AN,A="intro"$' ),
			'schema'    => array(
				'flags'      => array( 'T' ),
				'positional' => array(
					array(
						'name' => 'LABEL',
						'type' => 'string',
					),
				),
				'named'      => array(
					array(
						'name' => 'A',
						'type' => 'string',
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$label = (string) ( $arguments['A'] ?? $arguments['LABEL'] ?? '' );
		$id    = self::id_for( $label );

		return $this->el(
			'span',
			'',
			array(
				'id'    => $id,
				'class' => 'dolpress-an',
			)
		);
	}

	public static function id_for( string $label ): string {
		$slug = strtolower( preg_replace( '/[^a-zA-Z0-9_-]+/', '-', $label ) ?? '' );
		$slug = trim( $slug, '-' );

		return 'dolpress-an-' . ( '' !== $slug ? $slug : 'target' );
	}
}

final class IndentCommand extends AbstractCommand {
	public function code(): string {
		return 'ID';
	}

	public function definition(): array {
		return array(
			'code'      => 'ID',
			'label'     => 'Indent',
			'help'      => 'Relative indent. Positive opens a tree body; negative closes it.',
			'group'     => 'layout',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$ID,2$', '$ID,-2$' ),
			'schema'    => array(
				'flags'      => array(),
				'positional' => array(
					array(
						'name'    => 'DELTA',
						'type'    => 'int',
						'min'     => -16,
						'max'     => 16,
						'default' => 0,
					),
				),
				'named'      => array(
					array(
						'name' => 'DELTA',
						'type' => 'int',
						'min'  => -16,
						'max'  => 16,
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		return '';
	}
}
