<?php
/**
 * Class Alireza_Icons
 * Provides inline SVG sprite definitions for UI icons.
 *
 * @package Alireza_Ajax_Search
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alireza_Icons {

	/**
	 * Render the SVG spritesheet.
	 *
	 * @return void
	 */
	public static function render_spritesheet() {
		?>
		<svg xmlns="http://www.w3.org/2000/svg" style="display: none;">
			<!-- Shopping Cart Icon -->
			<symbol id="icon-cart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<circle cx="9" cy="21" r="1"></circle>
				<circle cx="20" cy="21" r="1"></circle>
				<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
			</symbol>
			<!-- Folder / Category Icon -->
			<symbol id="icon-folder" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-6l-2-2H5a2 2 0 0 0-2 2z"></path>
			</symbol>
		</svg>
		<?php
	}
}
