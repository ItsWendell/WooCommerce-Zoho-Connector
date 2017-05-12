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

register_activation_hook( __FILE__, 'woozoho_plugin_activate' );
register_deactivation_hook( __FILE__, 'woozoho_plugin_deactivate' );

function woozoho_plugin_activate()
{
	//Do Activation Stuff.
}

function woozoho_plugin_deactivate()
{
	//Do Deactivation Stuff.
}

