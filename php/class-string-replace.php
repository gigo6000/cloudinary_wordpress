<?php
/**
 * Cloudinary string replace class, to replace URLS and other strings on shutdown.
 *
 * @package Cloudinary
 */

namespace Cloudinary;

/**
 * String replace class.
 */
class String_Replace {

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the list of strings and replacements.
	 *
	 * @var array
	 */
	protected static $replacements = array();

	/**
	 * Site Cache constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		$this->init();
	}

	/**
	 * Init the buffer capture and set the output callback.
	 */
	public function init() {
		ob_start( array( $this, 'replace_strings' ) );
	}

	/**
	 * Replace a string.
	 *
	 * @param string $search  The string to be replaced.
	 * @param string $replace The string replacement.
	 */
	public static function replace( $search, $replace ) {
		self::$replacements[ $search ] = $replace;
	}

	/**
	 * Replace string in HTML.
	 *
	 * @param string $html The HTML.
	 *
	 * @return string
	 */
	public function replace_strings( $html ) {

		/**
		 * Do replacement action.
		 *
		 * @hook    cloudinary_string_replace
		 *
		 * @param $html {string} The html of the page.
		 */
		do_action( 'cloudinary_string_replace', $html );

		return str_replace( array_keys( self::$replacements ), array_values( self::$replacements ), $html );
	}
}
