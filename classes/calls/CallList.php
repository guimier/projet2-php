<?php

class CallList implements Iterator {

	/** The real list
	 * @type array
	 */
	private $calls = array();

	/** Add a call to the list
	 * @param Call $call
	 */
	public function add( Call $call ) {
		$this->calls[] = $call;
	}

/*----- Iterator -----*/
	
	/** Iteration index
	 * @type int
	 */
	private $i;

	/** Return the current call. */
	public function current() {
		return $this->calls[$this->i];
	}

	/** Return the key of the current call. */
	public function key() {
		return $this->i;
	}

	/** Move forward to next call. */
	public function next() {
		++ $this->i;
	}

	/** Rewind the Iterator to the first call. */
	public function rewind() {
		$this->i = 0;
	}

	/** Checks if current position is valid. */
	public function valid() {
		return $this->i < count( $this->calls );
	}

}
