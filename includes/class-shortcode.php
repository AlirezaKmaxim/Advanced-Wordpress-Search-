<?php
/**
 * Class HamSeda_Shortcode
 * Registers and handles the [hamseda_ajax_search] shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HamSeda_Shortcode {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register the shortcode
		add_shortcode( 'hamseda_ajax_search', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render the search widget shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Output HTML.
	 */
	public function render_shortcode( $atts ) {
		// Enqueue the necessary CSS and JS assets when shortcode is active
		hamseda_search()->assets->enqueue_assets();

		// Start output buffering
		ob_start();

		// Include the search template file
		$template_path = HAMSEDA_SEARCH_PATH . 'templates/search-template.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo '<!-- HamSeda Ajax Search: Template file not found -->';
		}

		return ob_get_clean();
	}
}
