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
	 */
	public function __construct() {
	}

	public static function woocommerce_update_settings() {
		$oldOrderRecurrence = Woozoho_Connector::get_option( "cron_orders_recurrence" );
		Woozoho_Connector_Logger::writeDebug( "Settings", "Settings updated!" );
		$data = Woozoho_Connector::get_option( "notify_email_option" );
		Woozoho_Connector_Logger::writeDebug( "Settings",
			"Settings data: " .
			print_r( $data->zoho_sku, true ) );
		woocommerce_update_options( self::get_settings() );
		if ( $oldOrderRecurrence != Woozoho_Connector::get_option( "cron_orders_recurrence" ) ) {
			Woozoho_Connector()->cron_jobs->updateOrdersJob( Woozoho_Connector::get_option( "cron_orders_recurrence" ) );
		}
	}

	public static function get_settings() {
		//TODO: Move setings to own file.

		$settings = array(

			array(
				'name' => __( 'Zoho Connector Authentication', 'woozoho-connector' ),
				'type' => 'title',
				'desc' => 'Your Zoho API credentials for connecting WooCommerce to Zoho.',
				'id'   => 'wc_zoho_connector_section_authentication'
			),

			array(
				'name' => __( 'Auth Token', 'woozoho-connector' ),
				'type' => 'text',
				'desc' => __( 'Generate a auth code here.', 'woozoho-connector' ),
				'id'   => 'wc_zoho_connector_token'
			),

			array(
				'name' => __( 'Organisation Id', 'woozoho-connector' ),
				'type' => 'text',
				'desc' => __( 'Find your organisation id here.', 'woozoho-connector' ),
				'id'   => 'wc_zoho_connector_organisation_id'
			),

			array( 'type' => 'sectionend', 'id' => 'wc_zoho_connector_section_authentication' ),

			array(
				'name' => __( 'Email Notifications', 'woozoho-connector' ),
				'type' => 'title',
				'desc' => 'Get a message or get notified when a certain thing happens.',
				'id'   => 'wc_zoho_connector_section_mail_notifications'
			),

			array(
				'name' => __( 'Notification Email', 'woozoho-connector' ),
				'type' => 'text',
				'desc' => __( 'Email where notifications and logs from synchronizing are sent too.', 'woozoho-connector' ),
				'id'   => 'wc_zoho_connector_notify_email'
			),

			array(
				'title'         => __( 'Notification Email Options', 'woozoho-connector' ),
				'desc'          => __( 'When an order failed to sync.', 'woozoho-connector' ),
				'id'            => 'wc_zoho_connector_email_notifications_failed_order',
				'default'       => 'no',
				'type'          => 'checkbox',
				'checkboxgroup' => 'start',
				'desc_tip'      => __( 'Send a notification when a order failed to sync to zoho.', 'woozoho-connector' ),
			),

			array(
				'desc'          => __( 'A new contact is created in Zoho.', 'woocommerce' ),
				'id'            => 'wc_zoho_connector_email_notifications_new_contact',
				'default'       => 'no',
				'type'          => 'checkbox',
				'checkboxgroup' => '',
				'desc_tip'      => __( 'Send a notification when the connector finds no existing contact and creates a new one.', 'woozoho-connector' ),
			),

			array(
				'desc'          => __( 'Prices are dynamically changed.', 'woocommerce' ),
				'id'            => 'wc_zoho_connector_email_notifications_price_changes',
				'default'       => 'no',
				'type'          => 'checkbox',
				'desc_tip'      => __( 'Only works when dynamic price changes are enabled.', 'woozoho-connector' ),
				'checkboxgroup' => 'end',
				'autoload'      => false,
			),

			array( 'type' => 'sectionend', 'id' => 'wc_zoho_connector_section_mail_notifications' ),

			array(
				'name' => __( 'Cron Jobs', 'woozoho-connector' ),
				'type' => 'title',
				'desc' => 'Settings for synchronisation cron jobs.',
				'id'   => 'wc_zoho_connector_section_cronjobs'
			),

			array(
				'name' => __( 'Enable Orders Cron', 'woozoho-connector' ),
				'type' => 'checkbox',
				'desc' => __( 'Automatically sync orders to zoho?', 'woozoho-connector' ),
				'id'   => 'wc_zoho_connector_cron_orders_enabled'
			),

			array(
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

			array(
				'name'    => __( 'Orders Queue Max Tries', 'woozoho-connector' ),
				'type'    => 'text',
				'default' => '10',
				'desc'    => __( 'How often should we try to push orders?', 'woozoho-connector' ),
				'id'      => 'wc_zoho_connector_orders_queue_max_tries'
			),

			array( 'type' => 'sectionend', 'id' => 'wc_zoho_connector_section_cronjobs' ),

			array(
				'name' => __( 'Advanced Settings', 'woozoho-connector' ),
				'type' => 'title',
				'desc' => 'Enable / disable debugging, test-mode, caching and more!',
				'id'   => 'wc_zoho_connector_section_advanced_settings'
			),

			array(
				'name' => __( 'Debugging', 'woozoho-connector' ),
				'type' => 'checkbox',
				'desc' => __( 'Enable debugging in logfile?', 'woozoho-connector' ),
				'id'   => 'wc_zoho_connector_debugging'
			),

			array(
				'name' => __( 'Test mode', 'woozoho-connector' ),
				'type' => 'checkbox',
				'desc' => __( 'Enable testmode?', 'woozoho-connector' ),
				'id'   => 'wc_zoho_connector_testmode'
			),

			array(
				'name'    => __( 'API Caching for Items', 'woozoho-connector' ),
				'type'    => 'select',
				'options' => array(
					'disabled' => __( 'Disabled', 'woozoho-connector' ),
					'1 day'    => __( '1 day', 'woozoho-connector' ),
					'2 days'   => __( '2 days', 'woozoho-connector' ),
					'1 week'   => __( '1 week', 'woozoho-connector' ),
					'2 weeks'  => __( '2 weeks', 'woozoho-connector' )
				),
				'default' => '1 day',
				'desc'    => __( 'How long is caching valid?', 'woozoho-connector' ),
				'id'      => 'wc_zoho_connector_api_cache_items'
			),

			array(
				'name'    => __( 'Reference Number Format', 'woozoho-connector' ),
				'type'    => 'text',
				'default' => ( is_multisite() == true ) ? 'WP-%post_id%-%blog_id%' : 'WP-%post_id%',
				'desc'    => __( '%post_id% = WooCommerce Order ID & %blog_id% = multisite blog id', 'woozoho-connector' ),
				'id'      => 'wc_zoho_connector_reference_number_format'
			),

			array(
				'name'    => __( 'WooCommerce Order Status After Pushing', 'woozoho-connector' ),
				'type'    => 'select',
				'options' => array(
					'wc-processing' => __( 'Processing', 'woozoho-connector' ),
					'wc-on-hold'    => __( 'On Hold', 'woozoho-connector' ),
					'wc-completed'  => __( 'Completed', 'woozoho-connector' ),
				),
				'default' => 'wc-processing',
				'desc'    => __( 'Change order status after pushing to zoho.', 'woozoho-connector' ),
				'id'      => 'wc_zoho_connector_pushed_order_status'
			),

			array(
				'name'    => __( 'Singular Caching', 'woozoho-connector' ),
				'type'    => 'checkbox',
				'default' => 'no',
				'desc'    => __( 'Merged caching for multi-stores.', 'woozoho-connector' ),
				'id'      => 'wc_zoho_connector_multisite_single_cache'
			),

			array(
				'name'    => __( 'Price Preference', 'woozoho-connector' ),
				'type'    => 'select',
				'options' => array(
					'woocommerce' => __( 'WooCoomerce', 'woozoho-connector' ),
					'zoho'        => __( 'Zoho', 'woozoho-connector' )
				),
				'default' => 'zoho',
				'desc'    => __( 'Price preference for order?', 'woozoho-connector' ),
				'id'      => 'wc_zoho_connector_pricing'
			),

			array(
				'name'    => __( 'Dynamic Price Updates', 'woozoho-connector' ),
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => __( 'Update the pricing in selected preference if different?', 'woozoho-connector' ),
				'id'      => 'wc_zoho_connector_pricing_updates'
			),

			array(
				'name'    => __( 'Create items if not exist', 'woozoho-connector' ),
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => __( 'Create missing items in Zoho?', 'woozoho-connector' ),
				'id'      => 'wc_zoho_connector_create_items'
			),

			array(
				'type' => 'sectionend',
				'id'   => 'wc_zoho_connector_section_advanced_settings'
			)
		);

		return apply_filters( 'wc_zoho_connector_settings', $settings );
	}

	//Settings page add link to settings page.

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

	//WooCommerce Settings Tab Functionality

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

	public function add_action_links( $links ) {
		$settings_link = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=zoho_connector">' . __( 'Settings', $this->plugin_name ) . '</a>' )
		);

		return array_merge( $settings_link, $links );
	}

	public function woocommerce_add_settings_tab( $settings_tabs ) {
		$settings_tabs['zoho_connector'] = __( 'Zoho Connector', 'woozoho-connector' );

		return $settings_tabs;
	}

	function woocommerce_add_bulk_actions( $bulk_actions ) {
		$bulk_actions['send_zoho'] = __( 'Push To Zoho', 'woozoho-connector' );

		return $bulk_actions;
	}

	/**
	 * @param $redirect_to
	 * @param $action
	 * @param $post_ids
	 *
	 * @return string
	 */
	function woocommerce_bulk_action_send_zoho( $redirect_to, $action, $post_ids ) {
		if ( $action !== 'send_zoho' ) {
			return $redirect_to;
		}

		foreach ( $post_ids as $post_id ) {
			Woozoho_Connector()->client->scheduleOrder( $post_id, false );
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
		Woozoho_Connector()->client->pushOrder( $order_id );
	}

	public function woocommerce_settings_tab() {
		include_once WOOZOHO_ABSPATH . 'admin/partials/woozoho-connector-settings-tab.php';
	}

	public function doAction( $action ) {
		switch ( $action ) {
			case "clearcache": {
				WC_Admin_Notices::add_custom_notice( 'woozoho_clear_cache_notice', "Clearing Zoho Connector cache in the background..." );
				Woozoho_Connector_Logger::writeDebug( "Action", "Regenerating caches..." );
				Woozoho_Connector()->client->getCache()->scheduleCaching();
			}
		}
	}

	public function scheduleOrder( $order_id ) {
		$hook = current_action();

		if ( $hook == "woocommerce_new_order" ) {
			Woozoho_Connector_Logger::writeDebug( "WooCommerce", "A new order ($order_id) received." );
		}

		if ( Woozoho_Connector::get_option( "cron_orders_recurrence" ) == "directly" ) {
			Woozoho_Connector()->client->scheduleOrder( $order_id );
		} else {
			Woozoho_Connector()->client->getOrdersQueue()->addOrder( $order_id );
		}
	}
	//END
}
