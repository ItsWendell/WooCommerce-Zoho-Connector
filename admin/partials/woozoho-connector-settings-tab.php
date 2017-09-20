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
			Woozoho_Connector_Logger::write_debug( "Settings Action", "Regenerating caches..." );
			Woozoho_Connector()->client->get_cache()->scheduleCaching();
			WC_Admin_Settings::add_message( "We're renewing the cache in the background, you can continue with your activities." );
			WC_Admin_Settings::show_messages();
			break;
		}

		case "process_orders_queue": {
			Woozoho_Connector()->cron_jobs->setup_orders_job();
			WC_Admin_Settings::add_message( "We're processing the orders queue in the background, you can continue with your activities." );
			WC_Admin_Settings::show_messages();
			break;
		}

		case "sync_prices": {
			WC_Admin_Settings::add_message( "We're syncing the prices in the background, you can continue with your activities." );
			WC_Admin_Settings::show_messages();
			Woozoho_Connector()->client->schedule_sync_prices();
			break;
		}

		case "sku_checker": {
			WC_Admin_Settings::add_message( "We're checking SKU's in the background, you can continue with your activities." );
			WC_Admin_Settings::show_messages();
			Woozoho_Connector()->client->schedule_sku_checker();
			break;
		}

		case "print_taxes": {
			WC_Admin_Settings::add_message( "We're printing the taxes in de debug log." );
			WC_Admin_Settings::show_messages();
			Woozoho_Connector()->client->print_taxes();
			break;
		}
	}
}

echo "<a class='page-title-action' href='" . Woozoho_Connector_Admin::get_action_url( 'renew_items_cache' ) . "'>Renew Items Cache</a>";
echo "<a class='page-title-action' href='" . Woozoho_Connector_Admin::get_action_url( 'process_orders_queue' ) . "'>Process Orders Queue</a>";
echo "<a class='page-title-action' href='" . Woozoho_Connector_Admin::get_action_url( 'sync_prices' ) . "'>Sync Prices</a>";
echo "<a class='page-title-action' href='" . Woozoho_Connector_Admin::get_action_url( 'sku_checker' ) . "'>SKU Checker</a>";


woocommerce_admin_fields( self::get_settings() );

