<?php
/**
 * Plugin Name:       جستجوی هوشمند علیرضا
 * Plugin URI:        https://github.com/AlirezaKmaxim
 * Description:       یک افزونه جستجوی ایجکس هوشمند، پرسرعت و فازی برای وردپرس و ووکامرس.
 * Version:           2.2.0
 * Author:            Alireza KMaxim
 * Author URI:        https://github.com/AlirezaKmaxim
 * License:           GPL2
 * Text Domain:       alireza-ajax-search
 * Domain Path:       /languages
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Alireza_Search_Core
 * The main plugin class utilizing the Singleton design pattern.
 */
final class Alireza_Search_Core {

	/**
	 * The single instance of the class.
	 *
	 * @var Alireza_Search_Core|null
	 */
	private static $instance = null;

	/**
	 * The asset manager instance.
	 *
	 * @var Alireza_Asset_Manager
	 */
	public $assets;

	/**
	 * The search query instance.
	 *
	 * @var Alireza_Search_Query
	 */
	public $query;

	/**
	 * The AJAX handler instance.
	 *
	 * @var Alireza_AJAX_Handler
	 */
	public $ajax;

	/**
	 * The shortcode instance.
	 *
	 * @var Alireza_Shortcode
	 */
	public $shortcode;

	/**
	 * The admin settings instance.
	 *
	 * @var Alireza_Admin_Settings
	 */
	public $settings;

	/**
	 * Retrieve the single instance of the class.
	 *
	 * @return Alireza_Search_Core
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
		
		$this->assets    = new Alireza_Asset_Manager();
		$this->query     = new Alireza_Search_Query();
		$this->ajax      = new Alireza_AJAX_Handler();
		$this->shortcode = new Alireza_Shortcode();
		
		if ( is_admin() ) {
			$this->settings = new Alireza_Admin_Settings();
			add_filter( 'plugin_action_links_' . plugin_basename( ALIREZA_SEARCH_FILE ), array( $this, 'add_plugin_action_links' ) );
		}

		$this->init_hooks();
	}

	/**
	 * Define plugin constants.
	 */
	private function define_constants() {
		if ( ! defined( 'ALIREZA_SEARCH_VERSION' ) ) {
			define( 'ALIREZA_SEARCH_VERSION', '2.2.0' );
		}
		if ( ! defined( 'ALIREZA_SEARCH_FILE' ) ) {
			define( 'ALIREZA_SEARCH_FILE', __FILE__ );
		}
		if ( ! defined( 'ALIREZA_SEARCH_PATH' ) ) {
			define( 'ALIREZA_SEARCH_PATH', plugin_dir_path( __FILE__ ) );
		}
		if ( ! defined( 'ALIREZA_SEARCH_URL' ) ) {
			define( 'ALIREZA_SEARCH_URL', plugin_dir_url( __FILE__ ) );
		}
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once ALIREZA_SEARCH_PATH . 'includes/class-asset-manager.php';
		require_once ALIREZA_SEARCH_PATH . 'includes/class-search-query.php';
		require_once ALIREZA_SEARCH_PATH . 'includes/class-ajax-handler.php';
		require_once ALIREZA_SEARCH_PATH . 'includes/class-shortcode.php';
		require_once ALIREZA_SEARCH_PATH . 'includes/alireza-icons.php';
		
		if ( is_admin() ) {
			require_once ALIREZA_SEARCH_PATH . 'includes/class-admin-settings.php';
		}
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
	}

	/**
	 * Fired when plugins are loaded.
	 */
	public function on_plugins_loaded() {
		// Ready for initialization.
	}

	/**
	 * Add Settings link on the WordPress Plugins screen.
	 *
	 * @param array $links
	 * @return array
	 */
	public function add_plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s" style="font-weight: bold; color: #FA7993;">%s</a>',
			admin_url( 'admin.php?page=alireza-search-settings' ),
			__( 'تنظیمات', 'alireza-ajax-search' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Cloning and unshelving are forbidden for Singleton.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'alireza-ajax-search' ), '1.0.0' );
	}

	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'alireza-ajax-search' ), '1.0.0' );
	}

	/**
	 * Check if WooCommerce is active on the current site.
	 *
	 * @return bool
	 */
	public function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Retrieve plugin settings, with backward-compatible fallback to the
	 * legacy "hamseda_search_settings" option name.
	 *
	 * Cached for the lifetime of the request (get_option() already caches
	 * the individual option rows, but the fallback branching logic itself
	 * was being re-evaluated on every call across several classes).
	 *
	 * @return array
	 */
	public function get_settings() {
		static $cached_settings = null;

		if ( null !== $cached_settings ) {
			return $cached_settings;
		}

		$options = get_option( 'alireza_search_settings' );
		if ( false === $options || empty( $options ) ) {
			$options = get_option( 'hamseda_search_settings', array() );
		}

		$cached_settings = ! empty( $options ) && is_array( $options ) ? $options : array();

		return $cached_settings;
	}
}

/**
 * Helper function to retrieve the main instance.
 *
 * @return Alireza_Search_Core
 */
function alireza_search() {
	return Alireza_Search_Core::instance();
}

// Backward compatibility helper
if ( ! function_exists( 'hamseda_search' ) ) {
	function hamseda_search() {
		return alireza_search();
	}
}

// Instantiate the plugin.
alireza_search();
