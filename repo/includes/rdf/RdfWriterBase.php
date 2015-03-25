<?php

namespace Wikibase\RDF;

use Closure;
use InvalidArgumentException;
use LogicException;

/**
 * Base class for RdfWriter implementations.
 *
 * Subclasses have to implement at least the writeXXX() methods to generate the desired output
 * for the respective RDF constructs. Subclasses may override the startXXX() and finishXXX()
 * methods to generate structural output, and override expandXXX() to transform identifiers.
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
abstract class RdfWriterBase implements RdfWriter {

	/**
	 * @var array An array of strings or RdfWriters.
	 */
	private $buffer = array();

	const STATE_START = 0;
	const STATE_DOCUMENT = 5;
	const STATE_SUBJECT = 10;
	const STATE_PREDICATE = 11;
	const STATE_OBJECT = 12;
	const STATE_DRAIN = 100;

	/**
	 * @var string the current state
	 */
	private $state = self::STATE_START;

	/**
	 * Shorthands that can be used in place of IRIs, e.g. ("a" to mean rdf:type).
	 *
	 * @var string[] a map of shorthand names to array( $base, $local ) pairs.
	 * @todo handle "a" as a special case directly. Use for custom "variables" like %currentValue instead.
	 */
	private $shorthands = array();

	/**
	 * @var string[] a map of prefixes to base IRIs
	 */
	private $prefixes = array();

	/**
	 * @var array pair to store the current subject.
	 * Holds the $base and $local parameters passed to about().
	 */
	protected $currentSubject = array( null, null );

	/**
	 * @var array pair to store the current predicate.
	 * Holds the $base and $local parameters passed to say().
	 */
	protected $currentPredicate = array( null, null );

	/**
	 * @var BNodeLabeler
	 */
	private $labeler;

	/**
	 * Role ID for writers that will generate a full RDF document.
	 */
	const DOCUMENT_ROLE = 'document';

	/**
	 * Role ID for writers that will generate a single inline blank node.
	 */
	const BNODE_ROLE = 'bnode';

	/**
	 * Role ID for writers that will generate a single inline RDR statement.
	 */
	const STATEMENT_ROLE = 'statement';

	/**
	 * @var string The writer's role, see the XXX_ROLE constants.
	 */
	private $role;

	/**
	 * @param string $role The writer's role, use the XXX_ROLE constants.
	 * @param BNodeLabeler $labeler
	 *
	 * @throws InvalidArgumentException
	 */
	function __construct( $role, BNodeLabeler $labeler = null ) {
		if ( !is_string( $role ) ) {
			throw new InvalidArgumentException( '$role must be a string' );
		}

		$this->role = $role;

		$this->labeler = $labeler?: new BNodeLabeler();

		$this->registerShorthand( 'a', 'rdf', 'type' );

		$this->registerPrefix( 'rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#' );
		$this->registerPrefix( 'xsd', 'http://www.w3.org/2001/XMLSchema#' );
	}

	/**
	 * @param string $role
	 * @param BNodeLabeler $labeler
	 *
	 * @return RdfWriterBase
	 */
	abstract protected function newSubWriter( $role, BNodeLabeler $labeler );

	/**
	 * Registers a shorthand that can be used instead of a qname,
	 * like 'a' can be used instead of 'rdf:type'.
	 *
	 * @param string $shorthand
	 * @param string $prefix
	 * @param string $local
	 */
	protected function registerShorthand( $shorthand, $prefix, $local ) {
		$this->shorthands[$shorthand] = array( $prefix, $local );
	}

	/**
	 * Registers a prefix
	 *
	 * @param string $prefix
	 * @param string $iri The base IRI
	 */
	protected function registerPrefix( $prefix, $iri ) {
		$this->prefixes[$prefix] = $iri;
	}

	/**
	 * Determines whether $shorthand can be used as a shorthand.
	 *
	 * @param string $shorthand
	 *
	 * @return bool
	 */
	protected function isShorthand( $shorthand ) {
		return isset( $this->shorthands[$shorthand] );
	}

	/**
	 * Determines whether $shorthand can legally be used as a prefix.
	 *
	 * @param string $prefix
	 *
	 * @return bool
	 */
	protected function isPrefix( $prefix ) {
		return isset( $this->prefixes[$prefix] );
	}

	/**
	 * Returns the prefix map.
	 *
	 * @return string[] An associative array mapping prefixes to base IRIs.
	 */
	public function getPrefixes() {
		return $this->prefixes;
	}

	/**
	 * @return RdfWriter
	 */
	final public function sub() {
		//FIXME: don't mess with the state, enqueue the writer to be placed in the buffer
		// later, on the next transtion to subject|document|drain
		$this->state( self::STATE_DOCUMENT );

		$writer = $this->newSubWriter( self::DOCUMENT_ROLE, $this->labeler );
		$writer->state = self::STATE_DOCUMENT;

		// share registered prefixes
		$writer->prefixes =& $this->prefixes;

		$this->write( $writer );
		return $writer;
	}

	/**
	 * Returns the writers role. The role determines the behavior of the writer with respect
	 * to which states and transitions are possible: a BNODE_ROLE writer would for instance
	 * not accept a call to about(), since it can only process triples about a single subject
	 * (the blank node it represents).
	 *
	 * @return string A string corresponding to one of the the XXX_ROLE constants.
	 */
	final public function getRole() {
		return $this->role;
	}

	/**
	 * Appends any parameters to the output buffer.
	 *
	 * @param string [$text,...]
	 */
	final protected function write() {
		foreach ( func_get_args() as $arg ) {
			$this->buffer[] = $arg;
		}
	}

	/**
	 * If $base is a shorthand, $base and $local are updated to hold whatever qname
	 * the shorthand was associated with.
	 *
	 * Otherwise, $base and $local remain unchanged.
	 *
	 * @param string &$base
	 * @param string|null &$local
	 */
	protected function expandShorthand( &$base, &$local ) {
		if ( $local === null && isset( $this->shorthands[$base] ) ) {
			list( $base, $local ) = $this->shorthands[$base];
		}
	}

	/**
	 * If $base is a registered prefix, $base will be replaced by the base IRI associated with
	 * that prefix, with $local appended. $local will be set to null.
	 *
	 * Otherwise, $base and $local remain unchanged.
	 *
	 * @param string &$base
	 * @param string|null &$local
	 *
	 * @throws LogicException
	 */
	protected function expandQName( &$base, &$local ) {
		if ( $local !== null && $base !== '_' ) {
			if ( isset( $this->prefixes[$base] ) ) {
				$base = $this->prefixes[$base] . $local; //XXX: can we avoid this concat?
				$local = null;
			} else {
				throw new LogicException( 'Unknown prefix: ' . $base );
			}
		}
	}

	/**
	 * @see RdfWriter::blank()
	 *
	 * @param string|null $label node label, will be generated if not given.
	 *
	 * @return string
	 */
	final public function blank( $label = null ) {
		return $this->labeler->getLabel( $label );
	}

	/**
	 * @see RdfWriter::start()
	 */
	final public function start() {
		$this->state( self::STATE_DOCUMENT );
	}

	/**
	 * @see RdfWriter::drain()
	 *
	 * @return string RDF
	 */
	final public function drain() {
		$this->state( 'drain' );

		$this->flattenBuffer();

		$rdf = join( '', $this->buffer );
		$this->buffer = array();

		return $rdf;
	}

	/**
	 * @see RdfWriter::reset()
	 *
	 * @note Does not reset the blank node counter, because it may be shared.
	 */
	public function reset() {
		$this->buffer = array();
		$this->state = self::STATE_START; //TODO: may depend on role

		$this->currentSubject = array( null, null );
		$this->currentPredicate = array( null, null );

		$this->prefixes = array();
		$this->registerPrefix( 'rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#' );
		$this->registerPrefix( 'xsd', 'http://www.w3.org/2001/XMLSchema#' );
	}

	/**
	 * Calls drain() an any RdfWriter instances in $this->buffer, and replaces them
	 * in $this->buffer with the string returned by the drain() call. Any closures
	 * present in the $this->buffer will be called, and replaced by their return value.
	 */
	private function flattenBuffer() {
		foreach ( $this->buffer as &$b ) {
			if ( $b instanceof Closure ) {
				$b = $b();
			}
			if ( $b instanceof RdfWriter ) {
				$b = $b->drain();
			}
		}
	}

	/**
	 * @see RdfWriter::prefix()
	 *
	 * @param string $prefix
	 * @param string $uri
	 */
	final public function prefix( $prefix, $uri ) {
		$this->state( self::STATE_DOCUMENT );

		$this->registerPrefix( $prefix, $uri );
		$this->writePrefix( $prefix, $uri );
	}

	/**
	 * @see RdfWriter::about()
	 *
	 * @param string $base A QName prefix if $local is given, or an IRI if $local is null.
	 * @param string|null $local A QName suffix, or null if $base is an IRI.
	 *
	 * @return RdfWriter $this
	 */
	final public function about( $base, $local = null ) {
		$this->expandSubject( $base, $local );

		if ( $base === $this->currentSubject[0] && $local === $this->currentSubject[1] ) {
			return $this; // redundant about() call
		}

		$this->state( self::STATE_SUBJECT );

		$this->currentSubject[0] = $base;
		$this->currentSubject[1] = $local;
		$this->currentPredicate[0] = null;
		$this->currentPredicate[1] = null;

		$this->writeSubject( $base, $local );
		return $this;
	}

	/**
	 * @see RdfWriter::a()
	 * Shorthand for say( 'a' )->is( $type ).
	 *
	 * @param string $typeBase The data type's QName prefix if $typeLocal is given,
	 *        or an IRI or shorthand if $typeLocal is null.
	 * @param string|null $typeLocal The data type's  QName suffix,
	 *        or null if $typeBase is an IRI or shorthand.
	 *
	 * @return RdfWriter $this
	 */
	final public function a( $typeBase, $typeLocal = null ) {
		return $this->say( 'a' )->is( $typeBase, $typeLocal );
	}

	/**
	 * @see RdfWriter::say()
	 *
	 * @param string $base A QName prefix.
	 * @param string $local A QName suffix.
	 *
	 * @return RdfWriter $this
	 */
	final public function say( $base, $local = null ) {
		$this->expandPredicate( $base, $local );

		if ( $base === $this->currentPredicate[0] && $local === $this->currentPredicate[1] ) {
			return $this; // redundant about() call
		}

		$this->state( self::STATE_PREDICATE );

		$this->currentPredicate[0] = $base;
		$this->currentPredicate[1] = $local;

		$this->writePredicate( $base, $local );
		return $this;
	}

	/**
	 * @see RdfWriter::is()
	 *
	 * @param string $base A QName prefix if $local is given, or an IRI if $local is null.
	 * @param string|null $local A QName suffix, or null if $base is an IRI.
	 *
	 * @return RdfWriter $this
	 */
	final public function is( $base, $local = null ) {
		$this->state( self::STATE_OBJECT );

		$this->expandResource( $base, $local );
		$this->writeResource( $base, $local );
		return $this;
	}

	/**
	 * @see RdfWriter::text()
	 *
	 * @param string $text the text to be placed in the output
	 * @param string|null $language the language the text is in
	 *
	 * @return $this
	 */
	final public function text( $text, $language = null ) {
		$this->state( self::STATE_OBJECT );

		$this->writeText( $text, $language );
		return $this;
	}

	/**
	 * @see RdfWriter::value()
	 *
	 * @param string $value the value encoded as a string
	 * @param string $typeBase The data type's QName prefix if $typeLocal is given,
	 *        or an IRI or shorthand if $typeLocal is null.
	 * @param string|null $typeLocal The data type's  QName suffix,
	 *        or null if $typeBase is an IRI or shorthand.
	 *
	 * @return $this
	 */
	final public function value( $value, $typeBase = null, $typeLocal = null ) {
		$this->state( self::STATE_OBJECT );

		if ( $typeBase === null && !is_string( $value ) ) {
			$vtype = gettype( $value );
			switch ( $vtype ) {
				case 'integer':
					$typeBase = 'xsd';
					$typeLocal = 'integer';
					$value = "$value";
					break;

				case 'double':
					$typeBase = 'xsd';
					$typeLocal = 'double';
					$value = "$value";
					break;

				case 'boolean':
					$typeBase = 'xsd';
					$typeLocal = 'boolean';
					$value = $value ? 'true' : 'false';
					break;
			}
		}

		$this->expandType( $typeBase, $typeLocal );

		$this->writeValue( $value, $typeBase, $typeLocal );
		return $this;
	}

	/**
	 * Perform a state transition. Writer states roughly correspond to states in a naive
	 * regular parser for the respective syntax. State transitions may generate output,
	 * particularly of structural elements which correspond to terminals in a respective
	 * parser.
	 *
	 * @param $newState
	 *
	 * @throws InvalidArgumentException
	 */
	final protected function state( $newState ) {
		switch ( $newState ) {
			case self::STATE_DOCUMENT:
				$this->transitionDocument();
				break;

			case self::STATE_SUBJECT:
				$this->transitionSubject();
				break;

			case self::STATE_PREDICATE:
				$this->transitionPredicate();
				break;

			case self::STATE_OBJECT:
				$this->transitionObject();
				break;

			case 'drain':
				$this->transitionDrain();
				break;

			default:
				throw new InvalidArgumentException( 'invalid $newState: ' . $newState );
		}

		$this->state = $newState;
	}

	private function transitionDocument() {
		switch ( $this->state ) {
			case self::STATE_DOCUMENT:
				break;

			case self::STATE_START:
				$this->beginDocument();
				break;

			case self::STATE_OBJECT: // when injecting a sub-document
				$this->finishObject( 'last' );
				$this->finishPredicate( 'last' );
				$this->finishSubject();
				break;

			default:
				throw new LogicException( 'Bad transition: ' . $this->state. ' -> ' . self::STATE_DOCUMENT );
		}
	}

	private function transitionSubject() {
		switch ( $this->state ) {
			case self::STATE_DOCUMENT:
				$this->beginSubject();
				break;

			case self::STATE_OBJECT:
				if ( $this->role !== self::DOCUMENT_ROLE ) {
					throw new LogicException( 'Bad transition: ' . $this->state. ' -> ' . self::STATE_SUBJECT );
				}

				$this->finishObject( 'last' );
				$this->finishPredicate( 'last' );
				$this->finishSubject();
				$this->beginSubject();
				break;

			default:
				throw new LogicException( 'Bad transition: ' . $this->state. ' -> ' . self::STATE_SUBJECT );
		}
	}

	private function transitionPredicate() {
		switch ( $this->state ) {
			case self::STATE_SUBJECT:
				$this->beginPredicate( 'first' );
				break;

			case self::STATE_OBJECT:
				if ( $this->role === self::STATEMENT_ROLE ) {
					throw new LogicException( 'Bad transition: ' . $this->state. ' -> ' . self::STATE_PREDICATE );
				}

				$this->finishObject( 'last' );
				$this->finishPredicate();
				$this->beginPredicate();
				break;

			default:
				throw new LogicException( 'Bad transition: ' . $this->state. ' -> ' . self::STATE_PREDICATE );

		}
	}

	private function transitionObject() {
		switch ( $this->state ) {
			case self::STATE_PREDICATE:
				$this->beginObject( 'first' );
				break;

			case self::STATE_OBJECT:
				$this->finishObject();
				$this->beginObject();
				break;

			default:
				throw new LogicException( 'Bad transition: ' . $this->state. ' -> ' . self::STATE_OBJECT );

		}
	}

	private function transitionDrain() {
		switch ( $this->state ) {
			case self::STATE_START:
				break;

			case self::STATE_DOCUMENT:
				$this->finishDocument();
				break;

			case self::STATE_OBJECT:

				$this->finishObject( 'last' );
				$this->finishPredicate( 'last' );
				$this->finishSubject();
				$this->finishDocument();
				break;

			default:
				throw new LogicException( 'Bad transition: ' . $this->state. ' -> ' . self::STATE_OBJECT );

		}
	}

	/**
	 * Must be implemented to generate output for a prefix declaration.
	 * If the output format does not support or require such declarations (like NTriples doesn't),
	 * the implementation can be empty.
	 *
	 * @param $prefix
	 * @param $uri
	 */
	protected abstract function writePrefix( $prefix, $uri );

	/**
	 * Must be implemented to generate output that starts a statement (or set of statements)
	 * about a subject. Depending on the requirements of the output format, the implementation
	 * may be empty.
	 *
	 * @note: $base and $local are given as passed to about() and processed by expandSubject().
	 *
	 * @param string $base
	 * @param string|null
	 */
	protected abstract function writeSubject( $base, $local = null );

	/**
	 * Must be implemented to generate output that represents the association of a predicate
	 * with a subject that was previously defined by a call to writeSubject().
	 *
	 * @note: $base and $local are given as passed to say() and processed by expandPredicate().
	 *
	 * @param string $base
	 * @param string|null
	 */
	protected abstract function writePredicate( $base, $local = null );

	/**
	 * Must be implemented to generate output that represents a resource used as the object
	 * of a statement.
	 *
	 * @note: $base and $local are given as passed to is() and processed by expandObject().
	 *
	 * @param string $base
	 * @param string|null
	 */
	protected abstract function writeResource( $base, $local = null );

	/**
	 * Must be implemented to generate output that represents a text used as the object
	 * of a statement.
	 *
	 * @param string $text the text to be placed in the output
	 * @param string|null $language the language the text is in
	 */
	protected abstract function writeText( $text, $language );

	/**
	 * Must be implemented to generate output that represents a (typed) literal used as the object
	 * of a statement.
	 *
	 * @note: $typeBase and $typeLocal are given as passed to value() and processed by expandType().
	 *
	 * @param string $value the value encoded as a string
	 * @param string $typeBase
	 * @param string|null $typeLocal
	 */
	protected abstract function writeValue( $value, $typeBase, $typeLocal = null );

	/**
	 * May be implemented to generate any output that may be needed at the beginning of a
	 * document.
	 */
	protected function beginDocument() {
	}

	/**
	 * May be implemented to generate any output that may be needed at the end of a
	 * document (e.g. this would generate "</rdf:RDF>" for RDF/XML).
	 */
	protected function finishDocument() {
	}

	/**
	 * May be implemented to generate any output that may be needed at the beginning of a
	 * sequence of statements about a subject.
	 *
	 * @param bool $first Whether this is the first statement in the document.
	 */
	protected function beginSubject( $first = false ) {
	}

	/**
	 * May be implemented to generate any output that may be needed at the end of a
	 * sequence of statements about a single subject (e.g. this would generate "." for Turtle).
	 */
	protected function finishSubject() {
	}

	/**
	 * May be implemented to generate any output that may be needed before the
	 * predicate of a statement.
	 *
	 * @param bool $first Whether this is the first predicate in a sequence of
	 *        statements about a single subject.
	 */
	protected function beginPredicate( $first = false ) {
	}

	/**
	 * May be implemented to generate any output that may be needed after the sequence of
	 * objects associated with the predicate, before starting the next predicate or subject,
	 * or ending the document (e.g. this would generate ";" for Turtle, unless $last = true).
	 *
	 * @param bool $last Whether this is the end of the last predicate in the statement sequence
	 *        about a given subject.
	 */
	protected function finishPredicate( $last = false ) {
	}

	/**
	 * May be implemented to generate any output that may be needed before an object.
	 *
	 * @param bool $first Whether this is the first object in a sequence of objects
	 *        associated with a single predicate.
	 */
	protected function beginObject( $first = false ) {
	}

	/**
	 * May be implemented to generate any output that may be needed after an object (e.g.
	 * this would generate "," for Turtle, unless $last = true).
	 *
	 * @param bool $last Whether this is the last object in the sequence.
	 */
	protected function finishObject( $last = false ) {
	}

	/**
	 * Perform any expansion (shorthand to qname, qname to IRI) desired
	 * for subject identifiers.
	 *
	 * @param string &$base
	 * @param string|null &$local
	 */
	protected function expandSubject( &$base, &$local ) {
	}

	/**
	 * Perform any expansion (shorthand to qname, qname to IRI) desired
	 * for predicate identifiers.
	 *
	 * @param string &$base
	 * @param string|null &$local
	 */
	protected function expandPredicate( &$base, &$local ) {
	}

	/**
	 * Perform any expansion (shorthand to qname, qname to IRI) desired
	 * for resource identifiers.
	 *
	 * @param string &$base
	 * @param string|null &$local
	 */
	protected function expandResource( &$base, &$local ) {
	}

	/**
	 * Perform any expansion (shorthand to qname, qname to IRI) desired
	 * for type identifiers.
	 *
	 * @param string &$base
	 * @param string|null &$local
	 */
	protected function expandType( &$base, &$local ) {
	}

}
