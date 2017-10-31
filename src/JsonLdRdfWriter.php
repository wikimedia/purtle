<?php

namespace Wikimedia\Purtle;

use LogicException;

/**
 * RdfWriter implementation for generating JSON-LD output.
 *
 * @license GPL-2.0+
 * @author C. Scott Ananian
 */
class JsonLdRdfWriter extends RdfWriterBase {

	/**
	 * The JSON-LD "@context", which maps terms to IRIs. This is shared with all sub-writers, and a
	 * single context is emitted when the writer is finalized.
	 *
	 * @see https://www.w3.org/TR/json-ld/#the-context
	 *
	 * @var string[]
	 */
	protected $context = [];

	/**
	 * The JSON-LD "@graph", which lists all the nodes described by this JSON-LD object.
	 * We apply an optimization eliminating the "@graph" entry if it consists
	 * of a single node; in that case we will set $this->graph to null in
	 * #finishJson() to ensure that the deferred callback in #finishDocument()
	 * doesn't later emit "@graph".
	 *
	 * @see https://www.w3.org/TR/json-ld/#named-graphs
	 *
	 * @var array[]|null
	 */
	private $graph = [];

	/**
	 * A collection of predicates about a specific subject.  The
	 * subject is identified by the "@id" key in this array; the other
	 * keys identify JSON-LD properties.
	 *
	 * @see https://www.w3.org/TR/json-ld/#dfn-edge
	 *
	 * @var array
	 */
	private $predicates = [];

	/**
	 * A sequence of zero or more IRIs, nodes, or values, which are the
	 * destination targets of the current predicates.
	 *
	 * @see https://www.w3.org/TR/json-ld/#dfn-list
	 *
	 * @var array
	 */
	private $values = [];

	/**
	 * True iff we have written the opening of the "@graph" field.
	 *
	 * @var bool
	 */
	private $wroteGraph = false;

	/**
	 * JSON-LD objects describing a single node can omit the "@graph" field;
	 * this variable remains false only so long as we can guarantee that
	 * only a single node has been described.
	 *
	 * @var bool
	 */
	private $disableGraphOpt = false;

	/**
	 * The IRI for the RDF `type` property.
	 */
	const RDF_TYPE_IRI = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';

	/**
	 * @param string $role
	 * @param BNodeLabeler|null $labeler
	 */
	public function __construct( $role = parent::DOCUMENT_ROLE, BNodeLabeler $labeler = null ) {
		parent::__construct( $role, $labeler );

		// The following named methods are protected, not private, so we
		// can invoke them directly w/o function wrappers.
		$this->transitionTable[self::STATE_START][self::STATE_DOCUMENT] =
			[ $this, 'beginJson' ];
		$this->transitionTable[self::STATE_DOCUMENT][self::STATE_FINISH] =
			[ $this, 'finishJson' ];
		$this->transitionTable[self::STATE_OBJECT][self::STATE_PREDICATE] =
			[ $this, 'finishPredicate' ];
		$this->transitionTable[self::STATE_OBJECT][self::STATE_SUBJECT] =
			[ $this, 'finishSubject' ];
		$this->transitionTable[self::STATE_OBJECT][self::STATE_DOCUMENT] =
			[ $this, 'finishDocument' ];
	}

	/**
	 * Emit $val as JSON, with $indent extra indentations on each line.
	 * @param array $val
	 * @param int $indent
	 * @return string the JSON string for $val
	 */
	public function encode( $val, $indent ) {
		$str = json_encode( $val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		// Strip outermost open/close braces/brackets
		$str = preg_replace( '/^[[{]\n?|\n?[}\]]$/', '', $str );

		if ( $indent > 0 ) {
			// add extra indentation
			$str = preg_replace( '/^/m', str_repeat( '    ', $indent ), $str );
		}

		return $str;
	}

	/**
	 * Return a "compact IRI" corresponding to the given base/local pair.
	 * This adds entries to the "@context" key when needed to allow use
	 * of a given prefix.
	 * @see https://www.w3.org/TR/json-ld/#dfn-compact-iri
	 *
	 * @param string $base A QName prefix if $local is given, or an IRI if $local is null.
	 * @param string|null $local A QName suffix, or null if $base is an IRI.
	 *
	 * @return string A compact IRI.
	 */
	private function compactify( $base, $local = null ) {
		$this->expandShorthand( $base, $local );

		if ( $local === null ) {
			return $base;
		} else {
			if ( $base !== '_' && isset( $this->prefixes[ $base ] ) ) {
				if ( $base === '' ) {
					// Empty prefix not supported; use full IRI
					return $this->prefixes[ $base ] . $local;
				}
				$this->context[ $base ] = $this->prefixes[ $base ];
			}
			return $base . ':' . $local;
		}
	}

	/**
	 * Return an absolute IRI from the given base/local pair.
	 * @see https://www.w3.org/TR/json-ld/#dfn-absolute-iri
	 *
	 * @param string $base A QName prefix if $local is given, or an IRI if $local is null.
	 * @param string|null $local A QName suffix, or null if $base is an IRI.
	 *
	 * @return string|null An absolute IRI, or null if it cannot be constructed.
	 */
	private function toIRI( $base, $local ) {
		$this->expandShorthand( $base, $local );
		$this->expandQName( $base, $local );
		if ( $local !== null ) {
			throw new LogicException( 'Unknown prefix: ' . $base );
		}
		return $base;
	}

	/**
	 * Write document header.
	 */
	protected function beginJson() {
		if ( $this->role === self::DOCUMENT_ROLE ) {
			$this->write( "{\n" );
			$this->write( function () {
				// If this buffer is drained early, disable @graph optimization
				$this->disableGraphOpt = true;
				return '';
			} );
		}
	}

	/**
	 * Write document footer.
	 */
	protected function finishJson() {
		// If we haven't drained yet, and @graph has only 1 element, then we
		// can optimize our output and hoist the single node to top level.
		if ( $this->role === self::DOCUMENT_ROLE ) {
			if ( ( !$this->disableGraphOpt ) && count( $this->graph ) === 1 ) {
				$this->write( $this->encode( $this->graph[0], 0 ) );
				$this->graph = null; // We're done with @graph.
			} else {
				$this->disableGraphOpt = true;
				$this->write( "\n    ]" );
			}
		}

		if ( count( $this->context ) ) {
			// Write @context field.
			$this->write( ",\n" );
			$this->write( $this->encode( [
				"@context" => $this->context
			], 0 ) );
		}

		$this->write( "\n}" );
	}

	protected function finishDocument() {
		$this->finishSubject();
		$this->write( function () {
			// if this is drained before finishJson(), then disable
			// the graph optimization and dump what we've got so far.
			$str = '';
			if ( $this->graph !== null && count( $this->graph ) > 0 ) {
				$this->disableGraphOpt = true;
				if ( $this->role === self::DOCUMENT_ROLE && !$this->wroteGraph ) {
					$str .= "    \"@graph\": [\n";
					$this->wroteGraph = true;
				} else {
					$str .= ",\n";
				}
				$str .= $this->encode( $this->graph, 1 );
				$this->graph = [];
				return $str;
			}
			// Delay; maybe we'll be able to optimize this later.
			return $str;
		} );
	}

	/**
	 * @param string $base
	 * @param string|null $local
	 */
	protected function writeSubject( $base, $local = null ) {
		$this->predicates = [
			"@id" => $this->compactify( $base, $local )
		];
	}

	protected function finishSubject() {
		$this->finishPredicate();
		$this->graph[] = $this->predicates;
	}

	/**
	 * @param string $base
	 * @param string|null $local
	 */
	protected function writePredicate( $base, $local = null ) {
		// no op
	}

	/**
	 * @param string $base
	 * @param string|null $local
	 */
	protected function writeResource( $base, $local = null ) {
		$this->values[] = [
			"@id" => $this->compactify( $base, $local )
		];
	}

	/**
	 * @param string $text
	 * @param string|null $language
	 */
	protected function writeText( $text, $language = null ) {
		if ( !$this->isValidLanguageCode( $language ) ) {
			$this->values[] = $text;
		} else {
			$this->values[] = [
				"@language" => $language,
				"@value" => $text
			];
		}
	}

	/**
	 * @param string $literal
	 * @param string|null $typeBase
	 * @param string|null $typeLocal
	 */
	public function writeValue( $literal, $typeBase, $typeLocal = null ) {
		if ( $typeBase === null && $typeLocal === null ) {
			$this->values[] = $literal;
			return;
		}

		switch ( $this->toIRI( $typeBase, $typeLocal ) ) {
			case 'http://www.w3.org/2001/XMLSchema#string':
				$this->values[] = strval( $literal );
				return;
			case 'http://www.w3.org/2001/XMLSchema#integer':
				$this->values[] = intval( $literal );
				return;
			case 'http://www.w3.org/2001/XMLSchema#boolean':
				$this->values[] = $literal === 'true';
				return;
			case 'http://www.w3.org/2001/XMLSchema#double':
				$v = doubleval( $literal );
				// Only "numbers with fractions" are xsd:double.  We need
				// to verify that the JSON string will contain a decimal
				// point, otherwise the value would be interpreted as an
				// xsd:integer.
				// TODO: consider instead using JSON_PRESERVE_ZERO_FRACTION
				// in $this->encode() once our required PHP >= 5.6.6.
				if ( strpos( json_encode( $v ), '.' ) !== false ) {
					$this->values[] = $v;
					return;
				}
		}

		$this->values[] = [
			"@type" => $this->compactify( $typeBase, $typeLocal ),
			"@value" => strval( $literal )
		];
	}

	protected function finishPredicate() {
		list( $base, $local ) = $this->currentPredicate;
		$predIRI = $this->toIRI( $base, $local );

		if ( $predIRI === self::RDF_TYPE_IRI ) {
			// TODO: the context can optionally specify other predicates
			// have type "@id" or "@vocab", which would trigger this
			// same coercion.  See https://www.w3.org/TR/json-ld/#iris
			$name = "@type";
			$this->values = array_map( function ( array $val ) {
				return $val[ "@id" ];
			}, $this->values );
		} else {
			$name = $this->compactify( $base, $local );
		}
		if ( isset( $this->predicates[$name] ) ) {
			$was = $this->predicates[$name];
			// Wrap $was into a numeric indexed array if it isn't already.
			// Note that $was could have non-numeric indices, eg
			// [ "@id" => "foo" ], in which was it still needs to be wrapped.
			if ( !( is_array( $was ) && isset( $was[0] ) ) ) {
				$was = [ $was ];
			}
			$this->values = array_merge( $was, $this->values );
		}

		$cnt = count( $this->values );
		if ( $cnt === 0 ) {
			throw new LogicException( "finishPredicate can't be called without at least one value" );
		} elseif ( $cnt === 1 ) {
			$this->predicates[$name] = $this->values[0];
		} else {
			$this->predicates[$name] = $this->values;
		}

		$this->values = [];
	}

	/**
	 * @param string $role
	 * @param BNodeLabeler $labeler
	 *
	 * @return RdfWriterBase
	 */
	protected function newSubWriter( $role, BNodeLabeler $labeler ) {
		$writer = new self( $role, $labeler );

		// Have subwriter share context with this parent.
		$writer->context = &$this->context;
		// We can't use the @graph optimization.
		$this->disableGraphOpt = true;

		return $writer;
	}

	/**
	 * @return string a MIME type
	 */
	public function getMimeType() {
		return 'application/ld+json; charset=UTF-8';
	}

}
