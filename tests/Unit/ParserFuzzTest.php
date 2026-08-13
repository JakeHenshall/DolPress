<?php
/**
 * Parser fuzz: random input must not throw.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Tests\Unit;

use Nought\DolPress\Parser\Parser;
use PHPUnit\Framework\TestCase;

final class ParserFuzzTest extends TestCase {
	public function test_random_corpus_does_not_throw(): void {
		$parser = new Parser();
		$seeds  = array( '', '$', '$$', '$CR$', '$TX,"x"$', '$TR$a$/TR$', "\0\n\r$", '$ZZ$', '$1$', str_repeat( 'A', 5000 ) );

		for ( $i = 0; $i < 40; $i++ ) {
			$seeds[] = $this->noise( $i + 1 );
		}

		foreach ( $seeds as $source ) {
			$result = $parser->parse( $source );
			$this->assertNotNull( $result->document );
		}
	}

	private function noise( int $seed ): string {
		mt_srand( $seed );
		$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ$+,"=/\n\t 0123456789';
		$out   = '';
		$len   = 80 + ( $seed % 40 );
		for ( $i = 0; $i < $len; $i++ ) {
			$out .= $chars[ mt_rand( 0, strlen( $chars ) - 1 ) ];
		}

		return $out;
	}
}
