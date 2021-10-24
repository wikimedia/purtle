<?php

namespace Wikimedia\Purtle\Tests;

use Wikimedia\Purtle\JsonLdRdfWriter;
use Wikimedia\Purtle\RdfWriter;

/**
 * @covers \Wikimedia\Purtle\JsonLdRdfWriter
 * @covers \Wikimedia\Purtle\RdfWriterBase
 *
 * @group Purtle
 * @group RdfWriter
 *
 * @license GPL-2.0-or-later
 * @author C. Scott Ananian
 */
class JsonLdRdfWriterTest extends RdfWriterTestBase {

	/**
	 * @return string
	 */
	protected function getFileSuffix() {
		return 'jsonld';
	}

	/**
	 * @return RdfWriter
	 */
	protected function newWriter() {
		return new JsonLdRdfWriter();
	}

	public function testEncode() {
		$writer = new JsonLdRdfWriter();
		$this->assertEquals( '"foo{bar}bat"', $writer->encode( 'foo{bar}bat', 0 ) );
		$this->assertSame( '', $writer->encode( [], 0 ) );
		$this->assertEquals( '    "@id": "foo"', $writer->encode( [
			'@id' => 'foo'
		], 0 ) );
		$this->assertEquals(
			"    1,\n    2,\n    3",
			$writer->encode( [ 1, 2, 3 ], 0 )
		);
	}

}
