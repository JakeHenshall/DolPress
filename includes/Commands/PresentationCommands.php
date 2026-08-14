<?php
/**
 * Presentation command handlers.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Commands;

use Nought\DolPress\Rendering\Html;
use Nought\DolPress\Rendering\RenderContext;

final class TextCommand extends AbstractCommand {
	public function code(): string {
		return 'TX';
	}

	public function definition(): array {
		return array(
			'code'      => 'TX',
			'label'     => 'Text span',
			'help'      => 'Styled text span with optional alignment.',
			'group'     => 'presentation',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$TX+CX,"WELCOME TO THE TEMPLE"$' ),
			'schema'    => array(
				'flags'      => array( 'CX', 'L', 'R', 'CLASS' ),
				'positional' => array(
					array(
						'name'     => 'TEXT',
						'type'     => 'string',
						'required' => true,
					),
				),
				'named'      => array(
					array(
						'name' => 'CLASS',
						'type' => 'string',
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$text  = Html::text( (string) ( $arguments['TEXT'] ?? '' ) );
		$class = 'dolpress-tx';
		if ( in_array( 'CX', $flags, true ) ) {
			$class .= ' dolpress-tx--center';
		}
		if ( in_array( 'L', $flags, true ) ) {
			$class .= ' dolpress-tx--left';
		}
		if ( in_array( 'R', $flags, true ) ) {
			$class .= ' dolpress-tx--right';
		}
		if ( ! empty( $arguments['CLASS'] ) && preg_match( '/^[a-zA-Z0-9_-]+$/', (string) $arguments['CLASS'] ) ) {
			$class .= ' ' . $arguments['CLASS'];
		}

		$tag = array() !== array_intersect( $flags, array( 'CX', 'L', 'R' ) ) ? 'p' : 'span';

		return $this->el( $tag, $text, array( 'class' => $class ) );
	}
}

final class BreakCommand extends AbstractCommand {
	public function code(): string {
		return 'CR';
	}

	public function definition(): array {
		return array(
			'code'      => 'CR',
			'label'     => 'Line break',
			'help'      => 'Insert a hard line break.',
			'group'     => 'presentation',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$CR$' ),
			'schema'    => array(
				'flags'      => array(),
				'positional' => array(),
				'named'      => array(),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		return '<br class="dolpress-cr" />';
	}
}

final class ColourCommand extends AbstractCommand {
	public function __construct( private readonly string $which ) {}

	public function code(): string {
		return $this->which;
	}

	public function definition(): array {
		return array(
			'code'      => $this->which,
			'label'     => 'FG' === $this->which ? 'Foreground colour' : 'Background colour',
			'help'      => 'Apply an allowlisted 16-colour palette value.',
			'group'     => 'presentation',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$' . $this->which . ',YELLOW$' ),
			'schema'    => array(
				'flags'      => array(),
				'positional' => array(
					array(
						'name'    => 'COLOR',
						'type'    => 'enum',
						'choices' => Html::COLOURS,
					),
				),
				'named'      => array(
					array(
						'name'    => 'COLOR',
						'type'    => 'enum',
						'choices' => Html::COLOURS,
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$colour = strtolower( (string) ( $arguments['COLOR'] ?? 'black' ) );
		$prop   = 'FG' === $this->which ? 'color' : 'background-color';
		$attr   = 'FG' === $this->which ? 'data-dolpress-fg' : 'data-dolpress-bg';

		return sprintf(
			'<span class="dolpress-%s dolpress-colour-%s" %s="%s"></span>',
			strtolower( $this->which ),
			esc_attr( $colour ),
			$attr,
			esc_attr( $colour )
		);
	}
}

final class ToggleCommand extends AbstractCommand {
	public function __construct( private readonly string $code, private readonly string $label, private readonly string $class ) {}

	public function code(): string {
		return $this->code;
	}

	public function definition(): array {
		return array(
			'code'      => $this->code,
			'label'     => $this->label,
			'help'      => 'Toggle ' . strtolower( $this->label ) . ' for following text.',
			'group'     => 'presentation',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$' . $this->code . '$' ),
			'schema'    => array(
				'flags'      => array(),
				'positional' => array(),
				'named'      => array(),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		return sprintf( '<span class="%s" data-dolpress-toggle="%s"></span>', esc_attr( $this->class ), esc_attr( strtolower( $this->code ) ) );
	}
}

final class LinkCommand extends AbstractCommand {
	public function code(): string {
		return 'LK';
	}

	public function definition(): array {
		return array(
			'code'      => 'LK',
			'label'     => 'Link',
			'help'      => 'Safe internal or external hyperlink. DolDoc A= types are resolved; AD: is rejected.',
			'group'     => 'presentation',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array(
				'$LK,"Nought",URL="https://noughtdigital.com"$',
				'$LK,"Intro",A="FA:home,intro"$',
			),
			'schema'    => array(
				'flags'      => array( 'L', 'UL', 'T' ),
				'positional' => array(
					array(
						'name'     => 'TEXT',
						'type'     => 'string',
						'required' => true,
					),
				),
				'named'      => array(
					array(
						'name' => 'URL',
						'type' => 'url',
					),
					array(
						'name' => 'A',
						'type' => 'string',
					),
					array(
						'name' => 'TITLE',
						'type' => 'string',
					),
				),
			),
		);
	}

	public function validate( array $arguments, array $flags, RenderContext $context ): array {
		$result = parent::validate( $arguments, $flags, $context );
		$aux    = (string) ( $result['arguments']['A'] ?? $arguments['A'] ?? '' );
		if ( str_starts_with( strtoupper( $aux ), 'AD:' ) ) {
			$result['ok']            = false;
			$result['diagnostics'][] = array(
				'severity' => 'error',
				'code'     => 'E_UNSAFE_LINK',
				'message'  => 'Address-eval links (AD:) are not executed.',
			);
		}

		return $result;
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$text = Html::text( (string) ( $arguments['TEXT'] ?? '' ) );
		$url  = (string) ( $arguments['URL'] ?? '' );
		$aux  = (string) ( $arguments['A'] ?? '' );
		if ( '' === $url && '' !== $aux ) {
			$url = $this->resolve_aux( $aux, $context );
		}

		if ( '' === $url ) {
			return $this->el( 'span', $text, array( 'class' => 'dolpress-lk dolpress-lk--plain' ) );
		}

		$plain = str_starts_with( strtoupper( $aux ), 'PI:' )
			|| str_starts_with( strtoupper( $aux ), 'PF:' )
			|| str_starts_with( strtoupper( $aux ), 'PL:' );

		return $this->el(
			'a',
			$text,
			array(
				'class' => $plain ? 'dolpress-lk dolpress-lk--plain' : 'dolpress-lk',
				'href'  => $url,
				'title' => (string) ( $arguments['TITLE'] ?? '' ),
				'rel'   => str_starts_with( $url, '#' ) || ( function_exists( 'home_url' ) && str_starts_with( $url, home_url() ) )
					? ''
					: 'noopener noreferrer nofollow',
			)
		);
	}

	private function resolve_aux( string $aux, RenderContext $context ): string {
		$aux  = trim( $aux );
		$type = 'FI';
		$rest = $aux;
		if ( preg_match( '/^([A-Za-z]{2}):(.*)$/', $aux, $match ) ) {
			$type = strtoupper( $match[1] );
			$rest = $match[2];
		}

		$filtered = apply_filters( 'dolpress/resolve_link', null, $type, $rest, $context );
		if ( is_string( $filtered ) && '' !== $filtered ) {
			return $filtered;
		}

		if ( in_array( $type, array( 'BF', 'DN', 'HI' ), true ) ) {
			return '';
		}

		if ( 'AN' === $type || ( '' !== $rest && ! str_contains( $rest, '/' ) && 'AN' === $type ) ) {
			return '#' . AnchorCommand::id_for( $rest );
		}

		if ( 'MN' === $type ) {
			return '#dolpress-mn-' . AnchorCommand::id_for( $rest );
		}

		$parts  = array_map( 'trim', explode( ',', $rest ) );
		$target = $parts[0];
		$extra  = $parts[1] ?? '';

		if ( in_array( $type, array( 'FI', 'FA', 'FF', 'FL', 'PI', 'PF', 'PL' ), true ) ) {
			$url = $this->url_for_target( $target, $context );
			if ( '' === $url ) {
				return '';
			}
			if ( 'FA' === $type ) {
				$url .= '#' . AnchorCommand::id_for( $extra );
			} elseif ( 'FL' === $type || 'PL' === $type ) {
				$url .= '#L' . preg_replace( '/[^0-9]/', '', $extra );
			} elseif ( ( 'FF' === $type || 'PF' === $type ) && '' !== $extra ) {
				$url .= '#:~:text=' . rawurlencode( $extra );
			}

			return $url;
		}

		if ( '' !== $rest && ! str_contains( $aux, ':' ) ) {
			return '#' . AnchorCommand::id_for( $aux );
		}

		return '';
	}

	private function url_for_target( string $target, RenderContext $context ): string {
		$target = trim( $target );
		if ( '' === $target ) {
			return '';
		}

		if ( is_numeric( $target ) ) {
			$id = (int) $target;
			if ( $context->can_view_post( $id ) ) {
				return (string) get_permalink( $id );
			}
			if ( $context->can_view_attachment( $id ) ) {
				return (string) wp_get_attachment_url( $id );
			}

			return '';
		}

		if ( function_exists( 'get_page_by_path' ) ) {
			$page = get_page_by_path( sanitize_title( $target ) );
			if ( $page instanceof \WP_Post && $context->can_view_post( $page->ID ) ) {
				return (string) get_permalink( $page );
			}
		}

		if ( function_exists( 'get_posts' ) ) {
			$found = get_posts(
				array(
					'name'           => sanitize_title( $target ),
					'post_type'      => 'any',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
				)
			);
			$match = $found[0] ?? null;
			if ( $match instanceof \WP_Post && $context->can_view_post( $match->ID ) ) {
				return (string) get_permalink( $match );
			}
		}

		return '';
	}
}

final class ButtonCommand extends AbstractCommand {
	public function __construct( private readonly ActionRegistry $actions ) {}

	public function code(): string {
		return 'BT';
	}

	public function definition(): array {
		return array(
			'code'      => 'BT',
			'label'     => 'Button',
			'help'      => 'Button mapped to an allowlisted action.',
			'group'     => 'presentation',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$BT,"Back to top",ACTION="top"$' ),
			'schema'    => array(
				'flags'      => array( 'X', 'B', 'T' ),
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

	public function validate( array $arguments, array $flags, RenderContext $context ): array {
		$result = parent::validate( $arguments, $flags, $context );
		$action = ActionRegistry::sanitise_name( (string) ( $result['arguments']['ACTION'] ?? 'top' ) );
		if ( '' === $action || ! $this->actions->is_allowed( $action ) ) {
			$result['ok']            = false;
			$result['diagnostics'][] = array(
				'severity' => 'error',
				'code'     => 'E_BAD_ACTION',
				'message'  => 'Action is not allowlisted.',
			);
		}

		return $result;
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$action = ActionRegistry::sanitise_name( (string) ( $arguments['ACTION'] ?? 'top' ) );
		if ( '' === $action || ! $this->actions->is_allowed( $action ) ) {
			return Html::text( (string) ( $arguments['TEXT'] ?? 'Button' ) );
		}

		$attrs = array(
			'type'                 => 'button',
			'class'                => 'dolpress-bt',
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

		return $this->el( 'button', Html::text( (string) ( $arguments['TEXT'] ?? 'Button' ) ), $attrs );
	}
}

final class TreeCommand extends AbstractCommand {
	public function code(): string {
		return 'TR';
	}

	public function definition(): array {
		return array(
			'code'      => 'TR',
			'label'     => 'Tree section',
			'help'      => 'Collapsible tree. Close with $/TR$ or native $ID$ indent scope.',
			'group'     => 'presentation',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'paired'    => true,
			'examples'  => array( '$TR,TITLE="Details"$Hidden$/TR$', '$TR,"Branch"$ $ID,2$Body$ID,-2$' ),
			'schema'    => array(
				'flags'      => array( 'OPEN', 'C', 'CA', 'TR', 'UL', 'T' ),
				'positional' => array(
					array(
						'name'    => 'TITLE',
						'type'    => 'string',
						'default' => 'Section',
					),
				),
				'named'      => array(
					array(
						'name'    => 'TITLE',
						'type'    => 'string',
						'default' => 'Section',
					),
					array(
						'name'    => 'OPEN',
						'type'    => 'bool',
						'default' => false,
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$open = ! empty( $arguments['OPEN'] ) || in_array( 'OPEN', $flags, true );
		if ( in_array( 'C', $flags, true ) ) {
			$open = false;
		}
		$title = Html::text( (string) ( $arguments['TITLE'] ?? 'Section' ) );
		$inner = (string) ( $arguments['_children'] ?? '' );

		return sprintf(
			'<details class="dolpress-tr"%s><summary>%s</summary><div class="dolpress-tr__body">%s</div></details>',
			$open ? ' open' : '',
			$title,
			$inner
		);
	}
}

final class ImageCommand extends AbstractCommand {
	public function code(): string {
		return 'IM';
	}

	public function definition(): array {
		return array(
			'code'      => 'IM',
			'label'     => 'Image',
			'help'      => 'WordPress attachment image.',
			'group'     => 'media',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$IM,ID=12,SIZE="large",ALT="Logo"$' ),
			'schema'    => array(
				'flags'      => array(),
				'positional' => array(),
				'named'      => array(
					array(
						'name'     => 'ID',
						'type'     => 'int',
						'required' => true,
						'min'      => 1,
					),
					array(
						'name'    => 'SIZE',
						'type'    => 'enum',
						'choices' => array( 'thumbnail', 'medium', 'large', 'full' ),
						'default' => 'large',
					),
					array(
						'name' => 'ALT',
						'type' => 'string',
					),
					array(
						'name' => 'CAPTION',
						'type' => 'string',
					),
					array(
						'name'    => 'LINK',
						'type'    => 'enum',
						'choices' => array( 'none', 'file', 'attachment' ),
						'default' => 'none',
					),
					array(
						'name' => 'CLASS',
						'type' => 'string',
					),
					array(
						'name'    => 'DECORATIVE',
						'type'    => 'bool',
						'default' => false,
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$id = (int) ( $arguments['ID'] ?? 0 );
		if ( $id < 1 || ! $context->can_view_attachment( $id ) ) {
			return '';
		}

		$context->note_dependency( 'attachment', $id );
		$size = (string) ( $arguments['SIZE'] ?? 'large' );
		$html = wp_get_attachment_image(
			$id,
			$size,
			false,
			array(
				'class' => 'dolpress-im' . ( ! empty( $arguments['CLASS'] ) && preg_match( '/^[a-zA-Z0-9_-]+$/', (string) $arguments['CLASS'] ) ? ' ' . $arguments['CLASS'] : '' ),
				'alt'   => ! empty( $arguments['DECORATIVE'] ) ? '' : (string) ( $arguments['ALT'] ?? get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
			)
		);

		if ( '' === $html ) {
			return '';
		}

		$link = (string) ( $arguments['LINK'] ?? 'none' );
		if ( 'file' === $link ) {
			$url  = wp_get_attachment_url( $id );
			$html = $url ? '<a class="dolpress-im-link" href="' . esc_url( $url ) . '">' . $html . '</a>' : $html;
		} elseif ( 'attachment' === $link ) {
			$url  = get_attachment_link( $id );
			$html = $url ? '<a class="dolpress-im-link" href="' . esc_url( $url ) . '">' . $html . '</a>' : $html;
		}

		$caption = (string) ( $arguments['CAPTION'] ?? '' );
		if ( '' !== $caption ) {
			$html = '<figure class="dolpress-im-figure">' . $html . '<figcaption>' . Html::text( $caption ) . '</figcaption></figure>';
		}

		return $html;
	}
}

final class RuleCommand extends AbstractCommand {
	public function code(): string {
		return 'HR';
	}

	public function definition(): array {
		return array(
			'code'      => 'HR',
			'label'     => 'Horizontal rule',
			'help'      => 'Thematic break.',
			'group'     => 'presentation',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$HR$' ),
			'schema'    => array(
				'flags'      => array(),
				'positional' => array(),
				'named'      => array(),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		return '<hr class="dolpress-hr" />';
	}
}
