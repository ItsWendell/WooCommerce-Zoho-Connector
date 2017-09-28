<?php

class Woozoho_Connector_Zoho_Exporter {
	/**
	 * @var Woozoho_Connector_Zoho_Client
	 */
	protected $client;

	public function __construct() {
		$this->client = Woozoho_Connector()->get_client();
	}

	/**
	 * @param $products
	 *
	 * @return array
	 */
	public function export_products( $products ) {
		$results = array();
		foreach ( $products as $product_id ) {
			try {
				$wc_product = wc_get_product( $product_id );

				if ( $wc_product->exists() ) {
					if ( $wc_product->is_type( 'variable' ) ) {
						$available_variations = $wc_product->get_available_variations();
						foreach ( $available_variations as $key => $value ) {
							$this->export_product( $value );
							$results["exported_products"] ++;
						}
					} else {
						$this->export_product( $wc_product );
						$results["exported_products"] ++;
					}
				} else {
					Throw new Exception( "Product doesn't seem to exist." );
				}
			} catch ( Exception $e ) {
				$results["errors"] ++;
				Woozoho_Connector_Logger::write_debug( "Product Exporter", "ERROR " . $e->getCode() . ": " . $e->getMessage() . " (Product ID: $product_id)" );
				continue;
			}
		}

		return $results;
	}


	/**
	 * @param $wc_product WC_Product|WC_Product_Variable|WC_Product_Simple
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function export_product( $wc_product ) {
		try {
			$sku = $wc_product->get_sku();

			if ( empty( $sku ) ) {
				Throw new Exception( "Skipping product because SKU is empty!" );
			}

			$zoho_item = $this->client->get_item( $sku, true, false );

			if ( $zoho_item ) {
				if ( false === $this->is_product_item_equal( $wc_product, $zoho_item ) ) {
					if ( $this->client->update_item( $zoho_item->item_id,
						[
							"name"        => $wc_product->get_title(),
							"description" => $wc_product->get_short_description(),
							"rate"        => (float) $wc_product->get_price()
						] ) ) {
						return true;
					}
				}
			} else {
				$zoho_item = $this->client->create_item( $wc_product );

				if ( ! $zoho_item ) {
					Throw new Exception( "Couldn't create product." );
				} else {
					return true;
				}
			}
		} catch ( Exception $e ) {
			Woozoho_Connector_Logger::write_debug( "Product Exporter",
				"ERROR " . $e->getCode() . ": " . $e->getMessage() . " | SKU: " . $sku );

			return false;
		}
	}

	/** Checking if a WooCommerce product and a Zoho Item is equal in Name, Description & Rate.
	 *
	 * @param $wc_product WC_Product
	 * @param $zoho_item
	 *
	 * @return true|false|null
	 */
	public function is_product_item_equal( $wc_product, $zoho_item ) {
		if ( ! $wc_product || ! $zoho_item ) {
			return null;
		}

		return ( $zoho_item->name == $wc_product->get_title() &&
		         $zoho_item->description == $wc_product->get_short_description() &&
		         $zoho_item->rate == $wc_product->get_price() );

	}

}