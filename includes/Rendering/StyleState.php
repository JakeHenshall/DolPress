<?php
/**
 * DolDoc-like attribute stack for following text.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Rendering;

final class StyleState {
	public ?string $fg    = null;
	public ?string $bg    = null;
	public bool $ul       = false;
	public bool $iv       = false;
	public bool $hl       = false;
	public bool $ww       = true;
	public bool $bk       = false;
	public int $indent    = 0;
	public int $sx        = 0;
	public int $sy        = 0;
	public ?int $lm       = null;
	public ?int $rm       = null;
	public ?int $pl       = null;
	public bool $has_form = false;

	public function wrap( string $html ): string {
		if ( '' === $html ) {
			return '';
		}

		$class = array( 'dolpress-run' );
		$style = array();

		if ( null !== $this->fg ) {
			$class[] = 'dolpress-fg dolpress-colour-' . strtolower( $this->fg );
		}
		if ( null !== $this->bg ) {
			$class[] = 'dolpress-bg dolpress-bg-' . strtolower( $this->bg );
		}
		if ( $this->ul ) {
			$class[] = 'dolpress-ul';
		}
		if ( $this->iv ) {
			$class[] = 'dolpress-iv';
		}
		if ( $this->hl ) {
			$class[] = 'dolpress-hl';
		}
		if ( $this->bk ) {
			$class[] = 'dolpress-bk';
		}
		if ( ! $this->ww ) {
			$class[] = 'dolpress-nowrap';
		}
		if ( $this->indent > 0 ) {
			$style[] = 'margin-left:' . ( $this->indent * 0.5 ) . 'em';
		}
		if ( 0 !== $this->sx || 0 !== $this->sy ) {
			$style[] = 'position:relative;left:' . $this->sx . 'px;top:' . $this->sy . 'px';
		}
		if ( null !== $this->lm ) {
			$style[] = 'padding-left:' . max( 0, $this->lm ) . 'ch';
		}
		if ( null !== $this->rm ) {
			$style[] = 'padding-right:' . max( 0, $this->rm ) . 'ch';
		}

		$attr = ' class="' . esc_attr( implode( ' ', $class ) ) . '"';
		if ( array() !== $style ) {
			$attr .= ' style="' . esc_attr( implode( ';', $style ) ) . '"';
		}

		return '<span' . $attr . '>' . $html . '</span>';
	}
}
