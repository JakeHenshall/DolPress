<?php
/**
 * WordPress-native command handlers.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Commands;

use Nought\DolPress\Rendering\Html;
use Nought\DolPress\Rendering\RenderContext;
use Nought\DolPress\Support\SettingsRepository;

final class SiteCommand extends AbstractCommand {
	public function code(): string {
		return 'WS';
	}

	public function definition(): array {
		return array(
			'code'      => 'WS',
			'label'     => 'Site value',
			'help'      => 'Public site identity field.',
			'group'     => 'wordpress',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$WS,FIELD="name"$' ),
			'schema'    => array(
				'flags'      => array(),
				'positional' => array(),
				'named'      => array(
					array(
						'name'    => 'FIELD',
						'type'    => 'enum',
						'choices' => array( 'name', 'description', 'home', 'url', 'language', 'charset' ),
						'default' => 'name',
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$field = (string) ( $arguments['FIELD'] ?? 'name' );
		$value = match ( $field ) {
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'home'        => home_url( '/' ),
			'url'         => site_url( '/' ),
			'language'    => determine_locale(),
			'charset'     => get_bloginfo( 'charset' ),
			default       => '',
		};

		$context->note_dependency( 'option', 'blogname' );

		if ( in_array( $field, array( 'home', 'url' ), true ) ) {
			return $this->el(
				'a',
				Html::text( $value ),
				array(
					'class' => 'dolpress-ws',
					'href'  => $value,
				)
			);
		}

		return $this->el( 'span', Html::text( $value ), array( 'class' => 'dolpress-ws' ) );
	}
}

final class LogoCommand extends AbstractCommand {
	public function code(): string {
		return 'WG';
	}

	public function definition(): array {
		return array(
			'code'      => 'WG',
			'label'     => 'Site logo',
			'help'      => 'Custom logo or site icon.',
			'group'     => 'wordpress',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$WG,SIZE=96,LINK=home$' ),
			'schema'    => array(
				'flags'      => array(),
				'positional' => array(),
				'named'      => array(
					array(
						'name'    => 'SIZE',
						'type'    => 'int',
						'min'     => 16,
						'max'     => 512,
						'default' => 96,
					),
					array(
						'name'    => 'LINK',
						'type'    => 'enum',
						'choices' => array( 'none', 'home' ),
						'default' => 'home',
					),
					array(
						'name' => 'CLASS',
						'type' => 'string',
					),
					array(
						'name'    => 'KIND',
						'type'    => 'enum',
						'choices' => array( 'logo', 'icon' ),
						'default' => 'logo',
					),
					array(
						'name'    => 'DECORATIVE',
						'type'    => 'bool',
						'default' => false,
					),
					array(
						'name' => 'ALT',
						'type' => 'string',
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$kind = (string) ( $arguments['KIND'] ?? 'logo' );
		$size = (int) ( $arguments['SIZE'] ?? 96 );
		$id   = 'icon' === $kind ? (int) get_option( 'site_icon' ) : (int) get_theme_mod( 'custom_logo' );
		if ( $id < 1 || ! $context->can_view_attachment( $id ) ) {
			return '';
		}

		$context->note_dependency( 'attachment', $id );
		$alt  = ! empty( $arguments['DECORATIVE'] ) ? '' : (string) ( $arguments['ALT'] ?? get_bloginfo( 'name' ) );
		$html = wp_get_attachment_image(
			$id,
			array( $size, $size ),
			false,
			array(
				'class' => 'dolpress-wg',
				'alt'   => $alt,
			)
		);
		if ( 'home' === ( $arguments['LINK'] ?? 'home' ) && '' !== $html ) {
			$html = '<a class="dolpress-wg-link" href="' . esc_url( home_url( '/' ) ) . '">' . $html . '</a>';
		}

		return $html;
	}
}

final class CurrentPostCommand extends AbstractCommand {
	public function code(): string {
		return 'WT';
	}

	public function definition(): array {
		return array(
			'code'      => 'WT',
			'label'     => 'Current post',
			'help'      => 'Field from the current post.',
			'group'     => 'wordpress',
			'cacheable' => false,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$WT,FIELD="title"$' ),
			'schema'    => array(
				'flags'      => array(),
				'positional' => array(),
				'named'      => array(
					array(
						'name'    => 'FIELD',
						'type'    => 'enum',
						'choices' => array( 'title', 'url', 'date', 'modified', 'excerpt', 'image', 'id', 'type' ),
						'default' => 'title',
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$post = get_post( $context->post_id );
		if ( ! $post instanceof \WP_Post || ! $context->can_view_post( $post->ID ) ) {
			return '';
		}

		$field = (string) ( $arguments['FIELD'] ?? 'title' );
		return match ( $field ) {
			'title'    => $this->el( 'span', Html::text( get_the_title( $post ) ), array( 'class' => 'dolpress-wt' ) ),
			'url'      => $this->el(
				'a',
				Html::text( get_the_title( $post ) ), array(
					'href'  => get_permalink( $post ),
					'class' => 'dolpress-wt',
				)
			),
			'date'     => $this->el( 'time', Html::text( get_the_date( '', $post ) ), array( 'datetime' => get_post_time( 'c', true, $post ) ) ),
			'modified' => $this->el( 'time', Html::text( get_the_modified_date( '', $post ) ), array( 'datetime' => get_post_modified_time( 'c', true, $post ) ) ),
			'excerpt'  => $this->el( 'span', Html::text( wp_strip_all_tags( get_the_excerpt( $post ) ) ), array( 'class' => 'dolpress-wt' ) ),
			'image'    => (string) get_the_post_thumbnail( $post, 'large', array( 'class' => 'dolpress-wt-image' ) ),
			'id'       => $this->el( 'span', (string) $post->ID, array( 'class' => 'dolpress-wt' ) ),
			'type'     => $this->el( 'span', Html::text( $post->post_type ), array( 'class' => 'dolpress-wt' ) ),
			default    => '',
		};
	}
}

final class AuthorCommand extends AbstractCommand {
	public function code(): string {
		return 'WA';
	}

	public function definition(): array {
		return array(
			'code'      => 'WA',
			'label'     => 'Author',
			'help'      => 'Public author profile fields. Email is never exposed.',
			'group'     => 'wordpress',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$WA,FIELD="name"$' ),
			'schema'    => array(
				'flags'      => array(),
				'positional' => array(),
				'named'      => array(
					array(
						'name'    => 'FIELD',
						'type'    => 'enum',
						'choices' => array( 'name', 'avatar', 'url', 'bio' ),
						'default' => 'name',
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$post = get_post( $context->post_id );
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$author_id = (int) $post->post_author;
		$field     = (string) ( $arguments['FIELD'] ?? 'name' );
		$context->note_dependency( 'user', $author_id );

		return match ( $field ) {
			'name'   => $this->el( 'span', Html::text( get_the_author_meta( 'display_name', $author_id ) ), array( 'class' => 'dolpress-wa' ) ),
			'avatar' => (string) get_avatar( $author_id, 96, '', get_the_author_meta( 'display_name', $author_id ), array( 'class' => 'dolpress-wa-avatar' ) ),
			'url'    => $this->el(
				'a',
				Html::text( get_the_author_meta( 'display_name', $author_id ) ), array(
					'href'  => get_author_posts_url( $author_id ),
					'class' => 'dolpress-wa',
				)
			),
			'bio'    => $this->el( 'span', Html::text( (string) get_the_author_meta( 'description', $author_id ) ), array( 'class' => 'dolpress-wa' ) ),
			default  => '',
		};
	}
}

final class TaxonomyCommand extends AbstractCommand {
	public function code(): string {
		return 'WX';
	}

	public function definition(): array {
		return array(
			'code'      => 'WX',
			'label'     => 'Taxonomy terms',
			'help'      => 'Public terms for the current post.',
			'group'     => 'wordpress',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$WX,TAXONOMY="category"$' ),
			'schema'    => array(
				'flags'      => array(),
				'positional' => array(),
				'named'      => array(
					array(
						'name'    => 'TAXONOMY',
						'type'    => 'string',
						'default' => 'category',
					),
					array(
						'name'    => 'SEP',
						'type'    => 'string',
						'default' => ', ',
					),
					array(
						'name'    => 'LINK',
						'type'    => 'bool',
						'default' => true,
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$taxonomy = sanitize_key( (string) ( $arguments['TAXONOMY'] ?? 'category' ) );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}

		$tax = get_taxonomy( $taxonomy );
		if ( ! $tax || ! $tax->public ) {
			return '';
		}

		$terms = get_the_terms( $context->post_id, $taxonomy );
		if ( ! is_array( $terms ) || array() === $terms ) {
			return '';
		}

		$sep  = (string) ( $arguments['SEP'] ?? ', ' );
		$link = ! isset( $arguments['LINK'] ) || $arguments['LINK'];
		$out  = array();
		foreach ( $terms as $term ) {
			$label = Html::text( $term->name );
			$out[] = $link ? '<a href="' . esc_url( get_term_link( $term ) ) . '">' . $label . '</a>' : $label;
			$context->note_dependency( 'term', $term->term_id );
		}

		return '<span class="dolpress-wx">' . implode( Html::text( $sep ), $out ) . '</span>';
	}
}

final class MenuCommand extends AbstractCommand {
	public function code(): string {
		return 'WN';
	}

	public function definition(): array {
		return array(
			'code'      => 'WN',
			'label'     => 'Navigation',
			'help'      => 'Theme location or visible menu ID.',
			'group'     => 'wordpress',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$WN,LOCATION="primary",DEPTH=2$' ),
			'schema'    => array(
				'flags'      => array(),
				'positional' => array(),
				'named'      => array(
					array(
						'name' => 'LOCATION',
						'type' => 'string',
					),
					array(
						'name' => 'ID',
						'type' => 'int',
						'min'  => 1,
					),
					array(
						'name'    => 'DEPTH',
						'type'    => 'int',
						'min'     => 1,
						'max'     => 4,
						'default' => 2,
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$depth = (int) ( $arguments['DEPTH'] ?? 2 );
		$args  = array(
			'container'       => 'nav',
			'container_class' => 'dolpress-wn',
			'echo'            => false,
			'depth'           => $depth,
			'fallback_cb'     => '__return_empty_string',
		);

		if ( ! empty( $arguments['LOCATION'] ) ) {
			$args['theme_location'] = sanitize_key( (string) $arguments['LOCATION'] );
		} elseif ( ! empty( $arguments['ID'] ) ) {
			$args['menu'] = (int) $arguments['ID'];
		} else {
			return '';
		}

		$html = (string) wp_nav_menu( $args );
		$context->note_dependency( 'menu', (string) ( $arguments['LOCATION'] ?? $arguments['ID'] ?? '' ) );

		return $html;
	}
}

final class BreadcrumbCommand extends AbstractCommand {
	public function code(): string {
		return 'WB';
	}

	public function definition(): array {
		return array(
			'code'      => 'WB',
			'label'     => 'Breadcrumbs',
			'help'      => 'Hierarchy breadcrumbs for the current post.',
			'group'     => 'wordpress',
			'cacheable' => false,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$WB$' ),
			'schema'    => array(
				'flags'      => array(),
				'positional' => array(),
				'named'      => array(
					array(
						'name'    => 'SEP',
						'type'    => 'string',
						'default' => ' / ',
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$post = get_post( $context->post_id );
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$items     = array();
		$items[]   = '<a href="' . esc_url( home_url( '/' ) ) . '">' . Html::text( get_bloginfo( 'name' ) ) . '</a>';
		$ancestors = array_reverse( get_post_ancestors( $post ) );
		foreach ( $ancestors as $ancestor ) {
			if ( ! $context->can_view_post( (int) $ancestor ) ) {
				continue;
			}
			$items[] = '<a href="' . esc_url( get_permalink( $ancestor ) ) . '">' . Html::text( get_the_title( $ancestor ) ) . '</a>';
		}
		$items[] = '<span aria-current="page">' . Html::text( get_the_title( $post ) ) . '</span>';

		$sep = Html::text( (string) ( $arguments['SEP'] ?? ' / ' ) );
		return '<nav class="dolpress-wb" aria-label="' . esc_attr__( 'Breadcrumb', 'dolpress' ) . '">' . implode( $sep, $items ) . '</nav>';
	}
}

final class SelectedPostCommand extends AbstractCommand {
	public function code(): string {
		return 'WP';
	}

	public function definition(): array {
		return array(
			'code'      => 'WP',
			'label'     => 'Selected post',
			'help'      => 'Approved field from a public post ID. Never renders post_content.',
			'group'     => 'wordpress',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$WP,ID=12,FIELD="title"$' ),
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
						'name'    => 'FIELD',
						'type'    => 'enum',
						'choices' => array( 'title', 'url', 'date', 'excerpt', 'image' ),
						'default' => 'title',
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$id = (int) ( $arguments['ID'] ?? 0 );
		if ( $id < 1 || ! $context->can_view_post( $id ) ) {
			return '';
		}

		$child = $context->child( $id );
		if ( ! $child ) {
			return '';
		}

		$inner = new CurrentPostCommand();
		return $inner->render( array( 'FIELD' => $arguments['FIELD'] ?? 'title' ), array(), $child );
	}
}

final class LoopCommand extends AbstractCommand {
	public function __construct( private readonly SettingsRepository $settings ) {}

	public function code(): string {
		return 'WL';
	}

	public function definition(): array {
		return array(
			'code'      => 'WL',
			'label'     => 'Post loop',
			'help'      => 'Restricted public post listing.',
			'group'     => 'wordpress',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$WL,TYPE="latest",COUNT=5,SHOW="title,date,excerpt"$' ),
			'schema'    => array(
				'flags'      => array(),
				'positional' => array(),
				'named'      => array(
					array(
						'name'    => 'TYPE',
						'type'    => 'enum',
						'choices' => array( 'latest', 'related', 'children', 'manual' ),
						'default' => 'latest',
					),
					array(
						'name'    => 'POST_TYPE',
						'type'    => 'string',
						'default' => 'post',
					),
					array(
						'name'    => 'COUNT',
						'type'    => 'int',
						'min'     => 1,
						'max'     => 20,
						'default' => 5,
					),
					array(
						'name' => 'CATEGORY',
						'type' => 'string',
					),
					array(
						'name' => 'TAG',
						'type' => 'string',
					),
					array(
						'name' => 'TAXONOMY',
						'type' => 'string',
					),
					array(
						'name' => 'TERM',
						'type' => 'string',
					),
					array(
						'name' => 'IDS',
						'type' => 'string',
					),
					array(
						'name'    => 'ORDER',
						'type'    => 'enum',
						'choices' => array( 'asc', 'desc' ),
						'default' => 'desc',
					),
					array(
						'name'    => 'ORDERBY',
						'type'    => 'enum',
						'choices' => array( 'date', 'title', 'menu_order', 'rand' ),
						'default' => 'date',
					),
					array(
						'name'    => 'SHOW',
						'type'    => 'string',
						'default' => 'title,date',
					),
					array(
						'name' => 'EMPTY',
						'type' => 'string',
					),
					array(
						'name'    => 'LAYOUT',
						'type'    => 'enum',
						'choices' => array( 'list', 'grid' ),
						'default' => 'list',
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$max = (int) $this->settings->get( 'max_query_count', 10 );
		if ( $context->query_count >= $max ) {
			return '';
		}
		++$context->query_count;

		$post_type = sanitize_key( (string) ( $arguments['POST_TYPE'] ?? 'post' ) );
		$object    = get_post_type_object( $post_type );
		if ( ! $object || ! $object->public ) {
			return '';
		}

		$count = (int) ( $arguments['COUNT'] ?? 5 );
		$query = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => $count,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'has_password'        => false,
			'order'               => strtoupper( (string) ( $arguments['ORDER'] ?? 'desc' ) ),
			'orderby'             => (string) ( $arguments['ORDERBY'] ?? 'date' ),
		);

		$type = (string) ( $arguments['TYPE'] ?? 'latest' );
		if ( 'related' === $type ) {
			$terms = get_the_terms( $context->post_id, 'category' );
			if ( is_array( $terms ) && array() !== $terms ) {
				$query['category__in'] = array_map( static fn( $term ) => (int) $term->term_id, $terms );
			}
			$query['post__not_in'] = array( $context->post_id );
		} elseif ( 'children' === $type ) {
			$query['post_parent'] = $context->post_id;
		} elseif ( 'manual' === $type ) {
			$ids = array();
			foreach ( explode( ',', (string) ( $arguments['IDS'] ?? '' ) ) as $id ) {
				$id = (int) trim( $id );
				if ( $id > 0 && $context->can_view_post( $id ) ) {
					$ids[] = $id;
				}
			}
			if ( array() === $ids ) {
				return Html::text( (string) ( $arguments['EMPTY'] ?? '' ) );
			}
			$query['post__in'] = $ids;
			$query['orderby']  = 'post__in';
		}

		if ( ! empty( $arguments['CATEGORY'] ) ) {
			$query['category_name'] = sanitize_title( (string) $arguments['CATEGORY'] );
		}
		if ( ! empty( $arguments['TAG'] ) ) {
			$query['tag'] = sanitize_title( (string) $arguments['TAG'] );
		}
		if ( ! empty( $arguments['TAXONOMY'] ) && ! empty( $arguments['TERM'] ) ) {
			$taxonomy = sanitize_key( (string) $arguments['TAXONOMY'] );
			$tax      = get_taxonomy( $taxonomy );
			if ( $tax && $tax->public ) {
				$query['tax_query'] = array(
					array(
						'taxonomy' => $taxonomy,
						'field'    => 'slug',
						'terms'    => sanitize_title( (string) $arguments['TERM'] ),
					),
				);
			}
		}

		$loop = new \WP_Query( $query );
		if ( ! $loop->have_posts() ) {
			wp_reset_postdata();
			return Html::text( (string) ( $arguments['EMPTY'] ?? '' ) );
		}

		$show         = array_filter( array_map( 'trim', explode( ',', strtolower( (string) ( $arguments['SHOW'] ?? 'title,date' ) ) ) ) );
		$allowed_show = array( 'title', 'url', 'date', 'excerpt', 'image', 'author', 'terms' );
		$show         = array_values( array_intersect( $show, $allowed_show ) );
		$layout       = (string) ( $arguments['LAYOUT'] ?? 'list' );
		$tag          = 'grid' === $layout ? 'div' : 'ul';
		$item         = 'grid' === $layout ? 'article' : 'li';
		$html         = '<' . $tag . ' class="dolpress-wl dolpress-wl--' . esc_attr( $layout ) . '">';

		foreach ( $loop->posts as $post ) {
			if ( ! $post instanceof \WP_Post || ! $context->can_view_post( $post->ID ) ) {
				continue;
			}
			$context->note_dependency( 'post', $post->ID );
			$html .= '<' . $item . ' class="dolpress-wl__item">';
			if ( in_array( 'image', $show, true ) ) {
				$html .= get_the_post_thumbnail( $post, 'medium', array( 'class' => 'dolpress-wl__image' ) );
			}
			if ( in_array( 'title', $show, true ) || in_array( 'url', $show, true ) ) {
				$html .= '<a class="dolpress-wl__title" href="' . esc_url( get_permalink( $post ) ) . '">' . Html::text( get_the_title( $post ) ) . '</a>';
			}
			if ( in_array( 'date', $show, true ) ) {
				$html .= '<time datetime="' . esc_attr( get_post_time( 'c', true, $post ) ) . '">' . Html::text( get_the_date( '', $post ) ) . '</time>';
			}
			if ( in_array( 'excerpt', $show, true ) ) {
				$html .= '<p class="dolpress-wl__excerpt">' . Html::text( wp_strip_all_tags( get_the_excerpt( $post ) ) ) . '</p>';
			}
			if ( in_array( 'author', $show, true ) ) {
				$html .= '<span class="dolpress-wl__author">' . Html::text( get_the_author_meta( 'display_name', (int) $post->post_author ) ) . '</span>';
			}
			$html .= '</' . $item . '>';
		}

		$html .= '</' . $tag . '>';
		wp_reset_postdata();

		return $html;
	}
}

final class CommentsCommand extends AbstractCommand {
	public function code(): string {
		return 'WC';
	}

	public function definition(): array {
		return array(
			'code'      => 'WC',
			'label'     => 'Comments',
			'help'      => 'Approved comments list, count, or native comment form.',
			'group'     => 'wordpress',
			'cacheable' => false,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$WC,MODE="list"$', '$WC,MODE="form"$' ),
			'schema'    => array(
				'flags'      => array(),
				'positional' => array(),
				'named'      => array(
					array(
						'name'    => 'MODE',
						'type'    => 'enum',
						'choices' => array( 'list', 'count', 'form' ),
						'default' => 'list',
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$post = get_post( $context->post_id );
		if ( ! $post instanceof \WP_Post || post_password_required( $post ) ) {
			return '';
		}

		$mode = (string) ( $arguments['MODE'] ?? 'list' );
		if ( 'count' === $mode ) {
			return $this->el( 'span', (string) get_comments_number( $post ), array( 'class' => 'dolpress-wc-count' ) );
		}

		if ( 'form' === $mode ) {
			if ( ! comments_open( $post ) ) {
				return '';
			}
			ob_start();
			comment_form( array(), $post->ID );
			return '<div class="dolpress-wc-form">' . (string) ob_get_clean() . '</div>';
		}

		$comments = get_comments(
			array(
				'post_id' => $post->ID,
				'status'  => 'approve',
				'type'    => 'comment',
				'number'  => 50,
			)
		);

		if ( array() === $comments ) {
			return '';
		}

		$html = '<ol class="dolpress-wc">';
		foreach ( $comments as $comment ) {
			$html .= '<li class="dolpress-wc__item"><strong>' . Html::text( $comment->comment_author ) . '</strong> ';
			$html .= '<time datetime="' . esc_attr( $comment->comment_date_gmt ) . '">' . Html::text( get_comment_date( '', $comment ) ) . '</time>';
			$html .= '<div>' . wp_kses_post( $comment->comment_content ) . '</div></li>';
		}
		$html .= '</ol>';
		$context->note_dependency( 'comment', $post->ID );

		return $html;
	}
}

final class MetaCommand extends AbstractCommand {
	public function __construct( private readonly SettingsRepository $settings ) {}

	public function code(): string {
		return 'WM';
	}

	public function definition(): array {
		return array(
			'code'      => 'WM',
			'label'     => 'Custom field',
			'help'      => 'Administrator-allowlisted public post meta.',
			'group'     => 'wordpress',
			'cacheable' => true,
			'public'    => true,
			'preview'   => true,
			'examples'  => array( '$WM,KEY="subtitle",TYPE="text"$' ),
			'schema'    => array(
				'flags'      => array(),
				'positional' => array(),
				'named'      => array(
					array(
						'name'     => 'KEY',
						'type'     => 'string',
						'required' => true,
					),
					array(
						'name'    => 'TYPE',
						'type'    => 'enum',
						'choices' => array( 'text', 'number', 'boolean', 'date', 'url', 'attachment', 'post' ),
						'default' => 'text',
					),
				),
			),
		);
	}

	public function render( array $arguments, array $flags, RenderContext $context ): string {
		$key = (string) ( $arguments['KEY'] ?? '' );
		if ( ! $this->settings->is_meta_key_allowed( $key ) || str_starts_with( $key, '_' ) ) {
			return '';
		}

		$value = get_post_meta( $context->post_id, $key, true );
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$type = (string) ( $arguments['TYPE'] ?? 'text' );
		$context->note_dependency( 'meta', $key );

		return match ( $type ) {
			'number'     => $this->el( 'span', Html::text( (string) ( is_numeric( $value ) ? $value : '' ) ), array( 'class' => 'dolpress-wm' ) ),
			'boolean'    => $this->el( 'span', $value ? 'true' : 'false', array( 'class' => 'dolpress-wm' ) ),
			'date'       => $this->el( 'time', Html::text( (string) $value ) ),
			'url'        => is_string( $value ) && wp_http_validate_url( $value ) ? $this->el( 'a', Html::text( $value ), array( 'href' => esc_url( $value ) ) ) : '',
			'attachment' => is_numeric( $value ) && $context->can_view_attachment( (int) $value ) ? (string) wp_get_attachment_image( (int) $value, 'medium' ) : '',
			'post'       => is_numeric( $value ) && $context->can_view_post( (int) $value ) ? $this->el( 'a', Html::text( get_the_title( (int) $value ) ), array( 'href' => get_permalink( (int) $value ) ) ) : '',
			default      => $this->el( 'span', Html::text( wp_strip_all_tags( (string) $value ) ), array( 'class' => 'dolpress-wm' ) ),
		};
	}
}
