<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://digispark.nl/
 * @since      1.0.0
 *
 * @package    Woozoho_Connector
 * @subpackage Woozoho_Connector/admin/partials
 */

//TODO: View items in queue
//TODO: Show latest error

if ( ! empty( $_REQUEST["action"] ) ) {
	switch ( $_REQUEST["action"] ) {
		case "renew_items_cache": {
			Woozoho_Connector_Logger::writeDebug( "Settings Action", "Regenerating caches..." );
			Woozoho_Connector()->client->getCache()->scheduleCaching();
			WC_Admin_Settings::add_message( "We're renewing the cache in the background, you can continue with your activities." );
			WC_Admin_Settings::show_messages();
		}

		case "process_orders_queue": {
			Woozoho_Connector()->cron_jobs->setupOrdersJob();
			WC_Admin_Settings::add_message( "We're processing the orders queue in the background, you can continue with your activities." );
			WC_Admin_Settings::show_messages();
		}

		case "sync_prices": {
			WC_Admin_Settings::add_message( "We're syncing the prices in the background, you can continue with your activities." );
			WC_Admin_Settings::show_messages();
			Woozoho_Connector()->client->schedule_sync_prices();
		}
	}
}

echo "<a class='page-title-action' href='" . Woozoho_Connector_Admin::get_action_url( 'renew_items_cache' ) . "'>Renew Items Cache</a>";
echo "<a class='page-title-action' href='" . Woozoho_Connector_Admin::get_action_url( 'process_orders_queue' ) . "'>Process Orders Queue</a>";
echo "<a class='page-title-action' href='" . Woozoho_Connector_Admin::get_action_url( 'sync_prices' ) . "'>Sync Prices</a>";


woocommerce_admin_fields( self::get_settings() );

