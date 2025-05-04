<?php

namespace Wikimedia\Purtle\Tests;

use PHPUnit\Framework\TestCase;
use Wikimedia\Purtle\N3Quoter;
use Wikimedia\Purtle\UnicodeEscaper;

/**
 * @covers \Wikimedia\Purtle\N3Quoter
 *
 * @uses \Wikimedia\Purtle\UnicodeEscaper
 *
 * @group Purtle
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class N3QuoterTest extends TestCase {

	public static function provideEscapeIRI() {
		return [
			[
				'http://acme.com/test.php?x=y&foo=bar#part',
				'http://acme.com/test.php?x=y&foo=bar#part',
			],
			[
				'http://acme.com/"evil stuff"',
				'http://acme.com/%22evil%20stuff%22',
			],
			[
				'http://acme.com/<wacky stuff>',
				'http://acme.com/%3Cwacky%20stuff%3E',
			],
			[
				'http://acme.com\\back\\slash',
				'http://acme.com%5Cback%5Cslash',
			],
			[
				'http://acme.com/~`!@#$%^&*()-_=+[]{}|:;\'",.<>/?',
				'http://acme.com/~%60!@#$%%5E&*()-_=+[]%7B%7D%7C:;\'%22,.%3C%3E/?',
			],
		];
	}

	/**
	 * @dataProvider provideEscapeIRI
	 */
	public function testEscapeIRI( $iri, $expected ) {
		$quoter = new N3Quoter();

		$this->assertEquals( $expected, $quoter->escapeIRI( $iri ) );
	}

	public static function provideEscapeLiteral() {
		$shortCircuitedChars = ' !#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ' .
			'[]^_`abcdefghijklmnopqrstuvwxyz{|}~';

		return [
			[ 'Hello World', 'Hello World' ],
			[ 'Hello#World', 'Hello#World' ],
			[ 'Hello[<f>a+n%cy]World^', 'Hello[<f>a+n%cy]World^' ],
			[ $shortCircuitedChars, $shortCircuitedChars ],
			[ $shortCircuitedChars, $shortCircuitedChars, true ],
			[ 'Hello"World', 'Hello\\"World' ],
			[ "Hello\nWorld", 'Hello\nWorld' ],
			[ "Hello\tWorld", 'Hello\tWorld' ],
			[ 'Hällo Wörld', 'Hällo Wörld', false ],
			[ 'Hällo Wörld', 'H\u00E4llo W\u00F6rld', true ],
			[ '\a', '\\\\a' ],
			[ "\x7\v\0\x1F", '\u0007\u000B\u0000\u001F' ],
		];
	}

	/**
	 * @dataProvider provideEscapeLiteral
	 */
	public function testEscapeLiteral( $literal, $expected, $escapeUnicode = false ) {
		$quoter = new N3Quoter();
		$quoter->setEscapeUnicode( $escapeUnicode );

		$actual = $quoter->escapeLiteral( $literal );
		$this->assertSame( $expected, $actual );

		if ( $escapeUnicode ) {
			// Make sure unicode escaping was correctly applied:
			$escaper = new UnicodeEscaper();
			$this->assertSame( $escaper->escapeString( $expected ), $actual );
		}
	}

}
