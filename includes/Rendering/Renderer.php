<?php
/**
 * Authoritative frontend renderer.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Rendering;

use Nought\DolPress\Contracts\CommandRegistryInterface;
use Nought\DolPress\Contracts\ParserInterface;
use Nought\DolPress\Contracts\RendererInterface;
use Nought\DolPress\Parser\Codes;
use Nought\DolPress\Parser\CommandNode;
use Nought\DolPress\Parser\Diagnostic;
use Nought\DolPress\Parser\Node;
use Nought\DolPress\Parser\TextNode;
use Nought\DolPress\Support\SettingsRepository;

final class Renderer implements RendererInterface {
	private const ALIGN_FLAGS = array( 'CX', 'L', 'R' );

	public function __construct(
		private readonly ParserInterface $parser,
		private readonly CommandRegistryInterface $commands,
		private readonly SettingsRepository $settings
	) {}

	public function render( string $source, RenderContext $context ): RenderResult {
		$started     = microtime( true );
		$parsed      = $this->parser->parse( $source );
		$diagnostics = $parsed->diagnostics;
		$style       = new StyleState();
		$html        = $this->nodes( $parsed->document->children, $context, $diagnostics, $style );
		$max_ms      = (int) $this->settings->get( 'max_render_ms', 1500 );
		if ( ( microtime( true ) - $started ) * 1000 > $max_ms ) {
			$diagnostics[] = new Diagnostic( 'warning', 'W_RENDER_SLOW', 'Render exceeded the time budget.', 1, 1, 0, 0 );
		}

		if ( $style->has_form && $context->post_id > 0 ) {
			$html = sprintf(
				'<form class="dolpress-form" method="post" data-dolpress-post="%d">%s<button type="submit" class="dolpress-form__submit" data-dolpress-action="submit-form">%s</button></form>',
				$context->post_id,
				$html,
				esc_html__( 'Save fields', 'dolpress' )
			);
		}

		$html = '<div class="dolpress-document">' . $html . '</div>';
		$html = Html::kses( $html );
		$html = (string) apply_filters( 'dolpress/rendered_html', $html, $context );

		return new RenderResult( $html, $diagnostics, $context->dependencies );
	}

	/**
	 * @param list<Node> $nodes
	 * @param list<Diagnostic> $diagnostics
	 */
	private function nodes( array $nodes, RenderContext $context, array &$diagnostics, StyleState $style ): string {
		$html   = '';
		$inline = '';

		$flush = static function () use ( &$html, &$inline ): void {
			$trimmed = trim( $inline );
			if ( '' === $trimmed ) {
				$inline = '';
				return;
			}
			$html  .= '<p>' . $trimmed . '</p>';
			$inline = '';
		};

		foreach ( $nodes as $node ) {
			if ( $node instanceof TextNode ) {
				$inline .= $style->wrap( $this->collapse_text( $node->value ) );
				continue;
			}

			if ( $node instanceof CommandNode && 'CR' === strtoupper( $node->code ) ) {
				$flush();
				continue;
			}

			$chunk = $this->command_html( $node, $context, $diagnostics, $style );
			if ( '' === $chunk ) {
				continue;
			}

			if ( $this->is_block( $node ) ) {
				$flush();
				$html .= $chunk;
				continue;
			}

			$inline .= $chunk;
		}

		$flush();

		return $html;
	}

	/**
	 * @param list<Diagnostic> $diagnostics
	 */
	private function command_html( Node $node, RenderContext $context, array &$diagnostics, StyleState $style ): string {
		if ( ! $node instanceof CommandNode ) {
			return '';
		}

		if ( $node->malformed || $node->unknown ) {
			return $this->unknown( $node, $context );
		}

		$command = $this->commands->get( $node->code );
		if ( ! $command ) {
			return $this->unknown( $node, $context );
		}

		$validated = $command->validate( $node->named_arguments(), $node->flags, $context );
		foreach ( $validated['diagnostics'] as $item ) {
			$diagnostics[] = new Diagnostic(
				(string) $item['severity'],
				(string) $item['code'],
				(string) $item['message'],
				$node->line,
				$node->column,
				$node->offset,
				$node->length
			);
		}

		if ( ! $validated['ok'] ) {
			return $this->unknown( $node, $context );
		}

		$arguments = $validated['arguments'];
		$code      = strtoupper( $node->code );
		if ( in_array( $code, Codes::STATE, true ) ) {
			$this->apply_state( $code, $arguments, $style );
			return '';
		}

		if ( in_array( $code, array( 'DA', 'CB', 'LS' ), true ) ) {
			$style->has_form = true;
		}

		if ( array() !== $node->children ) {
			$arguments['_children'] = $this->nodes( $node->children, $context, $diagnostics, clone $style );
		}

		try {
			return $command->render( $arguments, $node->flags, $context );
		} catch ( \Throwable $exception ) {
			$diagnostics[] = new Diagnostic(
				'error',
				'E_RENDER',
				'Command failed to render.',
				$node->line,
				$node->column,
				$node->offset,
				$node->length
			);
			return $this->unknown( $node, $context );
		}
	}

	/**
	 * @param array<string, mixed> $arguments
	 */
	private function apply_state( string $code, array $arguments, StyleState $style ): void {
		$colour = isset( $arguments['COLOR'] ) ? strtolower( (string) $arguments['COLOR'] ) : null;
		$n      = (int) ( $arguments['N'] ?? $arguments['DELTA'] ?? 0 );

		switch ( $code ) {
			case 'FG':
			case 'FD':
				$style->fg = $colour;
				break;
			case 'BG':
			case 'BD':
				$style->bg = $colour;
				break;
			case 'UL':
				$style->ul = ! $style->ul;
				break;
			case 'IV':
				$style->iv = ! $style->iv;
				break;
			case 'HL':
				$style->hl = ! $style->hl;
				break;
			case 'WW':
				$style->ww = ! $style->ww;
				break;
			case 'BK':
				$style->bk = ! $style->bk;
				break;
			case 'ID':
				$style->indent = max( 0, $style->indent + $n );
				break;
			case 'LM':
				$style->lm = $n;
				break;
			case 'RM':
				$style->rm = $n;
				break;
			case 'PL':
				$style->pl = $n;
				break;
			case 'SX':
				$style->sx = $n;
				break;
			case 'SY':
				$style->sy = $n;
				break;
			default:
				break;
		}
	}

	private function is_block( Node $node ): bool {
		if ( ! $node instanceof CommandNode ) {
			return false;
		}

		$code = strtoupper( $node->code );
		if ( in_array( $code, Codes::BLOCK, true ) ) {
			return true;
		}

		if ( 'TX' === $code ) {
			return array() !== array_intersect( $node->flags, self::ALIGN_FLAGS );
		}

		return false;
	}

	private function collapse_text( string $value ): string {
		$collapsed = preg_replace( '/[ \t]*\R[ \t]*/', ' ', str_replace( array( "\r\n", "\r" ), "\n", $value ) );
		$collapsed = is_string( $collapsed ) ? $collapsed : $value;

		if ( '' === trim( $collapsed ) ) {
			return '';
		}

		return Html::text( $collapsed );
	}

	private function unknown( CommandNode $node, RenderContext $context ): string {
		$behaviour = (string) $this->settings->get( 'public_invalid_command', 'hide' );
		if ( $context->is_editor || $context->is_preview ) {
			return '<span class="dolpress-diagnostic" title="' . esc_attr( $node->code ) . '">' . Html::text( $node->raw ) . '</span>';
		}

		if ( 'escape' === $behaviour ) {
			return '<span class="dolpress-unknown">' . Html::text( $node->raw ) . '</span>';
		}

		return '';
	}
}
