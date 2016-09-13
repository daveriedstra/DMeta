<?php

/**
* Easy way to implement singleton pattern
*
*	shamelessly learning from http://www.phptherightway.com/pages/Design-Patterns.html
*/
class DSingleton {

	private static $_instance; // singleton instance

	// block following methods which obstruct singleton pattern
	protected function __construct() {}
	private function __clone() {}
	private function __wakeup() {}

	/**
	* get singleton instance
	*/
	public static function get_instance() {
		if (null === self::$_instance)
			self::$_instance = new static(); // instantiate once only

		return self::$_instance;
	}
}