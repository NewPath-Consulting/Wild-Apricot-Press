<?php
namespace WAWP;

class WAWPApi {
	// Unique instance of class
	private static $instance = null;

	/**
     * Returns the instance of this class (singleton)
     * If the instance does not exist, creates one.
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
