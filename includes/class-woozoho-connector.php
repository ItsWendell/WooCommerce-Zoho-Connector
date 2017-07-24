<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://digispark.nl/
 * @since      1.0.0
 *
 * @package    Woozoho_Connector
 * @subpackage Woozoho_Connector/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Woozoho_Connector
 * @subpackage Woozoho_Connector/includes
 * @author     Wendell Misiedjan <me@digispark.nl>
 */
class Woozoho_Connector {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Woozoho_Connector_Loader $loader Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $plugin_name The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $version The current version of the plugin.
	 */
	protected $version;

	protected $core;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		$this->plugin_name = 'woozoho-connector';
		$this->version     = '0.1.1';

		$this->load_dependencies();
		$this->set_locale();

		$this->core = new ZohoConnector();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_cron_jobs();
	}

	public function core() {
		return $this->core;
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Woozoho_Connector_Loader. Orchestrates the hooks of the plugin.
	 * - Woozoho_Connector_i18n. Defines internationalization functionality.
	 * - Woozoho_Connector_Admin. Defines all hooks for the admin area.
	 * - Woozoho_Connector_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 * Lenart van Bolten
	 * Telecom Bunschoten
	 * 0622106412
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-woozoho-connector-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-woozoho-connector-i18n.php';

		//ZohoConnector Core Functionality
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-woozoho-connector-API.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-woozoho-connector-orders-queue.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-woozoho-connector-core.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-woozoho-connector-cronjobs.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-woozoho-connector-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-woozoho-connector-public.php';


		$this->loader = new Woozoho_Connector_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Woozoho_Connector_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Woozoho_Connector_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Woozoho_Connector_Admin( $this->get_plugin_name(), $this->get_version(), $this->core );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		//WooCommerce Tab Settings Integration
		$this->loader->add_filter( 'woocommerce_settings_tabs_array', $plugin_admin, 'woocommerce_add_settings_tab' );
		$this->loader->add_action( 'woocommerce_settings_tabs_zoho_connector', $plugin_admin, 'woocommerce_settings_tab' );
		$this->loader->add_action( 'woocommerce_update_options_zoho_connector', $plugin_admin, 'woocommerce_update_settings' );

		$plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_name . '.php' );
		$this->loader->add_filter( 'plugin_action_links_' . $plugin_basename, $plugin_admin, 'add_action_links' );

		//WooCommerce Actions
		$this->loader->add_filter( 'bulk_actions-edit-shop_order', $plugin_admin, 'woocommerce_add_bulk_actions' );
		$this->loader->add_filter( 'handle_bulk_actions-edit-shop_order', $plugin_admin, 'woocommerce_bulk_action_send_zoho', 10, 3 );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'woocommerce_zoho_connector_admin_notices' );
		$this->loader->add_action( 'woozoho_push_order_queue', $plugin_admin, 'pushOrder', 10, 1 );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		$plugin_public = new Woozoho_Connector_Public( $this->get_plugin_name(), $this->get_version(), $this->core );

		//Styles & Scripts
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		//Queue orders for synchronisation to Zoho.
		$this->loader->add_action( 'woocommerce_new_order', $plugin_public, 'woozoho_queue_order', 20, 1 );
	}

	private function define_cron_jobs() {
		$cron_jobs = new Woozoho_Connector_Cronjobs( $this->core );

		$orders_recurrence = WC_Admin_Settings::get_option( "wc_zoho_connector_cron_orders_recurrence" );
		$isEnabled         = WC_Admin_Settings::get_option( "wc_zoho_connector_cron_orders_enabled" );
		if ( $isEnabled ) {
			if ( $orders_recurrence == "directly" ) {
				$orders_recurrence = "hourly";
			}
				if ( ! $cron_jobs->isOrdersJobRunning() ) {
					$cron_jobs->setupOrdersJob();
				}

				$this->loader->add_action( 'woozoho_orders_job', $cron_jobs, 'runOrdersJob' );

		} else if ( ! $isEnabled && $cron_jobs->isOrdersJobRunning() ) {
			$cron_jobs->stopOrdersJob();
		}
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
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Woozoho_Connector_Loader    Orchestrates the hooks of the plugin.
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

}
