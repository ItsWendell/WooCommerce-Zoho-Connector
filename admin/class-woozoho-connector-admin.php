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
		Woozoho_Connector_Logger::write_debug( "Settings", "Settings updated!" );
		woocommerce_update_options( self::get_settings() );
		if ( $oldOrderRecurrence != Woozoho_Connector::get_option( "cron_orders_recurrence" ) ) {
			Woozoho_Connector()->cron_jobs->update_orders_job( Woozoho_Connector::get_option( "cron_orders_recurrence" ) );
		}
	}

	public static function get_action_url( $action ) {
		$current_url = menu_page_url( "wc-settings", false );

		return $current_url . "&tab=zoho_connector&action=" . $action;
	}

	public static function get_settings() {
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
				'desc' => __( '<a href="https://accounts.zoho.com/apiauthtoken/create?SCOPE=ZohoBooks/booksapi">Click here to generate an auth code.</a>', 'woozoho-connector' ),
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
				'checkboxgroup' => '',
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
				'name'    => __( 'Daily API Limit / Zoho Version', 'woozoho-connector' ),
				'type'    => 'select',
				'options' => array(
					'2500' => __( 'Paid Organization (2500 calls/day)', 'woozoho-connector' ),
					'1000' => __( 'Free Organization (1000 calls/day)', 'woozoho-connector' )
				),
				'default' => '1000',
				'desc'    => __( 'All versions have a 100 calls per minute limit.', 'woozoho-connector' ),
				'id'      => 'wc_zoho_connector_api_limit'
			),

			array(
				'name'    => __( 'Shipping Handling', 'woozoho-connector' ),
				'type'    => 'select',
				'options' => array(
					'native'    => __( 'Native (native in invoice)', 'woozoho-connector' ),
					'line-item' => __( 'Line Item (as a product)', 'woozoho-connector' )
				),
				'default' => 'line-item',
				'desc'    => __( 'The native shipping line in the invoices do not support taxes, choose line-item if you need taxes to be calculated. The product price will change for each other based on shipping price of that order.', 'woozoho-connector' ),
				'id'      => 'wc_zoho_connector_shipping_invoice_method'
			),

			array(
				'name'    => __( 'Shipping Line-Item SKU', 'woozoho-connector' ),
				'type'    => 'text',
				'default' => 'SHIPPING-COST',
				'desc'    => __( 'Is only being used for line-item shipping handling. Creates product with sku if it does not exist.', 'woozoho-connector' ),
				'id'      => 'wc_zoho_connector_shipping_invoice_item_sku'
			),

			array(
				'name'    => __( 'Reference Number Format', 'woozoho-connector' ),
				'type'    => 'text',
				'default' => ( is_multisite() == true ) ? 'WP-%order_number%' : 'WP-%post_id%',
				'desc'    => __( '%post_id% = WooCommerce Order Post ID & %blog_id% = multisite blog id, %order_number% for actual order number', 'woozoho-connector' ),
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
				'name'    => __( 'Default item type', 'woozoho-connector' ),
				'type'    => 'select',
				'options' => array(
					'sales'     => __( 'Sales', 'woozoho-connector' ),
					'inventory' => __( 'Inventory', 'woozoho-connector' )
				),
				'default' => 'sales',
				'desc'    => __( 'Using Zoho Inventory? Or only books?', 'woozoho-connector' ),
				'id'      => 'wc_zoho_connector_default_item_type'
			),

			array(
				'name'    => __( 'Push items including or excluding tax', 'woozoho-connector' ),
				'type'    => 'select',
				'options' => array(
					'including' => __( 'Including tax', 'woozoho-connector' ),
					'excluding' => __( 'Excluding tax', 'woozoho-connector' )
				),
				'default' => 'sales',
				'desc'    => __( 'Create new items including tax or excluding tax?', 'woozoho-connector' ),
				'id'      => 'wc_zoho_connector_default_tax_setting'
			),

			array(
				'name'    => __( 'Item placeholder SKU', 'woozoho-connector' ),
				'type'    => 'text',
				'default' => 'PLACEHOLDER',
				'desc'    => __( 'Being used when item creation is disabled to fill in the order if no products are found.', 'woozoho-connector' ),
				'id'      => 'wc_zoho_connector_item_placeholder'
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

		//wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/woozoho-connector-admin.css', array(), $this->version, 'all' );
		//Not being used right now.
	}

	//WooCommerce Settings Tab Functionality

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		//wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/woozoho-connector-admin.js', array( 'jquery' ), $this->version, false );
		//Not being used right now.
	}

	public function add_action_links( $links ) {
		$settings_link = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=zoho_connector">' . __( 'Settings', 'woozoho-connector' ) . '</a>' )
		);

		return array_merge( $settings_link, $links );
	}

	public function woocommerce_add_settings_tab( $settings_tabs ) {
		$settings_tabs['zoho_connector'] = __( 'Zoho Connector', 'woozoho-connector' );

		return $settings_tabs;
	}

	public function woocommerce_add_bulk_actions( $bulk_actions ) {
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
	public function woocommerce_bulk_action_send_zoho( $redirect_to, $action, $post_ids ) {
		if ( $action !== 'send_zoho' ) {
			return $redirect_to;
		}

		foreach ( $post_ids as $post_id ) {
			Woozoho_Connector()->client->schedule_order( $post_id, false );
		}

		$redirect_to = add_query_arg( 'bulk_send_zoho', count( $post_ids ), $redirect_to );

		return $redirect_to;
	}

	public function woocommerce_zoho_connector_admin_notices() {
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

	public function woozoho_woocommerce_error_admin_notice() {
		?>
        <div class="error">
            <p><?php _e( 'Connector for WooCommerce & Zoho Books is enabled but not effective. It requires WooCommerce in order to work.', 'woozoho-connector' ); ?></p>
        </div>
		<?php
	}

	public function add_bulk_actions_product( $bulk_actions ) {
		$bulk_actions['send_product_to_zoho'] = __( 'Send to Zoho Books', 'woozoho-connector' );

		return $bulk_actions;
	}

	public function handle_bulk_actions_product( $redirect_to, $action, $post_ids ) {
		if ( $action !== 'send_product_to_zoho' ) {
			return $redirect_to;
		}

		$exporter = new Woozoho_Connector_Zoho_Exporter();

		$results = $exporter->export_products( $post_ids );

		$redirect_to = add_query_arg( 'send_product_to_zoho', count( $results["exported_products"] ), $redirect_to );

		$redirect_to = add_query_arg( 'send_product_to_zoho_errors', count( $results["errors"] ), $redirect_to );

		return $redirect_to;
	}

	function notice_bulk_actions_product() {
		if ( ! empty( $_REQUEST['send_product_to_zoho'] ) ) {
			$products_count = intval( $_REQUEST['send_product_to_zoho'] );

			printf(
				'<div id="message" class="updated fade">' .
				_n( '%s product successfully pushed.', '%s products are successfully pushed.', $products_count, 'woozoho-connector' )
				. '</div>',
				$orders_count
			);
		}
	}


	public function create_sales_order( $order_id ) {
		Woozoho_Connector()->client->create_sales_order( $order_id );
	}

	public function sync_prices() {
		Woozoho_Connector()->client->sync_prices();
	}

	public function sku_checker() {
		Woozoho_Connector()->client->sku_checker();
	}

	public function woocommerce_settings_tab() {
		include_once WOOZOHO_ABSPATH . 'admin/partials/woozoho-connector-settings-tab.php';
	}

	public function schedule_order( $order_id ) {
		$hook = current_action();

		if ( $hook == "woocommerce_new_order" ) {
			Woozoho_Connector_Logger::write_debug( "WooCommerce", "A new order ($order_id) received." );
		}

		if ( Woozoho_Connector::get_option( "cron_orders_recurrence" ) == "directly" ) {
			Woozoho_Connector()->client->schedule_order( $order_id );
		} else {
			Woozoho_Connector()->client->get_orders_queue()->addOrder( $order_id );
		}
	}
}
