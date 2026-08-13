<?php
/**
 * DolDoc form and data widgets bound to allowlisted post meta.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Commands;

use Nought\DolPress\Rendering\Html;
use Nought\DolPress\Rendering\RenderContext;
use Nought\DolPress\Support\BinStore;
use Nought\DolPress\Support\SettingsRepository;

final class DataCommand extends AbstractCommand {
	public function __construct( private readonly SettingsRepository $settings ) {}

	public function code(): string {
		return 'DA';
	}

	public function definition(): array {
		return array(
			'code'      => 'DA',
			'label'     => 'Data field',
			'help'      => 'Form field bound to an allowlisted public meta key.',
			'group'     => 'widget',
			'cacheable' => false,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$DA,KEY="subtitle",A="%s"$' ),
			'schema'    => array(
				'flags'      => array( 'TRM', 'P', 'RD', 'UD', 'DL', 'DRT' ),
				'positional' => array(),
				'named'      => array(
					array(
						'name'     => 'KEY',
						'type'     => 'string',
						'required' => true,
					),
					array(
						'name'    => 'A',
						'type'    => 'string',
						'default' => '%s',
					),
					array(
						'name'    => 'LEN',
						'type'    => 'int',
						'min'     => 1,
						'max'     => 500,
						'default' => 40,
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$key = (string) ( $arguments['KEY'] ?? '' );
		if ( ! $this->settings->is_meta_key_allowed( $key ) ) {
			return '';
		}

		$value = '';
		if ( $context->post_id > 0 && function_exists( 'get_post_meta' ) ) {
			$raw   = get_post_meta( $context->post_id, $key, true );
			$value = is_scalar( $raw ) ? (string) $raw : '';
		}

		$context->note_dependency( 'meta', $key );
		$len = (int) ( $arguments['LEN'] ?? 40 );

		return sprintf(
			'<label class="dolpress-da"><span class="screen-reader-text">%s</span><input type="text" name="%s" value="%s" maxlength="%d" class="dolpress-da__input" /></label>',
			esc_attr( $key ),
			esc_attr( $key ),
			esc_attr( $value ),
			$len
		);
	}
}

final class CheckBoxCommand extends AbstractCommand {
	public function __construct( private readonly SettingsRepository $settings ) {}

	public function code(): string {
		return 'CB';
	}

	public function definition(): array {
		return array(
			'code'      => 'CB',
			'label'     => 'Checkbox',
			'help'      => 'Boolean field bound to an allowlisted public meta key.',
			'group'     => 'widget',
			'cacheable' => false,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$CB,"Agree",KEY="agree"$' ),
			'schema'    => array(
				'flags'      => array( 'CA', 'P', 'T', 'DRT' ),
				'positional' => array(
					array(
						'name'    => 'TEXT',
						'type'    => 'string',
						'default' => '',
					),
				),
				'named'      => array(
					array(
						'name'     => 'KEY',
						'type'     => 'string',
						'required' => true,
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$key = (string) ( $arguments['KEY'] ?? '' );
		if ( ! $this->settings->is_meta_key_allowed( $key ) ) {
			return '';
		}

		$checked = false;
		if ( $context->post_id > 0 && function_exists( 'get_post_meta' ) ) {
			$raw     = get_post_meta( $context->post_id, $key, true );
			$checked = in_array( (string) $raw, array( '1', 'true', 'yes', 'on' ), true );
		}

		$context->note_dependency( 'meta', $key );
		$label = Html::text( (string) ( $arguments['TEXT'] ?? $key ) );

		return sprintf(
			'<label class="dolpress-cb"><input type="checkbox" name="%s" value="1" %s /> %s</label>',
			esc_attr( $key ),
			$checked ? 'checked' : '',
			$label
		);
	}
}

final class ListCommand extends AbstractCommand {
	public function __construct( private readonly SettingsRepository $settings ) {}

	public function code(): string {
		return 'LS';
	}

	public function definition(): array {
		return array(
			'code'      => 'LS',
			'label'     => 'List picker',
			'help'      => 'Select from a comma-separated define list into allowlisted meta.',
			'group'     => 'widget',
			'cacheable' => false,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$LS,KEY="size",D="S,M,L"$' ),
			'schema'    => array(
				'flags'      => array( 'LS', 'P', 'T', 'DRT' ),
				'positional' => array(),
				'named'      => array(
					array(
						'name'     => 'KEY',
						'type'     => 'string',
						'required' => true,
					),
					array(
						'name'     => 'D',
						'type'     => 'string',
						'required' => true,
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$key = (string) ( $arguments['KEY'] ?? '' );
		if ( ! $this->settings->is_meta_key_allowed( $key ) ) {
			return '';
		}

		$choices = array_filter( array_map( 'trim', explode( ',', (string) ( $arguments['D'] ?? '' ) ) ) );
		if ( array() === $choices ) {
			return '';
		}

		$current = '';
		if ( $context->post_id > 0 && function_exists( 'get_post_meta' ) ) {
			$raw     = get_post_meta( $context->post_id, $key, true );
			$current = is_scalar( $raw ) ? (string) $raw : '';
		}

		$context->note_dependency( 'meta', $key );
		$opts = '';
		foreach ( $choices as $choice ) {
			$sel   = $choice === $current ? ' selected' : '';
			$opts .= '<option value="' . esc_attr( $choice ) . '"' . $sel . '>' . Html::text( $choice ) . '</option>';
		}

		return '<label class="dolpress-ls"><span class="screen-reader-text">' . esc_html( $key ) . '</span><select name="' . esc_attr( $key ) . '">' . $opts . '</select></label>';
	}
}

final class MenuValCommand extends AbstractCommand {
	public function __construct( private readonly ActionRegistry $actions ) {}

	public function code(): string {
		return 'MU';
	}

	public function definition(): array {
		return array(
			'code'      => 'MU',
			'label'     => 'Menu value',
			'help'      => 'Clickable menu item mapped to an allowlisted action.',
			'group'     => 'widget',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$MU,"Top",ACTION="top"$' ),
			'schema'    => array(
				'flags'      => array( 'X', 'UL', 'T', 'PU', 'Q' ),
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
						'name' => 'LE',
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
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$action = ActionRegistry::sanitise_name( (string) ( $arguments['ACTION'] ?? 'top' ) );
		if ( '' === $action || ! $this->actions->is_allowed( $action ) ) {
			return Html::text( (string) ( $arguments['TEXT'] ?? '' ) );
		}

		$attrs = array(
			'type'                 => 'button',
			'class'                => 'dolpress-mu',
			'data-dolpress-action' => $action,
		);
		if ( 'url' === $action && ! empty( $arguments['URL'] ) ) {
			$attrs['data-dolpress-url'] = (string) $arguments['URL'];
		}
		if ( 'jump' === $action && ! empty( $arguments['AN'] ) ) {
			$attrs['data-dolpress-target'] = AnchorCommand::id_for( (string) $arguments['AN'] );
		}
		if ( ! empty( $arguments['LE'] ) ) {
			$attrs['data-dolpress-le'] = (string) $arguments['LE'];
		}

		return $this->el( 'button', Html::text( (string) ( $arguments['TEXT'] ?? '' ) ), $attrs );
	}
}

final class HexCommand extends AbstractCommand {
	public function __construct(
		private readonly SettingsRepository $settings,
		private readonly BinStore $bins
	) {}

	public function code(): string {
		return 'HX';
	}

	public function definition(): array {
		return array(
			'code'      => 'HX',
			'label'     => 'Hex viewer',
			'help'      => 'Read-only hex view of an allowlisted meta blob or document bin.',
			'group'     => 'widget',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$HX,BI=1$', '$HX,KEY="payload"$' ),
			'schema'    => array(
				'flags'      => array( 'P', 'Z' ),
				'positional' => array(),
				'named'      => array(
					array(
						'name' => 'KEY',
						'type' => 'string',
					),
					array(
						'name' => 'BI',
						'type' => 'int',
						'min'  => 1,
						'max'  => 999,
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$data = '';
		if ( ! empty( $arguments['BI'] ) ) {
			$bin  = $this->bins->get( $context->post_id, (int) $arguments['BI'] );
			$data = is_array( $bin ) ? (string) $bin['data'] : '';
		} elseif ( ! empty( $arguments['KEY'] ) ) {
			$key = (string) $arguments['KEY'];
			if ( ! $this->settings->is_meta_key_allowed( $key ) ) {
				return '';
			}
			$raw  = $context->post_id > 0 && function_exists( 'get_post_meta' ) ? get_post_meta( $context->post_id, $key, true ) : '';
			$data = is_scalar( $raw ) ? (string) $raw : '';
			$context->note_dependency( 'meta', $key );
		}

		if ( '' === $data ) {
			return '';
		}

		$bytes = substr( $data, 0, 256 );
		$hex   = strtoupper( bin2hex( $bytes ) );
		$out   = '';
		for ( $i = 0, $n = strlen( $hex ); $i < $n; $i += 2 ) {
			if ( 0 === $i % 32 ) {
				$out .= ( 0 === $i ? '' : "\n" ) . sprintf( '%04X: ', $i / 2 );
			}
			$out .= substr( $hex, $i, 2 ) . ' ';
		}

		return '<pre class="dolpress-hx">' . Html::text( $out ) . '</pre>';
	}
}
