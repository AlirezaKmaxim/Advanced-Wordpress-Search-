<?php
/**
 * HamSeda Icons Manager
 * Handles SVG icons to be loaded inline in the DOM.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HamSeda_Icons {

	/**
	 * Output the inline SVG spritesheet.
	 */
	public static function render_spritesheet() {
		?>
		<svg xmlns="http://www.w3.org/2000/svg" style="display: none;">
			<defs>
				<!-- Shopping Cart Icon (Products) -->
				<symbol id="icon-cart" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
				</symbol>
				
				<!-- Folder/Tag Icon (Categories) -->
				<symbol id="icon-folder" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
				</symbol>
			</defs>
		</svg>
		<?php
	}
}
