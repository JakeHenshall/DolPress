<?php
/**
 * Sprite, song, and optional HTML-code commands.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Commands;

use Nought\DolPress\Rendering\Html;
use Nought\DolPress\Rendering\RenderContext;
use Nought\DolPress\Sprites\Decoder;
use Nought\DolPress\Support\BinStore;
use Nought\DolPress\Support\SettingsRepository;

final class SpriteCommand extends AbstractCommand {
	public function __construct(
		private readonly BinStore $bins,
		private readonly Decoder $decoder
	) {}

	public function code(): string {
		return 'SP';
	}

	public function definition(): array {
		return array(
			'code'      => 'SP',
			'label'     => 'Sprite',
			'help'      => 'Embedded sprite from a document bin, rendered as SVG.',
			'group'     => 'media',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$SP,BI=1$' ),
			'schema'    => array(
				'flags'      => array( 'T', 'DD', 'FST' ),
				'positional' => array(
					array(
						'name' => 'TEXT',
						'type' => 'string',
					),
				),
				'named'      => array(
					array(
						'name' => 'BI',
						'type' => 'int',
						'min'  => 1,
						'max'  => 999,
					),
					array(
						'name' => 'BP',
						'type' => 'string',
					),
					array(
						'name' => 'TAG',
						'type' => 'string',
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		if ( in_array( 'DD', $flags, true ) ) {
			return '';
		}

		$bin = null;
		if ( ! empty( $arguments['BI'] ) ) {
			$bin = $this->bins->get( $context->post_id, (int) $arguments['BI'] );
		} elseif ( ! empty( $arguments['TAG'] ) ) {
			$bin = $this->bins->get_by_tag( $context->post_id, (string) $arguments['TAG'] );
		} elseif ( ! empty( $arguments['BP'] ) ) {
			$bin = $this->import_ptr( (string) $arguments['BP'], $context );
		}

		if ( ! is_array( $bin ) ) {
			return '';
		}

		$context->note_dependency( 'bin', (int) $bin['num'] );
		$svg = $this->decoder->to_svg( (string) $bin['data'] );
		$tag = Html::text( (string) ( $arguments['TEXT'] ?? $bin['tag'] ) );

		return '' === $tag ? $svg : '<figure class="dolpress-sp-figure">' . $svg . '<figcaption>' . $tag . '</figcaption></figure>';
	}

	/**
	 * @return array{num: int, tag: string, data: string}|null
	 */
	private function import_ptr( string $ptr, RenderContext $context ): ?array {
		$parts = array_map( 'trim', explode( ',', $ptr ) );
		$id    = is_numeric( $parts[0] ) ? (int) $parts[0] : 0;
		if ( $id < 1 || ! $context->can_view_attachment( $id ) ) {
			return null;
		}

		$path = function_exists( 'get_attached_file' ) ? get_attached_file( $id ) : false;
		if ( ! is_string( $path ) || ! is_readable( $path ) ) {
			return null;
		}

		$data = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local attachment path from get_attached_file(), not a remote URL.
		if ( ! is_string( $data ) || strlen( $data ) > 262144 ) {
			return null;
		}

		$context->note_dependency( 'attachment', $id );

		return array(
			'num'  => $id,
			'tag'  => (string) ( $parts[1] ?? '' ),
			'data' => $data,
		);
	}
}

final class SongCommand extends AbstractCommand {
	public function code(): string {
		return 'SO';
	}

	public function definition(): array {
		return array(
			'code'      => 'SO',
			'label'     => 'Song',
			'help'      => 'TempleOS note string played by allowlisted frontend audio.',
			'group'     => 'media',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$SO,A="6qG3A3B3"$' ),
			'schema'    => array(
				'flags'      => array( 'T' ),
				'positional' => array(
					array(
						'name' => 'NOTES',
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
		$notes = (string) ( $arguments['A'] ?? $arguments['NOTES'] ?? '' );
		$notes = preg_replace( '/[^A-Ga-g0-9#bqwethx\s]/', '', $notes ) ?? '';
		if ( '' === $notes ) {
			return '';
		}

		return sprintf(
			'<button type="button" class="dolpress-so" data-dolpress-song="%s">%s</button>',
			esc_attr( $notes ),
			esc_html__( 'Play song', 'dolpress' )
		);
	}
}

final class HtmlCodeCommand extends AbstractCommand {
	public function __construct( private readonly SettingsRepository $settings ) {}

	public function code(): string {
		return 'HC';
	}

	public function definition(): array {
		return array(
			'code'      => 'HC',
			'label'     => 'HTML code',
			'help'      => 'Kses-filtered HTML. Disabled until enabled in settings.',
			'group'     => 'layout',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$HC,"<strong>Note</strong>"$' ),
			'schema'    => array(
				'flags'      => array( 'T' ),
				'positional' => array(
					array(
						'name' => 'HTML',
						'type' => 'string',
					),
				),
				'named'      => array(
					array(
						'name' => 'HTML',
						'type' => 'string',
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		if ( ! $this->settings->html_code_enabled() ) {
			return '';
		}

		$html = (string) ( $arguments['HTML'] ?? '' );

		return '<div class="dolpress-hc">' . Html::kses( $html ) . '</div>';
	}
}
