<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://digispark.nl/
 * @since      1.0.0
 *
 * @package    Woozoho_Connector
 * @subpackage Woozoho_Connector/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Woozoho_Connector
 * @subpackage Woozoho_Connector/public
 * @author     Wendell Misiedjan <me@digispark.nl>
 */
class Woozoho_Connector_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	private $core;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 *
	 * @param      string $plugin_name The name of the plugin.
	 * @param      string $version The version of this plugin.
	 * @param      Woozoho_Connector_Zoho_Client $core ZohoConnector
	 */
	public function __construct( $plugin_name, $version, $core ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->core        = $core;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Woozoho_Connector_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Woozoho_Connector_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/woozoho-connector-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Woozoho_Connector_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Woozoho_Connector_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/woozoho-connector-public.js', array( 'jquery' ), $this->version, false );

	}

	public function woozoho_queue_order( $order_id ) {
		if ( WC_Admin_Settings::get_option( "wc_zoho_connector_cron_orders_recurrence" ) == "directly" ) {
			$this->core->queueOrder( $order_id, false );
		} else {
			$this->core->getOrdersQueue()->addOrder( $order_id );
		}
	}
}
