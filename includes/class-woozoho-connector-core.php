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
	protected $ordersQueue;
	public $zohoClient;
	protected $logLocation = "./";

	public function __construct() {
		$args                   = array();
		$args["accessToken"]    = WC_Admin_Settings::get_option( "wc_zoho_connector_token" );
		$args["organizationId"] = WC_Admin_Settings::get_option( "wc_zoho_connector_organisation_id" );
		$this->zohoClient       = new ZohoClient( $args );
		$this->ordersQueue      = new Woozoho_Connector_Orders_Queue( $this );
	}

	public function getOrdersQueue() {
		return $this->ordersQueue;
	}

	public function getContact( $email ) {
		$args          = array();
		$args["email"] = $email;
		$data          = $this->zohoClient->listContacts( $args );
		if ( $data->contacts ) {
			$contact_id = $data->contacts[0]->contact_id;

			return $this->zohoClient->retrieveContact( $contact_id )->contact;
		} else {
			return null;
		}
	}

	public function getSalesOrders( $user_id ) {
		$args                = array();
		$args["customer_id"] = $user_id;
		$data                = $this->zohoClient->listSalesOrders( $args );
		if ( $data->salesorders ) {
			return $data->salesorders;
		} else {
			return null;
		}
	}

	public function getItem( $sku ) {
		$args        = array();
		$args["sku"] = $sku;
		$data        = $this->zohoClient->listItems( $args );
		if ( $data->items ) {
			return $data->items[0];
		} else {
			return null;
		}
	}

	public function sendNotificationEmail( $subject, $message ) {
		$mailTo = WC_Admin_Settings::get_option( "wc_zoho_connector_notify_email" );
		if ( $mailTo ) {
			$headers[] = 'From: WordPress Zoho Connector <wordpress@mydoo.nl>';
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			wp_mail( $mailTo, "WooCommerce Zoho Connector:" . $subject, $message, $headers );
			$this->writeDebug( "Notification Email", "Email with subject '" . $subject . " sent to " . $mailTo );
		}
	}

	public function createContact( $user_info, $order ) {
		$contactData = array(
			array(
				"contact_name"    => $order->get_billing_company(),
				"company_name"    => $order->get_billing_company(),
				"website"         => $user_info->user_url,
				"email"           => $user_info->user_email,
				"notes"           => "Created by WooCommerce Zoho Connector.",
				"billing_address",
				array(
					"attention" => $order->get_billing_company(),
					"address"   => $order->get_billing_address_1(),
					"street2"   => $order->get_billing_address_2(),
					"city"      => $order->get_billing_city(),
					"state"     => $order->get_billing_state(),
					"zip"       => $order->get_billing_postcode(),
					"country"   => $order->get_billing_country(),
					"phone"     => $order->get_billing_phone()
				),
				"shipping_address",
				array(
					"attention" => $order->get_shipping_company(),
					"address"   => $order->get_shipping_address_1(),
					"street2"   => $order->get_shipping_address_2(),
					"city"      => $order->get_shipping_city(),
					"state"     => $order->get_shipping_state(),
					"zip"       => $order->get_shipping_postcode(),
					"country"   => $order->get_shipping_country(),
					"phone"     => $order->get_shipping_phone()
				),
				"contact_persons" => array(
					array(
						"first_name" => $order->get_billing_first_name(),
						"last_name"  => $order->get_billing_last_name(),
						"email"      => $user_info->user_email,
						"phone"      => $order->get_billing_phone()
					)
				)
			)
		);

		$resultData = $this->zohoClient->createContact( $contactData );

		if ( $resultData->contact->contact ) {
			return $resultData->contact->contact;
		} else {
			return null;
		}
	}

	public function processQueue() {
		$ordersData = $this->ordersQueue->getQueue();
		foreach ( $ordersData as $order_id ) {
			$this->pushOrder( $order_id );

		}
	}

	public function itemConvert( $zohoItem, $storeItem, $quantity = 0 ) {
		$convertedItem = array(
			"item_id"     => $zohoItem->item_id,
			"rate"        => $zohoItem->rate,
			"name"        => $zohoItem->name,
			"description" => $zohoItem->description,
			"tax_id"      => $zohoItem->tax_id,
			"unit"        => $zohoItem->unit,
		);

		if ( $storeItem == false && $quantity != 0 ) {
			$convertedItem["quantity"] = $quantity;
		} else {
			$convertedItem["quantity"] = $storeItem->get_quantity();
		}

		return $convertedItem;
	}

	function itemToNotes( $item ) {
		$returnString = "";
		$returnString .= $item['name'] . " ";
		if ( $item->get_product() && ! empty( $item->get_product()->get_sku() ) ) {
			$returnString .= "(" . $item->get_product()->get_sku() . ") ";
		}
		$returnString .= "| Quantity: " . $item->get_quantity();
		$returnString .= " | Total Price: " . $item->get_total();
		$returnString .= "\n";

		return $returnString;
	}

	function pushOrder( $order_id ) {
		$this->writeDebug( "Push Order", "Pushing order ID; " . $order_id );
		$isQueued = false;
		try {
			$order         = new WC_Order( $order_id );
			$order_user_id = (int) $order->user_id;
			$user_info     = get_userdata( $order_user_id );
			$items         = $order->get_items();

			$this->writeDebug( "Push Order", "Syncing Zoho Order ID " . $order_id . " from (" . $user_info->user_email . "): " );

			$contact = $this->getContact( $user_info->user_email );

			//$this->writeDebug( "Push Order", "Contact info ($user_info->user_email) \n " . serialize( $contact ) );

			if ( ! $contact ) {
				$this->writeDebug( "Push Order", "Order " . $order_id . ": Email (" . $user_info->user_email . ") doesn't exist in Zoho. Creating contact..." );
				$contact = $this->createContact( $user_info, $order->get_billing_address(), $order->get_shipping_address() );
				if ( ! $contact ) {
					$this->writeDebug( "Push Order", "Order " . $order_id . ": Can't create contact (" . $user_info->user_email . ") in Zoho. Updating order and continue." );
					$this->ordersQueue->updateOrder( $order_id, "error", "Couldn't create contact in Zoho.", true );
					$isQueued = true;
					$this->sendNotificationEmail( "Can't create contact: " . $user_info->user_email, "WooCommerce Zoho Connector couldn't create the contact required for the order, updating queue." );
					return false;
				} else {
					$this->writeDebug( "Push Order", "Order " . $order_id . ": Contact created by email (" . $user_info->user_email . ") in Zoho." );
					if ( WC_Admin_Settings::get_option( "wc_zoho_connector_notify_email_option" )["new_user"] ) {
						$this->sendNotificationEmail( "A new user has been created.", "New user with email: " . $user_info->user_email . " has been created." );
					}
				}
			}


			$this->writeDebug( "Push Order", "Order " . $order_id . ": Populating Order data for Zoho..." );
			$salesOrder = array();

			//setup basic sales order details.
			if ( WC_Admin_Settings::get_option( "wc_zoho_connector_testmode" ) ) {
				$salesOrder["salesorder_number"] = "TEST-" . $order_id;
			}

			$salesOrder["customer_id"]       = $contact->contact_id;
			$salesOrder["customer_name"]     = $contact->company_name;
			$salesOrder["date"]              = date( 'Y-m-d' );

			if ( is_multisite() ) {
				$salesOrder["reference_number"] = "WP-" . $order_id . "-" . get_current_blog_id();
			} else {
				$salesOrder["reference_number"] = "WP-" . $order_id;
			}

			$salesOrder["line_items"]        = array();
			$salesOrder["status"]            = "draft";

			$num = 0;

			$missingProducts  = "";
			$inactiveProducts = "";

			//Loop through each item.
			foreach ( $items as $item ) {
				if ( $item->get_product() ) {
					if ( ! empty( $item->get_product()->get_sku() ) ) {
						$this->writeDebug( "Push Order", "Looking for product in zoho with SKU: " . $item->get_product()->get_sku() );
						$zohoItem = $this->getItem( $item->get_product()->get_sku() );
						if ( ! $zohoItem ) {
							$missingProducts .= $this->itemToNotes( $item );
							$this->writeDebug( "Push Order", "Product (" . $item->get_product()->get_sku() . ") not found. Adding to notes." );
							if ( WC_Admin_Settings::get_option( "wc_zoho_connector_notify_email_option" )["sku_zoho"] ) {
								$this->sendNotificationEmail( "SKU '" . $item->get_product()->get_sku() . "' not found in Zoho.", "SKU '" . $item->get_product()->get_sku() . "' not found in Zoho." );
							}
						} else {
							if ( $zohoItem->status == "active" ) {
								$line_item = $this->itemConvert( $zohoItem, $item );
								array_push( $salesOrder["line_items"], $line_item );
								$this->writeDebug( "Push Order", "Product " . $item->get_product()->get_sku() . " successfully found." );
							} else {
								$this->writeDebug( "Push Order", "Product " . $item->get_product()->get_sku() . " is inactive, added to notes." );
								$inactiveProducts .= $this->itemToNotes( $item );
							}
						}
					} else {
						$missingProducts .= $this->itemToNotes( $item );
						$this->writeDebug( "Push Order", "Error: SKU not found of product ID: " . $item['product_id'] . ". Product is added as a note." );
						if ( WC_Admin_Settings::get_option( "wc_zoho_connector_notify_email_option" )["sku_woocommerce"] ) {
							$this->sendNotificationEmail( "SKU of Product  '" . $item['name'] . "' not found in WooCommerce.", "SKU of Product  '" . $item['name'] . "' (" . $item['product_id'] . ") not found in WooCommerce." );
						}
					}
				} else {
					$missingProducts .= $this->itemToNotes( $item );
					$this->writeDebug( "Push Order", "Error: Product (" . $item['name'] . ") not found in WooCommerce, so we can't find SKU. Product is added as a note." );
				}
			}

			if ( empty( $salesOrder["line_items"] ) ) {
				$placeHolder = $this->itemConvert( $this->getItem( "PLACEHOLDER" ), false, 1 );
				array_push( $salesOrder["line_items"], $placeHolder ); //TODO: Find long term solution for this.
				$this->writeDebug( "Push Order", "Order is empty or no SKU's can't be found for Order ID: " . $order_id );
			}

			//Handle Notes
			if ( ! empty( $missingProducts ) ) {
				$missingProducts = "Missing products:\n" . $missingProducts;
			}

			if ( ! empty( $inactiveProducts ) ) {
				$inactiveProducts = "Inactive products:\n" . $inactiveProducts;
			}

			$orderComment = $missingProducts . "\n" . $inactiveProducts . "\nAutomatically generated by WooCommerce Zoho Connector.";

			//$salesOrder["notes"] = $notes;

			$salesOrderOutput = $this->zohoClient->createSalesOrder( $salesOrder, WC_Admin_Settings::get_option( "wc_zoho_connector_testmode" ) );

			//$this->writeDebug( "Push Order", "Sales order data: " . serialize( $salesOrderOutput ) );

			if ( ! $salesOrderOutput->salesorder ) {
				$this->writeDebug( "Push Order", "Couldn't push $order_id to Zoho. Something went wrong with pushing the order data." );
				$this->ordersQueue->updateOrder( $order_id, "error", "Something went wrong with pushing the order data.", true );

				return false;
			}

			$this->ordersQueue->updateOrder( $order_id, "success", "Successfully pushed to Zoho.", true );
			$this->zohoClient->createComment( $salesOrderOutput->salesorder->salesorder_id, $orderComment );

			$this->writeDebug( "Push Order", "Successfully pushed $order_id to Zoho." );

			return true;

		} catch ( Exception $e ) {
			if ( ! $isQueued ) {
				$this->ordersQueue->updateOrder( $order_id, "error", $e->getMessage(), true );
			}
			$this->writeDebug( "Push Order", "ERROR Exception:" . $e->getMessage() );

			return false;
		}
	}

	public function writeDebug( $type, $data ) {
		if ( WC_Admin_Settings::get_option( "wc_zoho_connector_debugging" ) ) {
			file_put_contents( '/home/mydoodev/mydoo.nl/wp-content/plugins/woozoho-connector/debug_log',
				"[" . date( "Y-m-d H:i:s" ) . "] [" . $type . "] " . $data . "\n", FILE_APPEND );
		}
	}

	public function queueOrder( $post_id, $timestamp ) {

		$this->ordersQueue->addOrder( $post_id );

		if ( $timestamp ) {
			wp_schedule_single_event( $timestamp, 'woozoho_push_order_queue', array( $post_id ) );
		} else {
			wp_schedule_single_event( time() + 5, 'woozoho_push_order_queue', array( $post_id ) );
		}
	}
}

