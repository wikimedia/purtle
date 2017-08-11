<?php

namespace Wikimedia\Purtle\Tests;

use Wikimedia\Purtle\RdfWriter;
use Wikimedia\Purtle\JsonLdRdfWriter;

/**
 * @covers Wikimedia\Purtle\JsonLdRdfWriter
 * @covers Wikimedia\Purtle\RdfWriterBase
 *
 * @group Purtle
 * @group RdfWriter
 *
 * @license GPL-2.0+
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
		$this->assertEquals( $writer->encode( "foo{bar}bat", 0 ), '"foo{bar}bat"' );
		$this->assertEquals( $writer->encode( [], 0 ), "" );
		$this->assertEquals( $writer->encode( [
			"@id" => "foo"
		], 0 ), "    \"@id\": \"foo\"" );
		$this->assertEquals(
			$writer->encode( [ 1, 2, 3 ], 0 ),
			"    1,\n    2,\n    3"
		);
	}

}
