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
class Woozoho_Connector_Zoho_Client {

	protected $ordersQueue;
	protected $cache;
	protected $zohoAPI;
	protected $logLocation = "./";
	protected $apiCachingItemsTimeout;

	public function __construct() {
		//API Settings
		$args                   = array();
		$args["accessToken"]    = Woozoho_Connector::get_option( "token" );
		$args["organizationId"] = Woozoho_Connector::get_option( "organisation_id" );

		//Variables
		$this->logLocation = realpath( __DIR__ . DIRECTORY_SEPARATOR . '..' );

		//Modules
		$this->zohoAPI     = new Woozoho_Connector_Zoho_API( $args );
		$this->ordersQueue = new Woozoho_Connector_Orders_Queue();
		$this->cache       = new Woozoho_Connector_Zoho_Cache();
	}

	/**
	 * @return Woozoho_Connector_Orders_Queue
	 */
	public function getOrdersQueue() {
		return $this->ordersQueue;
	}

	/**
	 * @return Woozoho_Connector_Zoho_API
	 */
	public function getAPI() {
		return $this->zohoAPI;
	}

	public function getAllItems() {
		$returnData = array();
		$nextPage   = 1;

		while ( $nextPage ) {
			Woozoho_Connector_Logger::writeDebug( "Zoho Client", "Getting all items... current page: " . $nextPage );
			$args         = array();
			$args["page"] = $nextPage;

			$resultData = $this->zohoAPI->listItems( $args );
			//TODO: Catch API return errors, retry certain times.
			$hasNextPage = $resultData->page_context->has_more_page;
			if ( $resultData->items ) {
				foreach ( $resultData->items as $item ) {
					$returnData[] = $item;
				}
			}

			Woozoho_Connector_Logger::writeDebug( "Zoho Client", "Current item count " . count( $returnData ) );

			if ( $hasNextPage ) {
				$nextPage ++;
			} else {
				break;
			}
		}

		return $returnData;
	}

	public function sendNotificationEmail( $subject, $message ) {
		$mailTo = Woozoho_Connector::get_option( "notify_email" );
		if ( $mailTo ) {
			if ( ! is_multisite() ) {
				$headers[] = 'From: Zoho Connector <' . get_option( 'admin_email' ) . '>';
			} else {
				$headers[] = 'From: Zoho Connector ' . get_bloginfo( 'name' ) . '<' . get_option( 'admin_email' ) . '>';
			}
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			wp_mail( $mailTo, "WooCommerce Zoho Connector:" . $subject, $message, $headers );
			Woozoho_Connector_Logger::writeDebug( "Notification Email", "Email with subject '" . $subject . " sent to " . $mailTo );
		}
	}

	public function processQueue() {
		$ordersData = $this->ordersQueue->getQueue();
		foreach ( $ordersData as $order_id ) {
			$this->pushOrder( $order_id );
		}
	}

	public function getReferenceNumber( $order_id ) {
		$rawString = Woozoho_Connector::get_option( "reference_number_format" );

		$refVars = array(
			'post_id' => $order_id,
			'blog_id' => get_current_blog_id()
		);

		$result = preg_replace_callback(
			'/\%(.*?)\%/',
			function ( $match ) use ( $refVars ) {
				return str_replace( $match[0], isset( $refVars[ $match[1] ] ) ? $refVars[ $match[1] ] : $match[0], $match[0] );
			},
			$rawString );

		Woozoho_Connector_Logger::writeDebug( "Reference Number", $result );

		return $result;
	}


	/**
	 * @param WC_Order_Item_Product $item
	 *
	 * Required API calls: 2.
	 *
	 * @return bool|object
	 */
	public function createZohoItem( $item ) {
		//TODO: Handle discounts, return proper order line item?
		//TODO: Add support for Taxes (default tax setting or synchronisation of tax classes)

		$product = $item->get_product();

		if ( $product == null ) {
			return false;
		}

		$pushData = [
			"name"         => $product->get_name(),
			"rate"         => $product->get_regular_price(),
			"description"  => $item->get_product()->get_description(),
			"sku"          => $item->get_product()->get_sku(),
			"product_type" => "goods"
		];

		$apiResult = $this->zohoAPI->createItem( $pushData );

		if ( $apiResult->code === 0 ) {
			Woozoho_Connector_Logger::writeDebug( "Zoho Client", "Successfully created product in Zoho: " . $item->get_name() . " (" . $item->get_product()->get_sku() . ")" );

			return $apiResult->item;
		} else {
			Woozoho_Connector_Logger::writeDebug( "Zoho Client", "Something went wrong while creating product: " . $item->get_name() );
			return false;
		}

	}

	/**
	 * @var WC_Order_Item_Product $line_item
	 * @var $zoho_item
	 * @var string|bool $preference
	 *
	 * Returns updated line_item
	 * @return bool|object
	 */

	public function update_pricing( $line_item, $zoho_item, $order_id, $preference = false ) {
		if ( empty( $line_item ) || empty( $zoho_item ) ) {
			Woozoho_Connector_Logger::writeDebug( "Price Updater", "Skipping updates, WooCommerce or Zoho Item is empty." );

			return $zoho_item;
		}

		if ( $preference == false ) {
			$preference = Woozoho_Connector::get_option( "pricing" );
		}

		$price_woocommerce = (float) $line_item->get_product()->get_price();
		$price_zoho        = (float) $zoho_item->rate;
		$sku               = $line_item->get_product()->get_sku();

		Woozoho_Connector_Logger::writeDebug( "Price Updater", "Pricing preference: $preference, WooCommerce: $price_woocommerce - Zoho: $price_zoho" );

		if ( $price_zoho != $price_woocommerce ) {
			if ( $preference == "zoho" ) {
				update_post_meta( $line_item->get_product()->get_id(), '_price', $price_zoho );
				update_post_meta( $line_item->get_product()->get_id(), '_regular_price', $price_zoho );

				wc_get_order( $order_id )->add_product( $line_item->get_product(), $line_item->get_quantity() );
				wc_get_order( $order_id )->remove_item( $line_item->get_id() );
				wc_delete_order_item( $line_item->get_id() );
				wc_get_order( $order_id )->update_taxes();
				wc_get_order( $order_id )->calculate_totals();
				wc_get_order( $order_id )->save();


				Woozoho_Connector_Logger::writeDebug( "Price Updater", "Updated WooCommerce pricing to Zoho." );

				if ( Woozoho_Connector::get_option( "email_notifications_price_changes" ) == "yes" ) {
					$this->sendNotificationEmail( "Price update in WooCommerce: $sku - $price_woocommerce -> $price_zoho",
						"Price update in WooCommerce: $sku - $price_woocommerce -> $price_zoho" );
				}

				return $zoho_item;
			} else if ( $preference == "woocommerce" ) {
				$update = $this->updateZohoItem( $zoho_item->item_id, [ "rate" => $price_woocommerce ] );
				if ( $update->code === 0 ) {
					Woozoho_Connector_Logger::writeDebug( "Price Updater", "Updated Zoho pricing to WooCommerce." );

					if ( Woozoho_Connector::get_option( "email_notifications_price_changes" ) == "yes" ) {
						$this->sendNotificationEmail( "Price update in Zoho: $sku - $price_zoho -> $price_woocommerce",
							"Price update in Zoho: $sku - $price_zoho -> $price_woocommerce" );
					}

					return $update->item;
				} else {
					Woozoho_Connector_Logger::writeDebug( "Price Updater", "Ooops something went wrong: " . $update->message );

					return $zoho_item;
				}
			}
		} else {
			Woozoho_Connector_Logger::writeDebug( "Price Updater", "Prices are equal, no update needed." );

			return $zoho_item;
		}
	}

	public function updateZohoItem( $item_id, $changes ) {
		return $this->zohoAPI->updateItem( $item_id, $changes );
	}

	/**
	 * @param $order_id
	 *
	 * @return bool
	 *
	 * Dynamic pricing updates notes:
	 *  - If preference is set to WooCommerce;
	 *    - Schedule a new event?
	 *    - Update products
	 *
	 * Maximum API calls (without caching):
	 * - 3 for contact info (2 tries, 1 creation)
	 * - 1-x Possible caching
	 * - If preference is set to Zoho;
	 *   - Update woocommerce order and products AFTER being pushed.
	 */
	public function pushOrder( $order_id ) {
		//TODO: Cleanup / breakdown this entire function?
		//TODO: Link orders using a meta fields on both sides?
		try {
			$order = new WC_Order( $order_id );
			if ( empty( $order->user_id ) ) {
				throw new Exception( "User id not found." );
			}
			$order_user_id = $order->user_id;
			$user_info     = get_userdata( $order_user_id );
			$items         = $order->get_items();
			$salesOrder    = array();

			Woozoho_Connector_Logger::writeDebug( "Push Order", "Syncing Zoho Order ID " . $order_id . " from (" . $user_info->user_email . "): " );

			$contact = $this->getContact( $order->get_billing_company(), $user_info->user_email );

			if ( ! $contact ) {
				Woozoho_Connector_Logger::writeDebug( "Push Order", "Contact " . $order->get_billing_company() . " (" . $user_info->user_email . ") for Order " . $order_id . " doesn't exist in Zoho. Creating contact..." );
				$contact = $this->createContact( $user_info, $order );
				if ( ! $contact ) {
					throw new Exception( "Couldn't create contact ($user_info->user_email) in Zoho." );
				} else {
					Woozoho_Connector_Logger::writeDebug( "Push Order", "Order " . $order_id . ": Contact created for " . $order->get_billing_company() . " (" . $user_info->user_email . ") in Zoho." );
				}
			} else {
				Woozoho_Connector_Logger::writeDebug( "Push Order", "Successfully found contact for " . $order->get_billing_company() . " (" . $user_info->user_email . ")." );
			}

			Woozoho_Connector_Logger::writeDebug( "Push Order", "Generating output to Zoho..." );

			$salesOrder["customer_id"]   = $contact->contact_id;
			$salesOrder["customer_name"] = $contact->company_name;

			if ( Woozoho_Connector::get_option( "testmode" ) == "yes" ) {
				Woozoho_Connector_Logger::writeDebug( "Push Order", "TEST MODE IS ENABLED, USING TEST ORDER ID's." );
				$salesOrder["salesorder_number"] = "TEST-" . $order_id;
			} else {
				Woozoho_Connector_Logger::writeDebug( "Push Order", "LIVE MODE ENABLED." );
			}


			$salesOrder["date"] = date( 'Y-m-d' );

			$salesOrder["reference_number"] = $this->getReferenceNumber( $order_id );

			$salesOrder["line_items"] = array();
			$salesOrder["status"]     = "draft";

			$missingProducts  = "";
			$inactiveProducts = "";

			$useCaching = true;

			if ( ! $this->getCache()->checkItemsCache( true ) ) {
				$useCaching = false;
			}

			//Loop through each item.
			/** @var WC_Order_Item_Product $line_item */
			foreach ( $items as $line_item ) {
				if ( $line_item->get_product() && $line_item->get_product()->get_sku() ) {
					Woozoho_Connector_Logger::writeDebug( "Push Order", "Looking for product in zoho with SKU: " . $line_item->get_product()->get_sku() );
					$zohoItem = $this->getItem( $line_item->get_product()->get_sku(), $useCaching, false );
					if ( ! $zohoItem ) { //Item not found in API or ZOHO.

						if ( Woozoho_Connector::get_option( "create_items" ) == 'yes' ) { //We can create new items...

							$zohoItem = $this->createZohoItem( $line_item );

							if ( $zohoItem !== false ) {
								$zoho_line_item = $this->itemConvert( $zohoItem, $line_item );
								array_push( $salesOrder["line_items"], $zoho_line_item );
							} else {
								$missingProducts .= $this->itemToNotes( $line_item );
								Woozoho_Connector_Logger::writeDebug( "Push Order", "Can't create zoho item with SKU (" . $line_item->get_product()->get_sku() . "). Adding to notes." );
							}

						} else { //No use notes system.
							$missingProducts .= $this->itemToNotes( $line_item );
							Woozoho_Connector_Logger::writeDebug( "Push Order", "Product (" . $line_item->get_product()->get_sku() . ") not found. Adding to notes." );
						}
					} else { //Item found in cache or live API
						if ( Woozoho_Connector::get_option( "pricing_updates" ) == "yes" ) {
							$zohoItem = $this->update_pricing( $line_item, $zohoItem, $order_id );
						}
						if ( $zohoItem->status == "active" ) { //Zoho sales orders only accepts active items.
							$zoho_line_item = $this->itemConvert( $zohoItem, $line_item );
							array_push( $salesOrder["line_items"], $zoho_line_item );
							Woozoho_Connector_Logger::writeDebug( "Push Order", "Product " . $line_item->get_product()->get_sku() . " successfully found." );
						} else {
							Woozoho_Connector_Logger::writeDebug( "Push Order", "Product " . $line_item->get_product()->get_sku() . " is inactive, added to notes." );
							$inactiveProducts .= $this->itemToNotes( $line_item );
						}
					}
				} else {
					$missingProducts .= $this->itemToNotes( $line_item );
					Woozoho_Connector_Logger::writeDebug( "Push Order", "Error: Product (" . $line_item['name'] . ") not found in WooCommerce, so we can't find SKU. Product is added as a note." );
				}
			}

			if ( empty( $salesOrder["line_items"] ) ) {
				Throw new Exception( "No items found or connected to this order." );
			}

			//Handle Notes
			if ( ! empty( $missingProducts ) ) {
				$missingProducts = "Missing products:\n" . $missingProducts . "\n";
			}

			if ( ! empty( $inactiveProducts ) ) {
				$inactiveProducts = "Inactive products:\n" . $inactiveProducts . "\n";
			}

			$orderComment = $missingProducts . $inactiveProducts . "Automatically generated by WooCommerce Zoho Connector.";

			$salesOrderOutput = $this->zohoAPI->createSalesOrder( $salesOrder, ( Woozoho_Connector::get_option( "testmode" ) == "yes" ) );

			if ( ! $salesOrderOutput->salesorder ) {
				throw new Exception( "Unable to push sales order to Zoho, invalid return." );
			}

			//Success
			$this->ordersQueue->updateOrder( $order_id, "success", "Successfully pushed to Zoho.", true );

			//Push Order Notes
			$order->add_order_note( "Successfully pushed order to Zoho. " . $salesOrderOutput->salesorder->salesorder_number . " (" . $salesOrderOutput->salesorder->customer_name . ")" );

			$order_status_setting = Woozoho_Connector::get_option( "pushed_order_status" );
			if ( $order_status_setting != $order->get_status() ) {
				$order->set_status( $order_status_setting );
			}

			//Add Payment Method As Notes;
			$paymentMethod = "Payment Method: " . $order->get_payment_method_title() . " (" . $order->get_payment_method() . ")";
			$this->zohoAPI->createComment( $salesOrderOutput->salesorder->salesorder_id, $paymentMethod );

			$this->zohoAPI->createComment( $salesOrderOutput->salesorder->salesorder_id, $orderComment ); //Adding missing / inactive products.


			Woozoho_Connector_Logger::writeDebug( "Push Order", "Successfully pushed $order_id to Zoho." );

			return true;

		} catch ( Exception $e ) {
			$this->ordersQueue->updateOrder( $order_id, "error", $e->getMessage(), true );

			Woozoho_Connector_Logger::writeDebug( "Push Order", "ERROR (Order ID: $order_id): " . $e->getMessage() );

			if ( $order ) {
				$order->add_order_note( "WooCommerce Zoho Connector Error: " . $e->getMessage() );
			}

			if ( Woozoho_Connector::get_option( "email_notifications_failed_order" ) == "yes" ) {
				$this->sendNotificationEmail( "Order $order_id failed to push to Zoho.",
					$e->getMessage() .
					"<br/>Go to order: " . get_edit_post_link( $order_id ) );
			}

			return false;
		}
	}

	public function getContact( $contact_name, $email = false ) {
		//TODO: Link contacts to users using meta field?
		$args                 = array();
		$args["contact_name"] = $contact_name;
		$data                 = $this->zohoAPI->listContacts( $args ); //Find contact by company name.
		if ( $data->contacts ) {
			$contact_id = $data->contacts[0]->contact_id;

			return $this->zohoAPI->retrieveContact( $contact_id )->contact;
		} else if ( $email ) {
			$args          = array();
			$args["email"] = $email;
			$data          = $this->zohoAPI->listContacts( $args ); //Find contact by email
			if ( $data->contacts ) {
				$contact_id = $data->contacts[0]->contact_id;

				return $this->zohoAPI->retrieveContact( $contact_id )->contact;
			} else {
				return null;
			}
		} else {
			return null;
		}
	}

	/**
	 * @param $user_info
	 * @param WC_Order $order
	 *
	 * @return null
	 */
	public function createContact( $user_info, $order ) {
		$contactData = array(
			array(
				"contact_name"     => $order->get_billing_company(),
				"company_name"     => $order->get_billing_company(),
				"website"          => $user_info->user_url,
				"email"            => $user_info->user_email,
				"notes"            => "Created by WooCommerce Zoho Connector.",
				"billing_address"  =>
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
				"shipping_address" =>
					array(
						"attention" => $order->get_shipping_company(),
						"address"   => $order->get_shipping_address_1(),
						"street2"   => $order->get_shipping_address_2(),
						"city"      => $order->get_shipping_city(),
						"state"     => $order->get_shipping_state(),
						"zip"       => $order->get_shipping_postcode(),
						"country"   => $order->get_shipping_country(),
						"phone"     => $order->get_billing_phone()
					),
				"contact_persons"  => array(
					array(
						"first_name" => $order->get_billing_first_name(),
						"last_name"  => $order->get_billing_last_name(),
						"email"      => $user_info->user_email,
						"phone"      => $order->get_billing_phone()
					)
				)
			)
		);

		$resultData = $this->zohoAPI->createContact( $contactData );

		if ( $resultData->contacts[0] ) {
			return $resultData->contacts[0];
		} else {
			return null;
		}
	}

	/** Get Item By SKU live from API or using the build-in caching.
	 *
	 * @param string $sku SKU code from product.
	 * @param bool $useCaching Use build-in caching system.
	 * @param bool $checkCaching check if caching is valid for this call.
	 *
	 * @return object|null
	 */
	public function getItem( $sku, $useCaching = true, $checkCaching = false ) {
		if ( $useCaching && ( $checkCaching ? $this->getCache()->checkItemsCache() : true ) && $this->getCache()->isEnabled() ) { //Check if caching is enabled & valid
			$item = $this->cache->getItem( $sku );
			if ( $item ) {
				return $item;
			}
		} else { //Caching not enabled or valid
			if ( ! $this->cache->checkItemsCache() && $this->cache->isEnabled() ) { //Caching not filled or not valid anymore
				$this->cache->scheduleCaching();
			}
		}

		Woozoho_Connector_Logger::writeDebug( "Zoho Client", "Looking for product $sku using API." );
		$args        = array();
		$args["sku"] = $sku;
		$data        = $this->zohoAPI->listItems( $args );
		if ( $data->items ) {
			return $data->items[0];
		} else {
			return null;
		}
	}

	/**
	 * @return Woozoho_Connector_Zoho_Cache
	 */
	public function getCache() {
		return $this->cache;
	}

	/**
	 * @param WC_Order_Item_Product $item
	 *
	 * @return string
	 */
	public function itemToNotes( $item ) {
		$returnString = "";
		$returnString .= $item['name'] . " ";
		if ( $item->get_product() && $item->get_product()->get_sku() ) {
			$returnString .= "(" . $item->get_product()->get_sku() . ") ";
		}
		$returnString .= "| Quantity: " . $item->get_quantity();
		$returnString .= " | Total Price: " . $item->get_total();
		$returnString .= "\n";

		return $returnString;
	}

	/**
	 * @param $zohoItem
	 * @param WC_Order_Item_Product|bool $storeItem
	 * @param int $quantity
	 *
	 * @return array
	 */
	public function itemConvert( $zohoItem, $storeItem, $quantity = 0 ) {
		$convertedItem = array(
			"item_id"     => $zohoItem->item_id,
			"rate"        => ( Woozoho_Connector::get_option( "pricing" ) == "zoho" || ! $storeItem ) ?
				$zohoItem->rate : $storeItem->get_total(),
			"name"        => $zohoItem->name,
			"description" => $zohoItem->description,
			"tax_id"      => $zohoItem->tax_id,
			"unit"        => $zohoItem->unit,
			"quantity"    => ( $quantity != 0 || ! $storeItem ) ?
				$quantity : $storeItem->get_quantity()
		);

		return $convertedItem;
	}

	public function scheduleOrder( $post_id, $timestamp = false ) {

		$this->ordersQueue->addOrder( $post_id );

		if ( $timestamp !== false ) {
			wp_schedule_single_event( $timestamp, 'woozoho_push_order_queue', array( $post_id ) );
		} else {
			wp_schedule_single_event( time(), 'woozoho_push_order_queue', array( $post_id ) );
		}
	}

	/**
	 * @return bool|string
	 */
	public function getLogLocation() {
		return $this->logLocation;
	}


}

