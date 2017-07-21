<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://digispark.nl/
 * @since      1.0.0
 *
 * @package    Woozoho_Connector
 * @subpackage Woozoho_Connector/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Woozoho_Connector
 * @subpackage Woozoho_Connector/admin
 * @author     Wendell Misiedjan <me@digispark.nl>
 */
class Woozoho_Connector_Admin {

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

	private $client;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 *
	 * @param      string $plugin_name The name of this plugin.
	 * @param      string $version The version of this plugin.
	 * @param      ZohoConnector $client Client for the core of Zoho Connector.
	 */
	public function __construct( $plugin_name, $version, $client ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->client      = $client;
	}

	/**
	 * Register the stylesheets for the admin area.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/woozoho-connector-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/woozoho-connector-admin.js', array( 'jquery' ), $this->version, false );

	}

	//Settings page add link to settings page.
	public function add_action_links( $links ) {
		$settings_link = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=zoho_connector">' . __( 'Settings', $this->plugin_name ) . '</a>' )
		);

		return array_merge( $settings_link, $links );
	}

	//WooCommerce Settings Tab Functionality
	public function woocommerce_add_settings_tab( $settings_tabs ) {
		$settings_tabs['zoho_connector'] = __( 'Zoho Connector', 'woozoho-connector' );

		return $settings_tabs;
	}

	function woocommerce_add_bulk_actions( $bulk_actions ) {
		$bulk_actions['send_zoho'] = __( 'Push To Zoho', 'woozoho-connector' );

		return $bulk_actions;
	}

	function woocommerce_bulk_action_send_zoho( $redirect_to, $action, $post_ids ) {
		if ( $action !== 'send_zoho' ) {
			return $redirect_to;
		}

		foreach ( $post_ids as $post_id ) {
			$this->client->queueOrder( $post_id, false );
		}

		$redirect_to = add_query_arg( 'bulk_send_zoho', count( $post_ids ), $redirect_to );

		return $redirect_to;
	}


	function woocommerce_zoho_connector_admin_notices() {
		if ( ! empty( $_REQUEST['bulk_send_zoho'] ) ) {
			$orders_count = intval( $_REQUEST['bulk_send_zoho'] );

			printf(
				'<div id="message" class="updated fade">' .
				_n( '%s order is queued to be send to Zoho.', '%s orders are queued to be send to Zoho.', $orders_count, 'woozoho-connector' )
				. '</div>',
				$orders_count
			);
		}
	}

	function pushOrder( $order_id ) {
		$this->client->pushOrder( $order_id );
	}


	public static function woocommerce_settings_tab() {
		woocommerce_admin_fields( self::get_settings() );
	}

	public static function woocommerce_update_settings() {
		$oldOrderRecurrence = WC_Admin_Settings::get_option( "wc_zoho_connector_cron_orders_recurrence" );
		ZohoConnector::writeDebug( "Settings", "Settings updated!" );
		$data = WC_Admin_Settings::get_option( "wc_zoho_connector_notify_email_option" );
		ZohoConnector::writeDebug( "Settings",
			"Settings data: " .
			print_r( $data->zoho_sku, true ) );
		woocommerce_update_options( self::get_settings() );
		if ( $oldOrderRecurrence != WC_Admin_Settings::get_option( "wc_zoho_connector_cron_orders_recurrence" ) ) {
			$cronJobs = new Woozoho_Connector_Cronjobs( new ZohoConnector() );
			$cronJobs->updateOrdersJob( WC_Admin_Settings::get_option( "wc_zoho_connector_cron_orders_recurrence" ) );
		}
	}

	public static function get_settings() {
		$settings = array(
			'section_title'          => array(
				'name' => __( 'Zoho Connector Settings', 'woozoho-connector' ),
				'type' => 'title',
				'desc' => '',
				'id'   => 'wc_zoho_connector_section_title'
			),
			'token'                  => array(
				'name' => __( 'Auth Token', 'woozoho-connector' ),
				'type' => 'text',
				'desc' => __( 'Generate a auth code here.', 'woozoho-connector' ),
				'id'   => 'wc_zoho_connector_token'
			),
			'organisation_id'     => array(
				'name' => __( 'Organisation Id', 'woozoho-connector' ),
				'type' => 'text',
				'desc' => __( 'Find your organisation id here.', 'woozoho-connector' ),
				'id'   => 'wc_zoho_connector_organisation_id'
			),
			'notify_email'        => array(
				'name' => __( 'Notification Email', 'woozoho-connector' ),
				'type' => 'text',
				'desc' => __( 'Email where notifications and logs from synchronizing are sent too.', 'woozoho-connector' ),
				'id'   => 'wc_zoho_connector_notify_email'
			),
			'notify_email_option' => array(
				'name'    => __( 'Notification Email Options', 'woozoho-connector' ),
				'type'    => 'multiselect',
				'desc'    => __( 'Where should emails be enabled?', 'woozoho-connector' ),
				'options' => array(
					'sku_woocommerce' => 'WooCommerce SKU not found.',
					'sku_zoho'        => 'Zoho SKU not found.',
					'new_user'        => 'New user has been created.',
					'zoho_error_user' => 'Cant create zoho user.',
				),
				'id'      => 'wc_zoho_connector_notify_email_option'
			),
			'debugging'           => array(
				'name' => __( 'Debugging', 'woozoho-connector' ),
				'type' => 'checkbox',
				'desc' => __( 'Enable debugging in logfile?', 'woozoho-connector' ),
				'id'   => 'wc_zoho_connector_debugging'
			),
			'testmode'            => array(
				'name' => __( 'Test mode', 'woozoho-connector' ),
				'type' => 'checkbox',
				'desc' => __( 'Enable testmode?', 'woozoho-connector' ),
				'id'   => 'wc_zoho_connector_testmode'
			),
			'cron_orders_enabled' => array(
				'name' => __( 'Enable Orders Cron', 'woozoho-connector' ),
				'type' => 'checkbox',
				'desc' => __( 'Automatically sync orders to zoho?', 'woozoho-connector' ),
				'id'   => 'wc_zoho_connector_cron_orders_enabled'
			),
			'cron_orders_recurrence' => array(
				'name'    => __( 'Syncing Orders', 'woozoho-connector' ),
				'type'    => 'select',
				'options' => array(
					'directly'   => __( 'Directly', 'woozoho-connector' ),
					'hourly'     => __( 'Hourly', 'woozoho-connector' ),
					'twicedaily' => __( 'Twice Daily', 'woozoho-connector' ),
					'daily'      => __( 'Daily', 'woozoho-connector' )
				),
				'desc'    => __( 'How often should orders be synced?', 'woozoho-connector' ),
				'id'      => 'wc_zoho_connector_cron_orders_recurrence'
			),
			'orders_queue_max_tries' => array(
				'name'    => __( 'Orders Queue Max Tries', 'woozoho-connector' ),
				'type'    => 'text',
				'default' => '3',
				'desc'    => __( 'How often should we try to push orders?', 'woozoho-connector' ),
				'id'      => 'wc_zoho_connector_orders_queue_max_tries'
			),
			'section_end'            => array(
				'type' => 'sectionend',
				'id'   => 'wc_zoho_connector_section_end'
			)
		);

		return apply_filters( 'wc_zoho_connector_settings', $settings );
	}
	//END
}
