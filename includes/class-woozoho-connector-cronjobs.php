<?php
/**
* Initializing cron jobs for syncing to / from Zoho
*
* @link       https://digispark.nl/
* @since      1.0.0
*
* @package    Woozoho_Connector
* @subpackage Woozoho_Connector/includes
*/

/**
* Initializing cron jobs for syncing to / from Zoho
*
* This class defines all code necessary to run during the plugin's deactivation.
*
* @since      1.0.0
* @package    Woozoho_Connector
* @subpackage Woozoho_Connector/includes
* @author     Wendell Misiedjan <me@digispark.nl>
*/
class Woozoho_Connector_Cronjobs {

/**
* Short Description. (use period)
*
* Long Description.
*
* @since    1.0.0
*/
public function setup() {
	add_action('woozoho_orders_event', 'woozoho_orders_event');
}

public function setup_orders()
{
	if (! wp_next_scheduled ( 'woozoho_orders_job' )) {
		wp_schedule_event(time(), WC_Admin_Settings::get_option("wc_zoho_connector_cron_orders_recurrence"), 'woozoho_orders_event');
	}
}

public function woozoho_orders_event()
{

}



}
