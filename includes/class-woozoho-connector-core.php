<?php
/**
* The core plugin class.
*
* This is used to define internationalization, admin-specific hooks, and
* public-facing site hooks.
*
* Also maintains the unique identifier of this plugin as well as the current
* version of the plugin.
*
* @since      1.0.0
* @package    Woozoho_Connector
* @subpackage Woozoho_Connector/includes
* @author     Wendell Misiedjan <me@digispark.nl>
*/

class ZohoConnector {

	protected $organizationId;
	protected $accessToken;
	public $zohoClient;
	protected $logLocation = "./";

	public function __construct()
	{
		$args = array();
		$args["accessToken"] = WC_Admin_Settings::get_option("wc_zoho_connector_token");
		$args["organizationId"] = WC_Admin_Settings::get_option("wc_zoho_connector_organisation_id");
		$this->zohoClient = new ZohoClient($args);
	}

	public function getContact($email)
	{
		$args = array();
		$args["email"] = $email;
		$data = $this->zohoClient->listContacts($args);
		if($data->contacts)
		{
			$contact_id = $data->contacts[0]->contact_id;
			return $this->zohoClient->retrieveContact($contact_id);
		}
		else
			return NULL;
	}

	public function getSalesOrders($user_id)
	{
		$args = array();
		$args["customer_id"] = $user_id;
		$data = $this->zohoClient->listSalesOrders($args);
		if($data->salesorders)
		{
			return $data->salesorders;
		}
		else
			return NULL;
	}

	public function getItem($sku)
	{
		$args = array();
		$args["sku"] = $sku;
		$data = $this->zohoClient->listItems($args);
		if($data->items)
			return $data->items[0];
		else
			return null;
	}

	public function sendNotificationEmail($subject, $message)
	{
		$headers[] = 'From: WordPress Zoho Connector <wordpress@mydoo.nl>';
		wp_mail(WC_Admin_Settings::get_option("wc_zoho_connector_notify_email"), "WooCommerce Zoho Connector:".$subject, $message, $headers );
	}

	public function createContact($user_info, $billing_address, $shipping_address)
	{
		$contactData = array(
			array(
				"contact_name" => "Bowman and Co",
                "company_name" => "Bowman and Co",
				"website" => "www.bowmanfurniture.com",
				"email", ""
	));
		//TODO: Finish data above.
		/* Data for creating a contact in Zoho Books.
    "billing_address": {
		"attention": "Mr.John",
        "address": "4900 Hopyard Rd",
        "street2": "Suite 310",
        "state_code": "CA",
        "city": "Pleasanton",
        "state": "CA",
        "zip": 94588,
        "country": "U.S.A",
        "fax": "+1-925-924-9600"
    },
    "shipping_address": {
		"attention": "Mr.John",
        "address": "4900 Hopyard Rd",
        "street2": "Suite 310",
        "state_code": "CA",
        "city": "Pleasanton",
        "state": "CA",
        "zip": 94588,
        "country": "U.S.A",
        "fax": "+1-925-924-9600"
    },
    "contact_persons": [
        {
	        "salutation": "Mr",
            "first_name": "Will",
            "last_name": "Smith",
            "email": "willsmith@bowmanfurniture.com",
            "phone": "+1-925-921-9201",
            "mobile": "+1-4054439562",
            "is_primary_contact": true
        }
    ]
	});
		*/

		$this->zohoClient->createContact();
	}

	function pushOrder( $order_id ) {
		$this->writeDebug( "Push Order", "Pushing order ID; ".$order_id );
		try {
			$order         = new WC_Order( $order_id );
			$order_user_id = (int) $order->user_id;
			$user_info     = get_userdata( $order_user_id );
			$items         = $order->get_items();

			$this->writeDebug( "Push Order", "Syncing Zoho Order ID " . $order_id . " from (" . $user_info->user_email . "): " );

			$contact = $this->getContact( $user_info->user_email );

			if(!$contact)
			{
				$this->writeDebug( "Push Order", "Order " . $order_id . ": Email (" . $user_info->user_email . ") doesn't exist in Zoho. Creating contact..." );
				$contact = $this->createContact($user_info, $order->get_billing_address(), $order->get_shipping_address());
				if(!$contact)
				{
					$this->writeDebug( "Push Order", "Order " . $order_id . ": Can't create contact (" . $user_info->user_email . ") in Zoho. Updating order and continue." );
					$this->updateOrderQueue($order_id, "error", "Couldn't create contact in Zoho.", true);
					sendNotificationEmail("Can't create contact: " . $user_info->user_email, "WooCommerce Zoho Connector couldn't create the contact required for the order, updating queue.");
					return false;
				}
				else
				{
					$this->writeDebug( "Push Order", "Order " . $order_id . ": Contact created by email (" . $user_info->user_email . ") in Zoho." );
				}
			}


			$this->writeDebug( "Push Order", "Order " . $order_id . ": Populating Order data for Zoho..." );
			$salesorder = array();

			//setup basic sales order details.
			$salesorder["salesorder_number"] = "TEST-" . $order_id;
			$salesorder["customer_id"]       = $contact->contact_id;
			$salesorder["customer_name"]     = $contact->company_name;
			$salesorder["date"]              = date( 'Y-m-d H:i:s' );
			$salesorder["reference_number"]  = "WP-" . $order_id;
			$salesorder["items"]             = array();

			$num = 0;

			//Loop through each item.
			foreach ( $items as $item ) {
				$client->writeDebug( "Push Order", "Looking for product in zoho with SKU: " . $item->get_product()->get_sku() . "\n" );

				$zohoproduct = $client->getItem( $item->get_product()->get_sku() );
				//TODO: Check if product exist, if product not exist, save as note or create new product.

				print_r( $zohoproduct );

				array_push( $salesorder["items"], $zohoproduct ); //add to array for sending to server.
			}

			$client->writeDebug( "Push Order", "Final result of order (", $salesorder );

			//$client->createSalesOrder($salesorder); //Sending to Zoho.

			//TODO: Check if this order was a success, if not try again later?
		} catch ( Exception $e ) {
			//TODO: Add order ID to error que, trying again in an hour?
		}
	}

	function queueOrder ( $order_id ) {
		global $wpdb;

		$this->writeDebug("Orders Queue", "Inserting order '".$order_id."' into queue.");

		if(!$wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM " . $wpdb->prefix . "woozoho_orders_tracker
                    WHERE post_id = %d LIMIT 1",
				$order_id
			)
		)) {
			if($wpdb->insert(
				$wpdb->prefix . 'woozoho_orders_tracker',
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
			))
			{
				ZohoConnector::writeDebug("Orders Queue", "Sucessfully inserted '".$order_id."' into queue.");
			}
			else
			{
				ZohoConnector::writeDebug("Orders Queue", "ERROR: Something went wrong with queuing '".$order_id."' into queue.");
			}
		}
		{
			ZohoConnector::writeDebug("Orders Queue", "Order '".$order_id."' already exists in queue, skipping...");
		}
	}

	function updateOrderQueue( $order_id, $status, $message = '', $pushtry = false)
	{
		global $wpdb;

		$orderQueue = $wpdb->get_row( "SELECT * FROM ".$wpdb->prefix . "woozoho_orders_tracker WHERE post_id = ".$order_id );
		if($orderQueue != null) {
			if($wpdb->update(
				$wpdb->prefix . 'woozoho_orders_tracker',
				array(
					'status' => $status,    // string
					'date_updated' => date( "Y-m-d H:i:s" ),
					'message' => ($message != '') ? $message : $orderQueue->message,
					'tries' => $pushtry ? ($orderQueue->tries + 1) : $orderQueue->tries
				),
				array( 'post_id' => $order_id),
				array(
					'%s',    // value1
					'%s',
					'%s',
					'%d'    // value2
				),
				array( '%d' )
			) !== false)
			{
				$this->writeDebug("Orders Queue", "Successfully updated order queue of order_id:" . $order_id);
			}
			else
			{
				$this->writeDebug("Orders Queue", "ERROR: Error updating orders queue for ".$order_id.".");
			}
		}
		else
		{
			$this->writeDebug("Orders Queue", "ERROR: No orders in queue found for order id: ".$order_id.".");
		}
	}

	public function writeDebug($type, $data)
	{
		if(WC_Admin_Settings::get_option("wc_zoho_connector_debugging")) {
			file_put_contents( '/home/mydoodev/mydoo.nl/wp-content/plugins/woozoho-connector/debug_log.txt',
				"[WooCommerce Zoho Connector] [" . date( "Y-m-d H:i:s" ) . "] [" . $type . "] " . $data . "\n", FILE_APPEND );
		}
	}
}

