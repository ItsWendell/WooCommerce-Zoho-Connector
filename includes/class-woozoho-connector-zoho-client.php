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

	protected $orders_queue;
	protected $cache;
	protected $zoho_api;

	public function __construct() {
		//API Settings
		$args                   = array();
		$args["accessToken"]    = Woozoho_Connector::get_option( "token" );
		$args["organizationId"] = Woozoho_Connector::get_option( "organisation_id" );

		//Modules
		$this->zoho_api     = new Woozoho_Connector_Zoho_API( $args );
		$this->orders_queue = new Woozoho_Connector_Orders_Queue();
		$this->cache        = new Woozoho_Connector_Zoho_Cache();
	}

	/**
	 * @return Woozoho_Connector_Orders_Queue
	 */
	public function get_orders_queue() {
		return $this->orders_queue;
	}

	/**
	 * @return Woozoho_Connector_Zoho_API
	 */
	public function get_api() {
		return $this->zoho_api;
	}

	public function list_all_items() {
		$returnData = array();
		$next_page  = 1;

		while ( $next_page ) {
			Woozoho_Connector_Logger::write_debug( "Zoho Client", "Getting all items... current page: " . $next_page );
			$args         = array();
			$args["page"] = $next_page;

			$resultData = $this->zoho_api->listItems( $args );
			//TODO: Catch API return errors, retry certain times.
			$hasNextPage = $resultData->page_context->has_more_page;
			if ( $resultData->items ) {
				foreach ( $resultData->items as $item ) {
					$returnData[] = $item;
				}
			}

			Woozoho_Connector_Logger::write_debug( "Zoho Client", "Current item count " . count( $returnData ) );

			if ( $hasNextPage ) {
				$next_page ++;
			} else {
				break;
			}
		}

		return $returnData;
	}

	public function get_taxes() {
		Woozoho_Connector_Logger::write_debug( "Zoho Client", "Getting Taxes..." );
		$api_result = $this->zoho_api->listTaxes();
		//TODO: Add try / catch for failures like no permissions and disable caching if this happens and notify users.
		if ( count( $api_result->taxes ) != 0 ) {
			return $api_result->taxes;
		}
		return false;
	}

	public function send_notification_email( $subject, $message ) {
		$mailTo = Woozoho_Connector::get_option( "notify_email" );
		if ( $mailTo ) {
			if ( ! is_multisite() ) {
				$headers[] = 'From: Zoho Connector <' . get_option( 'admin_email' ) . '>';
			} else {
				$headers[] = 'From: Zoho Connector ' . get_bloginfo( 'name' ) . '<' . get_option( 'admin_email' ) . '>';
			}
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			wp_mail( $mailTo, "WooCommerce Zoho Connector:" . $subject, $message, $headers );
			Woozoho_Connector_Logger::write_debug( "Notification Email", "Email with subject '" . $subject . " sent to " . $mailTo );
		}
	}

	public function process_orders_queue() {
		//TODO: remove? Function not being used?
		$ordersData = $this->orders_queue->getQueue();
		foreach ( $ordersData as $order_id ) {
			$this->create_sales_order( $order_id );
		}
	}

	public function generate_reference_number( $order_id ) {
		$order = new WC_Order( $order_id );

		$order_number = trim( str_replace( '#', '', $order->get_order_number() ) );

		$rawString = Woozoho_Connector::get_option( "reference_number_format" );

		$refVars = array(
			'post_id'      => $order_id,
			'order_number' => $order_number,
			'blog_id'      => get_current_blog_id()
		);

		$result = preg_replace_callback(
			'/\%(.*?)\%/',
			function ( $match ) use ( $refVars ) {
				return str_replace( $match[0], isset( $refVars[ $match[1] ] ) ? $refVars[ $match[1] ] : $match[0], $match[0] );
			},
			$rawString );

		Woozoho_Connector_Logger::write_debug( "Reference Number", $result );

		return $result;
	}


	/**
	 * @param WC_Order_Item_Product $item
	 *
	 * Required API calls: 2.
	 *
	 * @return bool|object
	 */
	public function create_item( $item ) {
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
			"tax_id"       => 200451000001689003,
			//TODO: Handle BTW.
			"product_type" => "goods"
		];

		$apiResult = $this->zoho_api->createItem( $pushData );

		if ( $apiResult->code === 0 ) {
			Woozoho_Connector_Logger::write_debug( "Zoho Client", "Successfully created product in Zoho: " . $item->get_name() . " (" . $item->get_product()->get_sku() . ")" );

			return $apiResult->item;
		} else {
			Woozoho_Connector_Logger::write_debug( "Zoho Client", "Something went wrong while creating product: " . $item->get_name() );
			return false;
		}
	}

	public function get_shipping_lineitem( $shipping_cost, $shipping_tax = false ) {
		$item_sku_option = Woozoho_Connector::get_option( "shipping_invoice_item_sku" );
		$item_sku        = ! empty( $item_sku_option ) ? $item_sku_option : "SHIPPING-COST";
		$item            = $this->get_item( $item_sku );

		if ( ! $item ) {
			$params = [
				"name"         => 'Shipping Costs',
				"rate"         => '0',
				"description"  => 'Item for shipping costs.',
				"sku"          => $item_sku,
				"tax_id"       => 200451000001689003,
				"product_type" => "service"
			];

			$api_result = $this->zoho_api->createItem( $params );

			if ( $api_result->code === 0 ) {
				Woozoho_Connector_Logger::write_debug( "Zoho Client", "Successfully created shipping costs product." );
				$item = $api_result->item;

			} else {
				Woozoho_Connector_Logger::write_debug( "Zoho Client", "Something went wrong while creating product for Shipping Costs (SHIPPING-COSTS)" );

				return false;
			}
		}

		$converted_item = array(
			"item_id"     => $item->item_id,
			"rate"        => $shipping_cost,
			"name"        => $item->name,
			"description" => $item->description,
			"tax_id"      => 200451000001689003,
			"unit"        => "unit",
			"quantity"    => 1,
		);

		//TODO: Fix taxes on every level instead of hardcoded tax

		return $converted_item;
	}

	/**
	 * @var WC_Order_Item_Product $line_item
	 * @var $zoho_item
	 * @var string|bool $preference
	 *
	 * Returns updated line_item
	 * @return bool|object
	 */

	//TODO: WARNING: Make sure your Tax names are the same in WooCommerce as in Zoho! DO NOT CHANGE YOUR TAX ITEMS IN ZOHO BOOKS. THIS MIGHT CAUSE CUNFUSION.

	public function tax_fixer() {
		/*
		if ( $this->cache->cacheItems() ) {
			$wc_products = new WP_Query( array(
				'post_type'      => array( 'product', 'product_variation' ),
				'posts_per_page' => - 1
			) );
			$zoho_products = $this->cache->getCachedItems();

			Woozoho_Connector_Logger::writeDebug( "Tax Fixer", "Fixing Tax ID's  of " . $wc_products->post_count . " products.");

			foreach($zoho_products as $zoho_item)
			{
				$this->zohoAPI->updateItem($zoho_item->item_id,array(''))
				if($zoho_item->tax_id != "200451000001689003")
				{
					Woozoho_Connector_Logger::writeDebug( "Tax Fixer", "Fixing $zoho_item->sku: ");
				}
			}
		}
		*/

		return false;
	}

	public function sku_checker() {
		$preference    = Woozoho_Connector::get_option( "pricing" );
		$updated_items = 0;
		$conflicts     = 0;

		if ( $this->cache->cacheItems() ) {
			$wc_products = new WP_Query( array(
				'post_type'      => array( 'product', 'product_variation' ),
				'posts_per_page' => - 1
			) );

			Woozoho_Connector_Logger::write_debug( "SKU Checker", "Checking SKU's of " . $wc_products->post_count . " products, pricing preference is set to " . $preference );

			while ( $wc_products->have_posts() ) : $wc_products->the_post();
				$pid        = get_the_ID();
				$wc_product = wc_get_product( $pid );
				if ( ! $wc_product ) {
					$conflicts ++;
					Woozoho_Connector_Logger::write_debug( "SKU Checker", "Product " . get_the_title() . " ($pid): is invalid or broken." );
					continue;
				}
				$sku = $wc_product->get_sku();

				if ( empty( $sku ) ) {
					$conflicts ++;
					Woozoho_Connector_Logger::write_debug( "SKU Checker", "Product " . get_the_title() . " ($pid): has no SKU." );
					continue;
				}

				$zoho_product = $this->cache->getItem( $sku );

				if ( ! $zoho_product ) {
					$conflicts ++;
					Woozoho_Connector_Logger::write_debug( "SKU Checker", "Product SKU: $sku (" . get_the_title() . " - $pid): Not found in Zoho." );
					continue;
				}

				$price_zoho        = (float) $zoho_product->rate;
				$price_woocommerce = (float) $wc_product->get_regular_price();

				if ( $price_zoho == $price_woocommerce ) {
					Woozoho_Connector_Logger::write_debug( "SKU Checker", "Product SKU: $sku (" . get_the_title() . " - $pid): Product found in Zoho. Prices are the same! Great :)" );
					continue;
				}

				Woozoho_Connector_Logger::write_debug( "SKU Checker", "Product SKU: $sku (" . get_the_title() . " - $pid): Product found in Zoho. Price Difference: (Zoho) $price_zoho | (WooCommerce) $price_woocommerce" );


			endwhile;
			Woozoho_Connector_Logger::write_debug( "SKU Checker", "SKU CHECKER FINISHED :)" );
		}
	}

	public function sync_prices() {
		try {

			//TODO: Take care of API limit of 100 calls / minute.
			$preference    = Woozoho_Connector::get_option( "pricing" );
			$updated_items = 0;
			$conflicts     = 0;

			if ( $this->cache->cacheItems() ) {
				$wc_products = new WP_Query( array(
					'post_type'      => array( 'product', 'product_variation' ),
					'posts_per_page' => - 1
				) );

				Woozoho_Connector_Logger::write_debug( "Price Sync", "Syncing prices of " . $wc_products->post_count . " products, pricing preference is set to " . $preference );

				while ( $wc_products->have_posts() ) : $wc_products->the_post();
					$pid        = get_the_ID();
					$wc_product = wc_get_product( $pid );
					if ( ! $wc_product ) {
						$conflicts ++;
						Woozoho_Connector_Logger::write_debug( "Price Sync", "Product " . get_the_title() . " ($pid) is invalid or broken." );
						continue;
					}
					$sku = $wc_product->get_sku();

					if ( empty( $sku ) ) {
						$conflicts ++;
						Woozoho_Connector_Logger::write_debug( "Price Sync", "Product " . $wc_product->get_name() . " ($pid) has no SKU." );
						continue;
					}

					$zoho_product = $this->cache->getItem( $sku );

					if ( ! $zoho_product ) {
						$conflicts ++;
						Woozoho_Connector_Logger::write_debug( "Price Sync", "Product SKU $sku not found in cache." );
						continue;
					}

					$price_zoho        = (float) $zoho_product->rate;
					$price_woocommerce = (float) $wc_product->get_regular_price();

					if ( $price_zoho == $price_woocommerce ) {
						Woozoho_Connector_Logger::write_debug( "Price Sync", "No price updated needed for $sku" );
						continue;
					}

					switch ( $preference ) {
						case "zoho": {
							$wc_product->set_regular_price( $price_zoho );
							if ( ! $wc_product->is_on_sale() ) {
								$wc_product->set_price( $price_zoho );
							}
							$wc_product->save();
							$wc_product->save_meta_data();
							Woozoho_Connector_Logger::write_debug( "Price Sync", "Successfully updated price of $sku in WooCommerce, $price_woocommerce -> $price_zoho" );
							$updated_items ++;
							continue;
						}

						case "woocommerce": {
							$update = $this->update_item( $zoho_product->item_id, [ "rate" => $price_woocommerce ] );
							if ( $update->code === 0 ) {
								Woozoho_Connector_Logger::write_debug( "Price Sync", "Successfully updated price of $sku in Zoho, $price_zoho -> $price_woocommerce" );
								$updated_items ++;
							} else {

							}
						}
					}

					Woozoho_Connector_Logger::write_debug( "Price Sync", "Updated the price of $updated_items with $conflicts conflicts." );
				endwhile;
			} else {
				Throw new Exception( "Something went wrong with the cache." );
			}
		} catch ( Exception $e ) {
			Woozoho_Connector_Logger::write_debug( "Price Sync", "ERROR: " . $e->getMessage() );
		}
	}

	/**
	 * @param WC_order_Item_Product $line_item
	 * @param $zoho_item
	 * @param $order_id
	 * @param bool $preference
	 *
	 * @return mixed
	 */
	public function update_pricing( $line_item, $zoho_item, $order_id, $preference = false ) {
		if ( empty( $line_item ) || empty( $zoho_item ) ) {
			Woozoho_Connector_Logger::write_debug( "Price Updater", "Skipping updates, WooCommerce or Zoho Item is empty." );

			return $zoho_item;
		}

		if ( $preference == false ) {
			$preference = Woozoho_Connector::get_option( "pricing" );
		}

		$price_woocommerce = (float) $line_item->get_product()->get_regular_price();
		$price_zoho        = (float) $zoho_item->rate;
		$sku               = $line_item->get_product()->get_sku();

		Woozoho_Connector_Logger::write_debug( "Price Updater", "Pricing preference: $preference, WooCommerce: $price_woocommerce - Zoho: $price_zoho" );

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


				Woozoho_Connector_Logger::write_debug( "Price Updater", "Updated WooCommerce pricing to Zoho." );

				if ( Woozoho_Connector::get_option( "email_notifications_price_changes" ) == "yes" ) {
					$this->send_notification_email( "Price update in WooCommerce: $sku - $price_woocommerce -> $price_zoho",
						"Price update in WooCommerce: $sku - $price_woocommerce -> $price_zoho" );
				}

				return $zoho_item;
			} else if ( $preference == "woocommerce" ) {
				$update = $this->update_item( $zoho_item->item_id, [ "rate" => $price_woocommerce ] );
				if ( $update->code === 0 ) {
					Woozoho_Connector_Logger::write_debug( "Price Updater", "Updated Zoho pricing to WooCommerce." );

					if ( Woozoho_Connector::get_option( "email_notifications_price_changes" ) == "yes" ) {
						$this->send_notification_email( "Price update in Zoho: $sku - $price_zoho -> $price_woocommerce",
							"Price update in Zoho: $sku - $price_zoho -> $price_woocommerce" );
					}

					return $update->item;
				} else {
					Woozoho_Connector_Logger::write_debug( "Price Updater", "Ooops something went wrong: " . $update->message );

					return $zoho_item;
				}
			}
		} else {
			Woozoho_Connector_Logger::write_debug( "Price Updater", "Prices are equal, no update needed." );

			return $zoho_item;
		}

		return false;
	}

	public function update_item( $item_id, $changes ) {
		return $this->zoho_api->updateItem( $item_id, $changes );
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
	public function create_sales_order( $order_id ) {
		//TODO: Cleanup / breakdown this entire function?
		//TODO: Link orders using a meta fields on both sides?
		//TODO: Add support for guests 'no user accounts'.
		try {
			$order = new WC_Order( $order_id );

			if ( empty( $order->user_id ) ) {
				throw new Exception( "User id not found." );
			}
			$order_user_id = $order->user_id;
			$user_info     = get_userdata( $order_user_id );
			$items         = $order->get_items();
			$salesOrder    = array();

			Woozoho_Connector_Logger::write_debug( "Push Order", "Syncing Zoho Order ID " . $order_id . " from (" . $user_info->user_email . "): " );

			$contact = $this->get_contact( $user_info->user_email, $order->get_billing_company() );

			if ( ! $contact ) {
				Woozoho_Connector_Logger::write_debug( "Push Order", "Contact " . $order->get_billing_company() . " (" . $user_info->user_email . ") for Order " . $order_id . " doesn't exist in Zoho. Creating contact..." );
				$contact = $this->create_contact( $user_info, $order );
				if ( ! $contact ) {
					throw new Exception( "Couldn't create contact ($user_info->user_email) in Zoho." );
				} else {
					Woozoho_Connector_Logger::write_debug( "Push Order", "Order " . $order_id . ": Contact created for " . $order->get_billing_company() . " (" . $user_info->user_email . ") in Zoho." );
				}
			} else {
				Woozoho_Connector_Logger::write_debug( "Push Order", "Successfully found contact for " . $order->get_billing_company() . " (" . $user_info->user_email . ")." );
			}

			Woozoho_Connector_Logger::write_debug( "Push Order", "Generating output to Zoho..." );

			$salesOrder["customer_id"]   = $contact->contact_id;
			$salesOrder["customer_name"] = $contact->company_name;

			if ( Woozoho_Connector::get_option( "testmode" ) == "yes" ) {
				Woozoho_Connector_Logger::write_debug( "Push Order", "TEST MODE IS ENABLED, USING TEST ORDER ID's." );
				$salesOrder["salesorder_number"] = "TEST-" . $order_id;
			} else {
				Woozoho_Connector_Logger::write_debug( "Push Order", "LIVE MODE ENABLED." );
			}


			$salesOrder["date"] = date( 'Y-m-d' );

			$salesOrder["reference_number"] = $this->generate_reference_number( $order_id );

			$salesOrder["line_items"] = array();
			$salesOrder["status"]     = "draft";

			$missingProducts  = "";
			$inactiveProducts = "";

			$useCaching = true;

			if ( ! $this->get_cache()->checkItemsCache( true ) ) {
				$useCaching = false;
			}

			//Loop through each item.
			/** @var WC_Order_Item_Product $line_item */
			foreach ( $items as $line_item ) {
				if ( $line_item->get_product() && $line_item->get_product()->get_sku() ) {
					Woozoho_Connector_Logger::write_debug( "Push Order", "Looking for product in zoho with SKU: " . $line_item->get_product()->get_sku() );
					$zohoItem = $this->get_item( $line_item->get_product()->get_sku(), $useCaching, false );
					if ( ! $zohoItem ) { //Item not found in API or ZOHO.

						if ( Woozoho_Connector::get_option( "create_items" ) == 'yes' ) { //We can create new items...

							$zohoItem = $this->create_item( $line_item );

							if ( $zohoItem !== false ) {
								$zoho_line_item = $this->convert_item( $zohoItem, $line_item );
								array_push( $salesOrder["line_items"], $zoho_line_item );
							} else {
								$missingProducts .= $this->convert_item_to_notes( $line_item );
								Woozoho_Connector_Logger::write_debug( "Push Order", "Can't create zoho item with SKU (" . $line_item->get_product()->get_sku() . "). Adding to notes." );
							}

						} else { //No use notes system.
							$missingProducts .= $this->convert_item_to_notes( $line_item );
							Woozoho_Connector_Logger::write_debug( "Push Order", "Product (" . $line_item->get_product()->get_sku() . ") not found. Adding to notes." );
						}
					} else { //Item found in cache or live API
						if ( Woozoho_Connector::get_option( "pricing_updates" ) == "yes" ) {
							$zohoItem = $this->update_pricing( $line_item, $zohoItem, $order_id );
						}
						if ( $zohoItem->status == "active" ) { //Zoho sales orders only accepts active items.
							$zoho_line_item = $this->convert_item( $zohoItem, $line_item );
							array_push( $salesOrder["line_items"], $zoho_line_item );
							Woozoho_Connector_Logger::write_debug( "Push Order", "Product " . $line_item->get_product()->get_sku() . " successfully found." );
						} else {
							Woozoho_Connector_Logger::write_debug( "Push Order", "Product " . $line_item->get_product()->get_sku() . " is inactive, added to notes." );
							$inactiveProducts .= $this->convert_item_to_notes( $line_item );
						}
					}
				} else {
					$missingProducts .= $this->convert_item_to_notes( $line_item );
					Woozoho_Connector_Logger::write_debug( "Push Order", "Error: Product (" . $line_item['name'] . ") not found in WooCommerce, so we can't find SKU. Product is added as a note." );
				}
			}

			if ( empty( $salesOrder["line_items"] ) ) {
				$placeholder_sku = Woozoho_Connector::get_option( "item_placeholder" );
				if ( ! empty( $placeholder_sku ) ) {
					$item = $this->get_item( $placeholder_sku );
					if ( $item ) {
						$line_item                  = $this->convert_item( $item, false );
						$salesOrder["line_items"][] = $line_item;

					} else {
						Throw new Exception( "Placeholder item with SKU $placeholder_sku is not found." );
					}
				} else {
					Throw new Exception( "No items found or connected to this order." );
				}
			}

			//Shipping
			if ( $order->get_shipping_total() ) {

				$shipping_total          = $order->get_shipping_total();
				$shipping_tax            = $order->get_shipping_tax();
				$shipping_invoice_method = Woozoho_Connector::get_option( "shipping_invoice_method" );

				Woozoho_Connector_Logger::write_debug( "Push Order",
					"Shipping Total: " . $shipping_total . " - Shipping Tax: " . $order->get_shipping_tax() . " Total: " .
					$salesOrder["shipping_charge"] . "Invoice Method: " . $shipping_invoice_method );

				if ( $shipping_invoice_method == 'line-item' ) {
					$shipping_line_item = $this->get_shipping_lineitem( $shipping_total );
					if ( ! $shipping_line_item ) {
						$missingProducts .= "Something went wrong with processing shipping costs!";
						Woozoho_Connector_Logger::write_debug( "Push Order", "Something went wrong with processing shipping costs!" );
					} else {
						array_push( $salesOrder["line_items"], $shipping_line_item );
						Woozoho_Connector_Logger::write_debug( "Push Order", "Successfully added shipping costs as a line_item!" );
					}
				} else {
					$salesOrder["shipping_charge"] = (float) $shipping_total + (float) $shipping_tax;
				}
			}

			//Handle Notes
			if ( ! empty( $missingProducts ) ) {
				$missingProducts = "Missing products:\n" . $missingProducts . "\n";
			}

			if ( ! empty( $inactiveProducts ) ) {
				$inactiveProducts = "Inactive products:\n" . $inactiveProducts . "\n";
			}

			$orderComment = $missingProducts . $inactiveProducts . "Automatically generated by WooCommerce Zoho Connector.";

			$salesOrderOutput = $this->zoho_api->createSalesOrder( $salesOrder, ( Woozoho_Connector::get_option( "testmode" ) == "yes" ) );

			if ( ! $salesOrderOutput->salesorder ) {
				throw new Exception( "Unable to push sales order to Zoho, invalid return." );
			}

			//Success
			$this->orders_queue->updateOrder( $order_id, "success", "Successfully pushed to Zoho.", true );

			//Push Order Notes
			$order->add_order_note( "Successfully pushed order to Zoho. " . $salesOrderOutput->salesorder->salesorder_number . " (" . $salesOrderOutput->salesorder->customer_name . ")" );

			$order_status_setting = Woozoho_Connector::get_option( "pushed_order_status" );
			if ( $order_status_setting != $order->get_status() ) {
				$order->update_status( $order_status_setting );
			}

			//Add Payment Method As Notes;
			$paymentMethod = "Payment Method: " . $order->get_payment_method_title() . " (" . $order->get_payment_method() . ")";
			$this->zoho_api->createComment( $salesOrderOutput->salesorder->salesorder_id, $paymentMethod );

			$this->zoho_api->createComment( $salesOrderOutput->salesorder->salesorder_id, $orderComment ); //Adding missing / inactive products.


			Woozoho_Connector_Logger::write_debug( "Push Order", "Successfully pushed $order_id to Zoho." );

			return true;

		} catch ( Exception $e ) {
			$this->orders_queue->updateOrder( $order_id, "error", $e->getMessage(), true );

			Woozoho_Connector_Logger::write_debug( "Push Order", "ERROR (Order ID: $order_id): " . $e->getMessage() );

			if ( $order ) {
				$order->add_order_note( "WooCommerce Zoho Connector Error: " . $e->getMessage() );
			}

			if ( Woozoho_Connector::get_option( "email_notifications_failed_order" ) == "yes" ) {
				$this->send_notification_email( "Order $order_id failed to push to Zoho.",
					$e->getMessage() .
					"<br/>Go to order: " . get_edit_post_link( $order_id ) );
			}

			return false;
		}
	}

	public function get_contact( $contact_email, $company_name = false ) {
		//TODO: Link contacts to users using meta field?
		//TODO: Rewrite to input actual WooCommerce contact
		//TODO: Support company_name as search value.
		//TODO: Catch inactive contacts. (not supported as input for new orders)
		Woozoho_Connector_Logger::write_debug( "Get Contact", "Point 1" );
		if ( ! empty( $contact_email ) ) {
			Woozoho_Connector_Logger::write_debug( "Get Contact", "Point 2" );
			$args          = array();
			$args["email"] = $contact_email;
			$data          = $this->zoho_api->list_contacts( $args ); //Find contact by email
			Woozoho_Connector_Logger::write_debug( "Get Contact", "Point 3" );
			if ( $data->contacts ) {
				Woozoho_Connector_Logger::write_debug( "Get Contact", "Point 4" );
				$contact_id = $data->contacts[0]->contact_id;
				if ( ! empty( $contact_id ) ) {
					Woozoho_Connector_Logger::write_debug( "Get Contact", "Point 5" );

					return $this->zoho_api->get_contact( $contact_id )->contact;
				}
			}
		}

		Woozoho_Connector_Logger::write_debug( "Get Contact", "Point 6" );

		if ( ! empty( $company_name ) ) {
			$args                 = array();
			$args["company_name"] = $company_name;
			$data                 = $this->zoho_api->list_contacts( $args ); //Find contact by company name.

			if ( $data->contacts ) {
				$contact_id = $data->contacts[0]->contact_id;
				if ( ! empty( $contact_id ) ) {
					return $this->zoho_api->get_contact( $contact_id )->contact;
				}
			}
		}

		return null;
	}

	/**
	 * @param $user_info
	 * @param WC_Order $order
	 *
	 * @return null
	 */
	public function create_contact( $user_info, $order ) {
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
						"first_name"         => $order->get_billing_first_name(),
						"last_name"          => $order->get_billing_last_name(),
						"email"              => $user_info->user_email,
						"phone"              => $order->get_billing_phone(),
						"is_primary_contact" => true
					)
				)
			)
		);

		$resultData = $this->zoho_api->create_contact( $contactData );

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
	public function get_item( $sku, $useCaching = true, $checkCaching = false ) {
		if ( $useCaching && ( $checkCaching ? $this->get_cache()->checkItemsCache() : true ) && $this->get_cache()->isEnabled() ) { //Check if caching is enabled & valid
			$item = $this->cache->getItem( $sku );
			if ( $item ) {
				Woozoho_Connector_Logger::write_debug( "Tax Checker", "Tax ID: " . $item->tax_id . " - tax_name: " . $item->tax_name . " - tax_percentage: " . $item->tax_percentage . " - tax_type: " . $item->tax_type );
				return $item;
			}
		} else { //Caching not enabled or valid
			if ( ! $this->cache->checkItemsCache() && $this->cache->isEnabled() ) { //Caching not filled or not valid anymore
				$this->cache->scheduleCaching();
			}
		}

		Woozoho_Connector_Logger::write_debug( "Zoho Client", "Looking for product $sku using API." );
		$args        = array();
		$args["sku"] = $sku;
		$data        = $this->zoho_api->listItems( $args );
		if ( $data->items ) {
			return $data->items[0];
		} else {
			return null;
		}
	}

	public function get_tax( $tax_percentage, $tax_name = false, $useCaching = true, $checkCaching = false ) {
		$tax_percentage = (int) $tax_percentage;
		Woozoho_Connector_Logger::write_debug( "Get Tax", "Input data: Percentage: " . $tax_percentage . " Tax Name: " . $tax_name );
		if ( $useCaching && ( $checkCaching ? $this->get_cache()->checkItemsCache() : true ) && $this->get_cache()->isEnabled() ) { //Check if caching is enabled & valid

			Woozoho_Connector_Logger::write_debug( "Get Tax", "Data 1: Percentage: " . $tax_percentage . " Name" . $tax_name );
			$item = $this->cache->getTax( $tax_percentage, $tax_name );
			if ( $item ) {
				return $item;
			}
		} else { //Caching not enabled or valid
			if ( ! $this->cache->checkItemsCache() && $this->cache->isEnabled() ) { //Caching not filled or not valid anymore
				$this->cache->scheduleCaching();
			}
		}

		return false;
	}

	/**
	 * @return Woozoho_Connector_Zoho_Cache
	 */
	public function get_cache() {
		return $this->cache;
	}

	/**
	 * @param WC_Order_Item_Product $item
	 *
	 * @return string
	 */
	public function convert_item_to_notes( $item ) {
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
	public function convert_item( $zohoItem, $storeItem, $quantity = 0 ) {
		//Get right tax ID
		Woozoho_Connector_Logger::write_debug( "Tax Fix?", "Class: " . $storeItem->get_tax_class() );
		Woozoho_Connector_Logger::write_debug( "Tax class output", print_r( $storeItem->get_tax_class(), true ) );
		Woozoho_Connector_Logger::write_debug( "Taxes output", print_r( $storeItem->get_taxes(), true ) );
		$tax = $this->get_tax( $zohoItem->tax_percentage );

		if ( $tax ) {
			Woozoho_Connector_Logger::write_debug( "Tax Fix?", "Tax found in cache by percentage." );
			$tax_id = $tax->tax_id;
		} else {
			$tax_id = $zohoItem->tax_id;
			Woozoho_Connector_Logger::write_debug( "Tax Fix?", "Tax not found, using original: " . $tax_id );
		}

		$convertedItem = array(
			"item_id"     => $zohoItem->item_id,
			"rate"        => ( Woozoho_Connector::get_option( "pricing" ) == "zoho" || ! $storeItem ) ?
				$zohoItem->rate : $storeItem->get_total(),
			"name"        => $zohoItem->name,
			"description" => $zohoItem->description,
			"tax_id"      => 200451000001689003,
			"unit"        => $zohoItem->unit,
			"quantity"    => ( $quantity != 0 || ! $storeItem ) ?
				$quantity : $storeItem->get_quantity()
		);

		return $convertedItem;
	}

	public function schedule_order( $post_id, $timestamp = false ) {

		$this->orders_queue->addOrder( $post_id );

		if ( $timestamp !== false ) {
			wp_schedule_single_event( $timestamp, 'woozoho_push_order_queue', array( $post_id ) );
		} else {
			wp_schedule_single_event( time(), 'woozoho_push_order_queue', array( $post_id ) );
		}
	}

	public function schedule_sync_prices( $timestamp = false ) {


		if ( $timestamp !== false ) {
			wp_schedule_single_event( $timestamp, 'woozoho_sync_prices' );
		} else {
			wp_schedule_single_event( time(), 'woozoho_sync_prices' );
		}

		Woozoho_Connector_Logger::write_debug( "Price Sync", "Scheduled price sync." );
	}

	public function schedule_sku_checker( $timestamp = false ) {
		if ( $timestamp !== false ) {
			wp_schedule_single_event( $timestamp, 'woozoho_sku_checker' );
		} else {
			wp_schedule_single_event( time(), 'woozoho_sku_checker' );
		}

		Woozoho_Connector_Logger::write_debug( "SKU Checker", "Scheduled SKU checker." );

	}
}

