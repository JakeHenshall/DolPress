<?php
/**
 * HTML helpers and the 16-colour palette.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Rendering;

final class Html {
	public const COLOURS = array(
		'BLACK',
		'BLUE',
		'GREEN',
		'CYAN',
		'RED',
		'PURPLE',
		'BROWN',
		'LTGRAY',
		'DKGRAY',
		'LTBLUE',
		'LTGREEN',
		'LTCYAN',
		'LTRED',
		'LTPURPLE',
		'YELLOW',
		'WHITE',
	);

	public static function text( string $value ): string {
		return esc_html( $value );
	}

	public static function kses( string $html ): string {
		return wp_kses( $html, self::allowed_tags() );
	}

	/**
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_tags(): array {
		$common = array(
			'class' => true,
			'id'    => true,
			'lang'  => true,
		);

		$data = array(
			'data-dolpress-fg'     => true,
			'data-dolpress-bg'     => true,
			'data-dolpress-toggle' => true,
			'data-dolpress-action' => true,
		);

		return array(
			'div'        => $common + array( 'role' => true ),
			'span'       => $common + $data,
			'p'          => $common,
			'br'         => $common,
			'hr'         => $common,
			'a'          => $common + array(
				'href'   => true,
				'title'  => true,
				'rel'    => true,
				'target' => true,
			),
			'button'     => $common + array( 'type' => true ) + $data,
			'strong'     => $common,
			'em'         => $common,
			'code'       => $common,
			'pre'        => $common,
			'ul'         => $common,
			'ol'         => $common,
			'li'         => $common,
			'nav'        => $common + array( 'aria-label' => true ),
			'img'        => $common + array(
				'src'     => true,
				'alt'     => true,
				'width'   => true,
				'height'  => true,
				'loading' => true,
				'srcset'  => true,
				'sizes'   => true,
			),
			'figure'     => $common,
			'figcaption' => $common,
			'details'    => $common + array( 'open' => true ),
			'summary'    => $common,
			'h1'         => $common,
			'h2'         => $common,
			'h3'         => $common,
			'h4'         => $common,
			'time'       => $common + array( 'datetime' => true ),
			'form'       => $common + array(
				'action' => true,
				'method' => true,
				'id'     => true,
			),
			'input'      => $common + array(
				'type'  => true,
				'name'  => true,
				'value' => true,
				'id'    => true,
			),
			'textarea'   => $common + array(
				'name' => true,
				'id'   => true,
				'rows' => true,
				'cols' => true,
			),
			'label'      => $common + array( 'for' => true ),
			'article'    => $common,
			'header'     => $common,
			'footer'     => $common,
			'section'    => $common,
		);
	}
}
