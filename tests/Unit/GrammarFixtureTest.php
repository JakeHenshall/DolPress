<?php
/**
 * Shared grammar fixture tests.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Tests\Unit;

use Nought\DolPress\Parser\CommandNode;
use Nought\DolPress\Parser\DocumentNode;
use Nought\DolPress\Parser\Node;
use Nought\DolPress\Parser\Parser;
use Nought\DolPress\Parser\Serializer;
use Nought\DolPress\Parser\TextNode;
use PHPUnit\Framework\TestCase;

final class GrammarFixtureTest extends TestCase {
	/**
	 * @dataProvider fixtures
	 * @param array<string, mixed> $fixture
	 */
	public function test_fixture( array $fixture ): void {
		$parser = new Parser();
		$result = $parser->parse( (string) $fixture['source'] );
		$codes  = array_map( static fn( $item ) => $item->code, $result->diagnostics );

		foreach ( $fixture['diagnostics'] as $expected ) {
			$this->assertContains( $expected['code'], $codes, $fixture['name'] );
		}

			$this->assertTrue(
			$this->match_tree( $result->document->children, $fixture['tree'] ),
			$fixture['name'] . ' AST mismatch: ' . json_encode( $this->simplify( $result->document ) )
		);
	}

	public function test_round_trip_plain_commands(): void {
		$source = '$CR$$HR$$UL$';
		$parser = new Parser();
		$parsed = $parser->parse( $source );
		$again  = ( new Serializer() )->serialize( $parsed->document );
		$this->assertSame( $source, $again );
	}

	public function test_parse_is_side_effect_free(): void {
		$parser = new Parser();
		$a      = $parser->parse( '$TX,"Hi"$' );
		$b      = $parser->parse( '$TX,"Hi"$' );
		$this->assertSame( $a->document->to_array(), $b->document->to_array() );
	}

	/**
	 * @return list<list<array<string, mixed>>>
	 */
	public static function fixtures(): array {
		$path = dirname( __DIR__, 2 ) . '/grammar/fixtures.json';
		$data = json_decode( (string) file_get_contents( $path ), true );
		$out  = array();
		foreach ( $data as $fixture ) {
			$out[ $fixture['name'] ] = array( $fixture );
		}

		return $out;
	}

	/**
	 * @param list<Node> $nodes
	 * @param list<array<string, mixed>> $expected
	 */
	private function match_tree( array $nodes, array $expected ): bool {
		if ( count( $nodes ) !== count( $expected ) ) {
			return false;
		}

		foreach ( $nodes as $i => $node ) {
			$want = $expected[ $i ];
			if ( $node instanceof TextNode ) {
				if ( ( $want['type'] ?? '' ) !== 'text' ) {
					return false;
				}
				if ( isset( $want['value'] ) && $want['value'] !== $node->value ) {
					return false;
				}
				continue;
			}

			if ( ! $node instanceof CommandNode || ( $want['type'] ?? '' ) !== 'command' ) {
				return false;
			}

			if ( isset( $want['code'] ) && $want['code'] !== $node->code ) {
				return false;
			}
			if ( isset( $want['flags'] ) && $want['flags'] !== $node->flags ) {
				return false;
			}
			if ( isset( $want['unknown'] ) && (bool) $want['unknown'] !== $node->unknown ) {
				return false;
			}
			if ( isset( $want['malformed'] ) && (bool) $want['malformed'] !== $node->malformed ) {
				return false;
			}
			if ( isset( $want['arguments'] ) ) {
				foreach ( $want['arguments'] as $j => $arg ) {
					$have = $node->arguments[ $j ] ?? array();
					foreach ( $arg as $key => $value ) {
						if ( ( $have[ $key ] ?? null ) !== $value ) {
							return false;
						}
					}
				}
			}
			if ( isset( $want['children'] ) && ! $this->match_tree( $node->children, $want['children'] ) ) {
				return false;
			}
		}

		return true;
	}

	private function simplify( DocumentNode $document ): array {
		return $document->to_array()['children'];
	}
}
