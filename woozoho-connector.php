<?php

/** WordPress Plugin Details
 * Plugin Name: Connector for WooCommerce & Zoho
 * Plugin URI: https://digispark.nl/lab/connector-woocommerce-zoho/
 * Description: A feature rich connector that binds & synchronizes WooCommerce to Zoho.
 * Version: 0.3
 * Author: DigiSpark
 * Author URI: https://digispark.nl/
 * Requires at least: 4.4
 * Tested up to: 4.8
 *
 * Text Domain: woozoho-connector
 * Domain Path: /i18n/languages/
 *
 * @package woozoho-connector
 * @category Core
 * @author DigiSpark
 *
 * The rewrite, structure and coding style of this plugin is inspired by WooCommerce, no literal code is taken.
 */

// Safety first
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class Woozoho_Connector {

	protected static $_instance = null;
	public $version = "0.4.1";
	/**
	 * @var Woozoho_Connector_Zoho_Client
	 */
	public $client;
	public $logger;
	/**
	 * @var Woozoho_Connector_Cronjobs
	 */
	public $cron_jobs;
	/**
	 * @var Woozoho_Connector_Loader
	 */
	protected $loader;

	public function __construct() {
		require_once dirname( __FILE__ ) . '/includes/class-woozoho-connector-logger.php';
		if ( ! $this->check_dependencies() ) {
			Woozoho_Connector_Logger::writeDebug( "Plugin", "Dependencies not met!" );
			wp_die( "WooZoho connector needs WooCommerce installed." );

			return;
		}

		$this->define_constants();
		$this->load_dependencies();
		$this->init();
	}

	public function check_dependencies() {
		return true;
		//in_array( 'woocommerce/woocommerce.php', (array) get_option( 'active_plugins', array() ) );
	}

	private function define_constants() {
		$upload_dir = wp_upload_dir();

		$this->define( 'WOOZOHO_VERSION', $this->version );
		$this->define( 'WOOZOHO_LOG_DIR', $upload_dir['basedir'] . '/woozoho-logs/' );
		$this->define( 'WOOZOHO_CACHE_DIR', $upload_dir['basedir'] . '/woozoho-cache/' );
		$this->define( 'WOOZOHO_ABSPATH', dirname( __FILE__ ) . '/' );
	}

	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * @noinspection PhpIncludeInspection,PhpUndefinedConstantInspection
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		/** @noinspection PhpIncludeInspection */
		require_once WOOZOHO_ABSPATH . 'includes/class-woozoho-connector-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */

		//ZohoConnector Core Functionality

		require_once WOOZOHO_ABSPATH . 'includes/class-woozoho-connector-zoho-cache.php';
		require_once WOOZOHO_ABSPATH . 'includes/class-woozoho-connector-zoho-api.php';
		require_once WOOZOHO_ABSPATH . 'includes/class-woozoho-connector-orders-queue.php';
		require_once WOOZOHO_ABSPATH . 'includes/class-woozoho-connector-zoho-client.php';
		require_once WOOZOHO_ABSPATH . 'includes/class-woozoho-connector-cronjobs.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once WOOZOHO_ABSPATH . 'admin/class-woozoho-connector-admin.php';

	}

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function init() {
		$this->load_plugin_textdomain();

		$this->loader    = new Woozoho_Connector_Loader();
		$this->logger    = new Woozoho_Connector_Logger();
		$this->client    = new Woozoho_Connector_Zoho_Client();
		$this->cron_jobs = new Woozoho_Connector_Cronjobs();

		$this->plugin_hooks();
		$this->int_cron_jobs();

		$this->run();
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function plugin_hooks() {

		register_activation_hook( __FILE__, array( 'Woozoho_Connector_Activator', 'activate' ) );
		register_deactivation_hook( __FILE__, array( 'Woozoho_Connector_Activator', 'deactivate' ) );

		$plugin_admin = new Woozoho_Connector_Admin();

		//Initialize only after WooCommerce has loaded?
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		//WooCommerce Tab Settings Integration
		$this->loader->add_filter( 'woocommerce_settings_tabs_array', $plugin_admin, 'woocommerce_add_settings_tab' );
		$this->loader->add_action( 'woocommerce_settings_tabs_zoho_connector', $plugin_admin, 'woocommerce_settings_tab' );
		$this->loader->add_action( 'woocommerce_update_options_zoho_connector', $plugin_admin, 'woocommerce_update_settings' );

		$plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . 'woozoho-connector.php' );
		$this->loader->add_filter( 'plugin_action_links_' . $plugin_basename, $plugin_admin, 'add_action_links' );

		//WooCommerce Bulk Functionality
		$this->loader->add_filter( 'bulk_actions-edit-shop_order', $plugin_admin, 'woocommerce_add_bulk_actions' );
		$this->loader->add_filter( 'handle_bulk_actions-edit-shop_order', $plugin_admin, 'woocommerce_bulk_action_send_zoho', 10, 3 );

		//WooCommerce Prepare New Order for Push to Zoho
		$this->loader->add_action( 'woocommerce_new_order', $plugin_admin, 'scheduleOrder', 20, 1 );

		//Notices
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'woocommerce_zoho_connector_admin_notices' );
		$this->loader->add_action( 'woozoho_push_order_queue', $plugin_admin, 'pushOrder', 10, 1 );
	}

	private function int_cron_jobs() {
		$isEnabled = Woozoho_Connector::get_option( "cron_orders_enabled" );
		if ( $isEnabled ) {
			if ( ! $this->cron_jobs->isOrdersJobRunning() ) {
				$this->cron_jobs->setupOrdersJob();
			}
			$this->loader->add_action( 'woozoho_orders_job', $this->cron_jobs, 'runOrdersJob' );
		} else if ( ! $isEnabled && $this->cron_jobs->isOrdersJobRunning() ) {
			$this->cron_jobs->stopOrdersJob();
		}
		$this->loader->add_action( 'woozoho_caching', $this->cron_jobs, 'startCaching' );
	}

	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'woozoho-connector',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Woozoho_Connector_Loader    Get the loader
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	public static function get_option( $option_name ) {
		return WC_Admin_Settings::get_option( 'wc_zoho_connector_' . $option_name );
	}


}

/**
 * Main instance of WooCommerce.
 *
 * Returns the main instance of WC to prevent the need to use globals.
 *
 * @since  0.3
 * @return Woozoho_Connector
 */
function Woozoho_Connector() {
	return Woozoho_Connector::instance();
}

// Global for backwards compatibility.
$GLOBALS['woozoho_connector'] = Woozoho_Connector();
