<?php

/*
Plugin Name: WooCommerce Zoho Connector
Plugin URI: http://digispark.nl/WooCommerce-Zoho-Connector/
Description: A connector for WooCommerce to Zoho.
Version: 0.1
Author: WMisiedjan
Author URI: http://digispark.nl/
License: GPL2
Prefix: woozoho
*/

include "vendor/autoload.php";
include "zoho_settings.php";

register_activation_hook( __FILE__, 'woozoho_activate' );
register_deactivation_hook( __FILE__, 'woozoho_deactivate' );

//TODO: Find a Zoho API Library for PHP, We're using composer, so that's handy.

function woozoho_activate($networkwide)
{
	global $wpdb;

	if (function_exists('is_multisite') && is_multisite()) {
		// Network support for Multi-site / Multi-store's, activating this plugin for each individual store.
		if ($networkwide) {
			$old_blog = $wpdb->blogid;
			//
			$blogs = get_sites();
			foreach ($blogs as $blog) {
				$blog_id = get_object_vars($blog)["blog_id"];
				switch_to_blog($blog_id);
				// Check if WooCommerce is activated
				if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
					_woozoho_activate();
				}
			}
			switch_to_blog($old_blog);
			return;
		}
		_woozoho_activate();
	}
	else
	{
		_woozoho_activate();
	}
}

function _woozoho_activate()
{
	//Adding woozoho_push_order to event hook payment complete.
	add_action ( 'woocommerce_payment_complete', 'woozoho_push_order', 10, 1 );
}

function woozoho_plugin_deactivate()
{
	//Do Deactivation Stuff.
}

function woozoho_client()
{
	global $zoho_key, $zoho_org;
	$client = new \shqear\lib\ZohoClient();
	$client->accessToken = $zoho_key;
	$client->organizationId = $zoho_org;
	return $client;
}

function woozoho_zoho_lastorder()
{
	$client = woozoho_client();
	$client->listSalesOrders();
}

function woozoho_get_contact($email)
{
	$client = woozoho_client();
	$args = array();
	$args["email"] = $email;
	$data = $client->listContacts($args);
	if($data["contacts"]) {
		$contact_id = $data["contacts"][0]["contact_id"];
		return $client->retrieveContact($contact_id);
	}
	else
		return NULL;
}

/* Pushing Order To Zoho CRM / Inventory */
function woozoho_push_order($order_id)
{
	$order = new WC_Order( $order_id );
	$order_user_id = (int)$order->user_id;
	$user_info = get_userdata($order_user_id);
	$items = $order->get_items();
	$client = woozoho_client();

	$contact = array();
	$contact = woozoho_get_contact($user_info->user_email);

	if($contact) {

		$salesorder = array();

		//setup basic sales order details.
		$salesorder["customer_id"]          = $contact["contact_id"];
		$salesorder["customer_name"]        = $contact["company_name"];
		$salesorder["salesorder_number"]    = null;
		$salesorder["date"]                 = date( 'Y-m-d H:i:s' );
		$salesorder["reference_number"]     = "WP-" . $order_id;

		$num = 0;


		//Loop through each item.
		foreach($items as $item)
		{
			$salesorder["items"][$num]["item_name"] = $item->item_name;
			$num++;
		}

		$client->createSalesOrder($salesorder);

	//TODO: Find products in inventory by SKU (Product ID), connect to new Sales Order.
	//TODO: Item not found? Write in notes. Make Note in Logbook?
	//TODO: Find user in CRM by email, connect to new Sales Order.
	//TODO: Push Order To Inventory / CRM.
	}
}

