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

class Woozoho_Connector_Cronjobs {

	/**
	 * The unique identifier of the client
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Woozoho_Connector_Zoho_Client $client The string used to uniquely identify this plugin.
	 */

	/**
	 * Initialize the collections used to maintain the actions and filters.
	 *
	 * @since    1.0.0
	 * @var
	 */
	public function __construct() {
		//Define jobs
	}

	public function updateOrdersJob( $recurrence ) {
		$isEnabled = Woozoho_Connector::get_option( "cron_orders_enabled" );
		if ( $isEnabled ) {
			$oldRecurrence = wp_get_schedule( 'woozoho_orders_job' );
			if ( $recurrence != $oldRecurrence ) {
				Woozoho_Connector_Logger::writeDebug( "Cron Jobs", "Changing cronjob from " . $oldRecurrence . " to " . $recurrence );
				$nextTime = wp_next_scheduled( 'woozoho_orders_job' );
				wp_unschedule_event( $nextTime, 'woozoho_orders_job' );
				if ( ! $this->isOrdersJobRunning() ) {
					$this->setupOrdersJob();
				} else {
					Woozoho_Connector_Logger::writeDebug( "Cron Jobs", "Error, job was still running! Cant change " . $oldRecurrence . " to " . $recurrence );
				}
			}
		} else {
			$this->stopOrdersJob();
		}
	}

	public function isOrdersJobRunning() {
		return wp_next_scheduled( 'woozoho_orders_job' );
	}

	public function setupOrdersJob() {
		$recurrence = Woozoho_Connector::get_option( "cron_orders_recurrence" );
		if ( $recurrence == "directly" ) {
			$recurrence = "hourly";
		}
		Woozoho_Connector_Logger::writeDebug( "Cron Jobs", "Setting up cron job for Orders on a " . $recurrence . " basis..." );
		wp_schedule_event( time(), $recurrence, 'woozoho_orders_job' );
		Woozoho_Connector_Logger::writeDebug( "Cron Jobs", "Cron job is successfully setup." );
	}

	public function stopOrdersJob() {
		Woozoho_Connector_Logger::writeDebug( "Cron Jobs", "Stopping 'woozoho_orders_job'." );
		if ( $this->isOrdersJobRunning() ) {
			$nextTime = wp_next_scheduled( 'woozoho_orders_job' );
			wp_unschedule_event( $nextTime, 'woozoho_orders_job' );
		}
	}

	public function runOrdersJob() {
		Woozoho_Connector_Logger::writeDebug( "Cron Jobs", "Running orders cron job..." );
		$ordersQueue = Woozoho_Connector()->client->getOrdersQueue()->getQueue();

		if ( count( $ordersQueue ) >= 1 ) {
			foreach ( $ordersQueue as $order_id ) {
				Woozoho_Connector_Logger::writeDebug( "Cron Jobs", "Pushing Order ID: " . $order_id );
				Woozoho_Connector()->client->pushOrder( $order_id );
			}
		} else {
			if ( Woozoho_Connector()->client->getCache()->isEnabled() && ! defined( 'WOOZOHO_ITEMS_CACHING' ) ) {
				Woozoho_Connector()->client->getCache()->checkItemsCache( true );
			}
		}
	}

	public function startCaching() {
		Woozoho_Connector()->client->getCache()->cacheItems();
		Woozoho_Connector()->client->getCache()->cacheTaxes();
	}
}
