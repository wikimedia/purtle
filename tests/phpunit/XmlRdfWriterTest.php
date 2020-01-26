<?php

namespace Wikimedia\Purtle\Tests;

use Wikimedia\Purtle\RdfWriter;
use Wikimedia\Purtle\XmlRdfWriter;

/**
 * @covers \Wikimedia\Purtle\XmlRdfWriter
 * @covers \Wikimedia\Purtle\RdfWriterBase
 *
 * @uses \Wikimedia\Purtle\BNodeLabeler
 *
 * @group Purtle
 * @group RdfWriter
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class XmlRdfWriterTest extends RdfWriterTestBase {

	protected function getFileSuffix() {
		return 'rdf';
	}

	/**
	 * @return RdfWriter
	 */
	protected function newWriter() {
		return new XmlRdfWriter();
	}

}
