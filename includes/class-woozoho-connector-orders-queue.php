<?php

class Woozoho_Connector_Orders_Queue {

	protected $maxTries;
	protected $dataTable;
	protected $client;

	public function __construct( $client ) {
		$this->maxTries  = WC_Admin_Settings::get_option( "wc_zoho_connector_orders_queue_max_tries" );
		$this->dataTable = "woozoho_orders_tracker";
		$this->client    = $client;
	}

	public function getQueue() {
		global $wpdb;

		$this->client->writeDebug( "Orders Queue", "Listing all orders in queue in array." );

		$results = array();
		//First getting error orders with maxtries.

		$errorQueue = $wpdb->get_results(
			"
	SELECT * 
	FROM " . $wpdb->prefix . $this->dataTable . "
	WHERE (status = 'error' OR status = 'queued')
		AND tries <= " . $this->maxTries . "
	"
		);

		foreach ( $errorQueue as $orderQueueItem ) {
			array_push( $results, $orderQueueItem->post_id );
		}

		$this->client->writeDebug( "Orders Queue", count( $results ) . " active orders in queue listed" );

		return $results;
	}

	function addOrder( $order_id ) {
		global $wpdb;

		$this->client->writeDebug( "Orders Queue", "Inserting order '" . $order_id . "' into queue." );

		if ( ! $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM " . $wpdb->prefix . $this->dataTable . "
                    WHERE post_id = %d LIMIT 1",
				$order_id
			)
		)
		) {
			if ( $wpdb->insert(
				$wpdb->prefix . $this->dataTable,
				array(
					'post_id'      => $order_id,
					'status'       => 'queued',
					'date_created' => date( "Y-m-d H:i:s" ),
					'date_updated' => date( "Y-m-d H:i:s" ),
					'tries'        => 0,
					'message'      => ''
				),
				array(
					'%d',
					'%s',
					'%s',
					'%s',
					'%d',
					'%s'
				)
			)
			) {
				ZohoConnector::writeDebug( "Orders Queue", "Sucessfully inserted '" . $order_id . "' into queue." );
			} else {
				ZohoConnector::writeDebug( "Orders Queue", "ERROR: Something went wrong with queuing '" . $order_id . "' into queue." );
			}
		}
		{
			ZohoConnector::writeDebug( "Orders Queue", "Order '" . $order_id . "' already exists in queue, skipping..." );
		}
	}

	function updateOrder( $order_id, $status, $message = '', $pushtry = false ) {
		global $wpdb;

		$orderQueue = $wpdb->get_row( "SELECT * FROM " . $wpdb->prefix . $this->dataTable . " WHERE post_id = " . $order_id );
		if ( $orderQueue != null ) {
			if ( $wpdb->update(
					$wpdb->prefix . 'woozoho_orders_tracker',
					array(
						'status'       => $status,    // string
						'date_updated' => date( "Y-m-d H:i:s" ),
						'message'      => ( $message != '' ) ? $message : $orderQueue->message,
						'tries'        => $pushtry ? ( $orderQueue->tries + 1 ) : $orderQueue->tries
					),
					array( 'post_id' => $order_id ),
					array(
						'%s',    // value1
						'%s',
						'%s',
						'%d'    // value2
					),
					array( '%d' )
				) !== false
			) {
				$this->client->writeDebug( "Orders Queue", "Successfully updated order queue of order_id:" . $order_id );
			} else {
				$this->client->writeDebug( "Orders Queue", "ERROR: Error updating orders queue for " . $order_id . "." );
			}
		} else {
			$this->client->writeDebug( "Orders Queue", "ERROR: No orders in queue found for order id: " . $order_id . "." );
		}
	}
}
