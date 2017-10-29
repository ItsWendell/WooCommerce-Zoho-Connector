<?php
/**
 * Zoho API caching system
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

class Woozoho_Connector_Zoho_Cache {

	//TODO: Add caching for contacts & taxes.
	/**
	 * @var Woozoho_Connector_Zoho_Client
	 */
	protected $cacheDir;
	protected $apiCachingItemsTimeout;
	protected $items_cache;
	protected $taxes_cache;

	/**
	 * Woozoho_Connector_Zoho_Cache constructor.
	 *
	 */
	public function __construct() {
		//Load settings
		$this->apiCachingItemsTimeout = Woozoho_Connector::get_option( "api_cache_items" );
		if ( Woozoho_Connector::get_option( "multisite_single_cache" ) == "yes" ) {
			$this->cacheDir = WOOZOHO_ABSPATH . "cache/";
		} else {
			$this->cacheDir = WOOZOHO_CACHE_DIR;
		}
	}

	public function isEnabled() {
		return ( $this->apiCachingItemsTimeout != "disabled" ) ? true : false;
	}

	public function scheduleCaching() {
		wp_schedule_single_event( time(), 'woozoho_caching' );
	}

	public function checkItemsCache( $make_valid = false ) {
		Woozoho_Connector_Logger::write_debug( "API Cache", "Checking if cache is valid." );
		/** @noinspection PhpUndefinedConstantInspection */
		$itemsFile = $this->cacheDir . "items.json";
		$taxesFile = $this->cacheDir . "taxes.json";
		if ( file_exists( $itemsFile ) && file_exists( $taxesFile ) ) {
			$fileTime   = filectime( $itemsFile );
			$nowTime    = time();
			$expireTime = strtotime( "+ " . $this->apiCachingItemsTimeout, $fileTime );

			//Woozoho_Connector_Logger::writeDebug("API Cache","File time: $fileTime, Expire Time: $expireTime, Now Time: $nowTime, Expire Time should be bigger than now time for cache to be valid.");

			if ( $expireTime >= $nowTime ) {
				Woozoho_Connector_Logger::write_debug( "API Cache", "Cache is still valid." );

				return true;
			} else {
				Woozoho_Connector_Logger::write_debug( "API Cache", "Cache is outdated, removing..." );
				unlink( $itemsFile ); //Removing expired cache.
				unlink( $taxesFile );
				if ( $make_valid ) {
					if ( $this->cacheItems() && $this->cacheItems() ) {
						return true;
					}
				}

				return false;
			}
		} else {
			Woozoho_Connector_Logger::write_debug( "API Cache", "No cache file is available." );
			if ( $make_valid ) {
				if ( $this->cacheItems() && $this->cacheTaxes() ) {
					return true;
				}
			}

			return false;
		}
	}

	public function cacheTaxes() {
		$taxes     = Woozoho_Connector()->client->get_taxes();
		$cacheFile = $this->cacheDir . "taxes.json";

		if ( ! empty( $taxes ) ) {
			if ( file_put_contents( $cacheFile, json_encode( $taxes ) ) ) {
				Woozoho_Connector_Logger::write_debug( "Zoho Cache", "Successfully wrote taxes to cache." );
				$this->taxes_cache = $taxes;

				return true;
			} else {
				Woozoho_Connector_Logger::write_debug( "Zoho Cache", "Error something went wrong with writing to taxes cache, check file permissions!" );

				return false;
			}
		} else {
			Woozoho_Connector_Logger::write_debug( "Zoho Cache", "Error, taxes are empty!" );
			unlink( $cacheFile );

			return false;
		}
	}

	public function cacheItems() {
		if ( defined( 'WOOZOHO_ITEMS_CACHING' ) ) {
			Woozoho_Connector_Logger::write_debug( "Zoho Cache", "Already an item caching instance running, skipping..." );

			return false;
		}

		define( 'WOOZOHO_ITEMS_CACHING', true );

		Woozoho_Connector_Logger::write_debug( "Zoho Cache", "Listing all cached items..." );
		$cacheFile = $this->cacheDir . "items.json";

		if ( ! is_dir( $this->cacheDir ) ) {
			mkdir( $this->cacheDir );
		}

		//Get all items
		$itemsCache = Woozoho_Connector()->client->list_all_items();

		if ( ! empty( $itemsCache ) ) {
			if ( file_put_contents( $cacheFile, json_encode( $itemsCache ) ) ) {
				Woozoho_Connector_Logger::write_debug( "Zoho Cache", "Successfully wrote items to cache." );
				$this->items_cache = $itemsCache;
				return true;
			} else {
				Woozoho_Connector_Logger::write_debug( "Zoho Cache", "Error something went wrong with writing to items cache, check file permissions!" );
				return false;
			}
		} else {
			unlink( $cacheFile );
			return false;
		}
	}

	public function getItem( $sku ) {
		$items = $this->getCachedItems();

		foreach ( $items as $item ) {
			if ( $item->sku == $sku ) {
				return $item;
			}
		}

		return false;
	}

	public function getTax( $tax_percentage, $tax_name = null ) {
		$taxes = $this->getCachedTaxes();

		Woozoho_Connector_Logger::write_debug( "Tax Cache Count", "Items: " . count( $taxes ) );
		Woozoho_Connector_Logger::write_debug( "Cache Out", print_r( $taxes, true ) );
		foreach ( $taxes as $tax ) {
			Woozoho_Connector_Logger::write_debug( "Tax Check", "Checking $tax_percentage against {$tax->tax_id} @ {$tax->tax_percentage}" );
			if ( $tax_percentage != false ) {
				if ( $tax_percentage == floatval( $tax->tax_percentage ) ) {
					Woozoho_Connector_Logger::write_debug( "Tax Check", "Match!" );

					return $tax;
				}
			}

			if ( $tax_name != null ) {
				if ( $tax->tax_name == $tax_name ) {
					return $tax;
				}
			}

			Woozoho_Connector_Logger::write_debug( "Tax Check", "No match!" );
		}


		return false;
	}

	public function getCachedItems() {
		if ( empty( $this->items_cache ) ) {
			$cacheData         = json_decode( file_get_contents( $this->cacheDir . "items.json" ) );
			$this->items_cache = $cacheData;
		} else {
			$cacheData = $this->items_cache;
		}

		if ( $cacheData ) {
			$returnData = $cacheData;
			return $returnData;
		} else {
			return false;
		}
	}

	public function getCachedTaxes() {
		$cacheData = array();

		$cacheData         = json_decode( file_get_contents( $this->cacheDir . "taxes.json" ) );
		$this->taxes_cache = $cacheData;


		if ( $cacheData ) {
			$returnData = $cacheData;
			return $returnData;
		} else {
			return false;
		}
	}


}
