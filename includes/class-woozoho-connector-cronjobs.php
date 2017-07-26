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

	/**
	 * The unique identifier of the client
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Woozoho_Connector_Zoho_Client $client The string used to uniquely identify this plugin.
	 */
	protected $client;

	/**
	 * Initialize the collections used to maintain the actions and filters.
	 *
	 * @since    1.0.0
	 * @var
	 */
	public function __construct( $client ) {
		//Define jobs
		$this->client = $client;
	}

	public function setupOrdersJob() {
		$recurrence = WC_Admin_Settings::get_option( "wc_zoho_connector_cron_orders_recurrence" );
		if ( $recurrence == "directly" ) {
			$recurrence = "hourly";
		}
		$this->client->writeDebug( "Cron Jobs", "Setting up cron job for Orders on a " . $recurrence . " basis..." );
		wp_schedule_event( time(), $recurrence, 'woozoho_orders_job' );
		$this->client->writeDebug( "Cron Jobs", "Cron job is successfully setup." );
	}

	public function isOrdersJobRunning() {
		return wp_next_scheduled( 'woozoho_orders_job' );
	}

	public function updateOrdersJob( $recurrence ) {
		$isEnabled = WC_Admin_Settings::get_option( "wc_zoho_connector_cron_orders_enabled" );
		if ( $isEnabled ) {
			$oldRecurrence = wp_get_schedule( 'woozoho_orders_job' );
			if ( $recurrence != $oldRecurrence ) {
				$this->client->writeDebug( "Cron Jobs", "Changing cronjob from " . $oldRecurrence . " to " . $recurrence );
				$nextTime = wp_next_scheduled( 'woozoho_orders_job' );
				wp_unschedule_event( $nextTime, 'woozoho_orders_job' );
				if ( ! $this->isOrdersJobRunning() ) {
					$this->setupOrdersJob();
				} else {
					$this->client->writeDebug( "Cron Jobs", "Error, job was still running! Cant change " . $oldRecurrence . " to " . $recurrence );
				}
			}
		} else {
			$this->stopOrdersJob();
		}
	}

	public function stopOrdersJob() {
		$this->client->writeDebug( "Cron Jobs", "Stopping 'woozoho_orders_job'." );
		if ( $this->isOrdersJobRunning() ) {
			$nextTime = wp_next_scheduled( 'woozoho_orders_job' );
			wp_unschedule_event( $nextTime, 'woozoho_orders_job' );
		}
	}

	public function runOrdersJob() {
		$this->client->writeDebug( "Cron Jobs", "Running orders cron job..." );
		$ordersQueue = $this->client->getOrdersQueue()->getQueue();

		foreach ( $ordersQueue as $order_id ) {
			$this->client->writeDebug( "Cron Jobs", "Pushing Order ID: " . $order_id );
			$this->client->pushOrder( $order_id );
		}
	}

	public function startCaching() {
		$this->client->cacheItems();
	}
}
