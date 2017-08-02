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

	/**
	 * @var Woozoho_Connector_Zoho_Client
	 */
	protected $cacheLocation;
	protected $apiCachingItemsTimeout;

	/**
	 * Woozoho_Connector_Zoho_Cache constructor.
	 *
	 */
	public function __construct() {
		//Load settings
		$this->apiCachingItemsTimeout = WC_Admin_Settings::get_option( "wc_zoho_connector_api_cache_items" );
		$this->cacheLocation          = WOOZOHO_CACHE_DIR;
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
		$cacheFile = WOOZOHO_CACHE_DIR . "items.json";
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
		Woozoho_Connector_Logger::writeDebug( "Zoho Cache", "Listing all cached items..." );
		$cacheFile = WOOZOHO_CACHE_DIR . "items.json";

		if ( ! is_dir( WOOZOHO_CACHE_DIR ) ) {
			mkdir( WOOZOHO_CACHE_DIR );
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
		$items      = $this->getCachedItems();
		$dataColumn = array_column( $items, 'sku' );
		$key        = array_search( $sku, $dataColumn );
		if ( $key !== false ) {
			Woozoho_Connector_Logger::writeDebug( "Zoho Cache", "Item '$sku' found at position " . $key );

			return $items[ $key ];
		} else {
			Woozoho_Connector_Logger::writeDebug( "Zoho Cache", "Item '$sku' not found in cache." );

			return false;
		}
	}

	public function getCachedItems() {
		$folderTests = plugin_dir_path( __DIR__ );
		Woozoho_Connector_Logger::writeDebug( "Zoho Cache", "Folder Test: " . $folderTests );
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
