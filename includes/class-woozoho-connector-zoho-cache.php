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
		Woozoho_Connector_Logger::writeDebug( "API Cache", "Checking if cache is valid." );
		/** @noinspection PhpUndefinedConstantInspection */
		$cacheFile = $this->cacheDir . "items.json";
		if ( file_exists( $cacheFile ) ) {
			$fileTime   = filectime( $cacheFile );
			$nowTime    = time();
			$expireTime = strtotime( "+ " . $this->apiCachingItemsTimeout, $fileTime );

			//Woozoho_Connector_Logger::writeDebug("API Cache","File time: $fileTime, Expire Time: $expireTime, Now Time: $nowTime, Expire Time should be bigger than now time for cache to be valid.");

			if ( $expireTime >= $nowTime ) {
				Woozoho_Connector_Logger::writeDebug( "API Cache", "Cache is still valid." );

				return true;
			} else {
				Woozoho_Connector_Logger::writeDebug( "API Cache", "Cache is outdated, removing..." );
				unlink( $fileTime ); //Removing expired cache.
				if ( $make_valid ) {
					if ( $this->cacheItems() ) {
						return true;
					}
				}

				return false;
			}
		} else {
			Woozoho_Connector_Logger::writeDebug( "API Cache", "No cache file is available." );
			if ( $make_valid ) {
				if ( $this->cacheItems() ) {
					return true;
				}
			}

			return false;
		}
	}

	public function cacheItems() {
		if ( defined( 'WOOZOHO_ITEMS_CACHING' ) ) {
			Woozoho_Connector_Logger::writeDebug( "Zoho Cache", "Already an item caching instance running, skipping..." );

			return false;
		}

		define( 'WOOZOHO_ITEMS_CACHING', true );

		Woozoho_Connector_Logger::writeDebug( "Zoho Cache", "Listing all cached items..." );
		$cacheFile = $this->cacheDir . "items.json";

		if ( ! is_dir( $this->cacheDir ) ) {
			mkdir( $this->cacheDir );
		}

		//Get all items
		$itemsCache = Woozoho_Connector()->client->getAllItems();

		if ( ! empty( $itemsCache ) ) {
			if ( file_put_contents( $cacheFile, json_encode( $itemsCache ) ) ) {
				Woozoho_Connector_Logger::writeDebug( "Zoho Cache", "Successfully wrote items to cache." );
				return true;
			} else {
				Woozoho_Connector_Logger::writeDebug( "Zoho Cache", "Error something went wrong with writing to items cache, check file permissions!" );
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
				Woozoho_Connector_Logger::writeDebug( "Zoho Cache", "Item '$sku' found in cache." );

				return $item;
			}
		}

		Woozoho_Connector_Logger::writeDebug( "Zoho Cache", "Item '$sku' not found in cache." );

		return false;
	}

	public function getCachedItems() {
		$cacheData = file_get_contents( WOOZOHO_CACHE_DIR . "items.json" );
		if ( $cacheData ) {
			$returnData = json_decode( $cacheData );
			Woozoho_Connector_Logger::writeDebug( "Zoho Cache", "Items in cache: " . count( $returnData ) );

			return $returnData;
		} else {
			return false;
		}
	}


}
