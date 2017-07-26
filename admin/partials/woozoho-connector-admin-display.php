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

$current_url = "//" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

if ( ! empty( $_REQUEST["doaction"] ) ) {
	echo "<a class='button-primary' href='#'>Clearing cache....</a>";
	echo "The system is renewing your caching files in the background, you can continue with whatever you where doing.";
	$this->doAction( $_REQUEST["doaction"] );
} else {
	echo "<a class='button-primary' href='" . $current_url . "&doaction=clearcache'>Clear Caching</a>";
}

woocommerce_admin_fields( self::get_settings() );

