<?php
/**
 * Plugin Name:       HamSeda Ajax Search
 * Plugin URI:        https://hamseda.com
 * Description:       A premium, high-performance, fuzzy AJAX search plugin for WordPress.
 * Version:           2.0.9
 * Author:            Alireza KMaxim
 * Author URI:        https://github.com/AlirezaKmaxim
 * License:           GPL2
 * Text Domain:       hamseda-ajax-search
 * Domain Path:       /languages
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HamSeda_Search_Core
 * The main plugin class utilizing the Singleton design pattern.
 */
final class HamSeda_Search_Core {

	/**
	 * The single instance of the class.
	 *
	 * @var HamSeda_Search_Core|null
	 */
	private static $instance = null;

	/**
	 * The asset manager instance.
	 *
	 * @var HamSeda_Asset_Manager
	 */
	public $assets;

	/**
	 * The search query instance.
	 *
	 * @var HamSeda_Search_Query
	 */
	public $query;

	/**
	 * The AJAX handler instance.
	 *
	 * @var HamSeda_AJAX_Handler
	 */
	public $ajax;

	/**
	 * The shortcode instance.
	 *
	 * @var HamSeda_Shortcode
	 */
	public $shortcode;

	/**
	 * The admin settings instance.
	 *
	 * @var HamSeda_Admin_Settings
	 */
	public $settings;

	/**
	 * Retrieve the single instance of the class.
	 *
	 * @return HamSeda_Search_Core
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->define_constants();
		$this->includes();
		$this->assets    = new HamSeda_Asset_Manager();
		$this->query     = new HamSeda_Search_Query();
		$this->ajax      = new HamSeda_AJAX_Handler();
		$this->shortcode = new HamSeda_Shortcode();
		
		if ( is_admin() ) {
			$this->settings = new HamSeda_Admin_Settings();
		}

		$this->init_hooks();
	}

	/**
	 * Define plugin constants.
	 */
	private function define_constants() {
		define( 'HAMSEDA_SEARCH_VERSION', '2.0.9' );
		define( 'HAMSEDA_SEARCH_FILE', __FILE__ );
		define( 'HAMSEDA_SEARCH_PATH', plugin_dir_path( __FILE__ ) );
		define( 'HAMSEDA_SEARCH_URL', plugin_dir_url( __FILE__ ) );
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once HAMSEDA_SEARCH_PATH . 'includes/class-asset-manager.php';
		require_once HAMSEDA_SEARCH_PATH . 'includes/class-search-query.php';
		require_once HAMSEDA_SEARCH_PATH . 'includes/class-ajax-handler.php';
		require_once HAMSEDA_SEARCH_PATH . 'includes/class-shortcode.php';
		require_once HAMSEDA_SEARCH_PATH . 'includes/hamseda-icons.php';
		
		if ( is_admin() ) {
			require_once HAMSEDA_SEARCH_PATH . 'includes/class-admin-settings.php';
		}
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Hook into WordPress actions and filters.
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
	}

	/**
	 * Fired when plugins are loaded.
	 */
	public function on_plugins_loaded() {
		// Ready for initialization.
	}

	/**
	 * Cloning and unshelving are forbidden for Singleton.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'hamseda-ajax-search' ), '1.0.0' );
	}

	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'hamseda-ajax-search' ), '1.0.0' );
	}

	/**
	 * Check if WooCommerce is active on the current site.
	 *
	 * @return bool
	 */
	public function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}
}

/**
 * Helper function to retrieve the main instance.
 *
 * @return HamSeda_Search_Core
 */
function hamseda_search() {
	return HamSeda_Search_Core::instance();
}

// Instantiate the plugin.
hamseda_search();
