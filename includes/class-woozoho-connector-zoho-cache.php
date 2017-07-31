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
	protected $client;
	protected $cacheLocation;
	protected $apiCachingItemsTimeout;

	/**
	 * Woozoho_Connector_Zoho_Cache constructor.
	 *
	 */
	public function __construct() {
		global $woozoho_connector;
		//Load settings
		$this->apiCachingItemsTimeout = WC_Admin_Settings::get_option( "wc_zoho_connector_api_cache_items" );
		$this->cacheLocation          = $client->getLogLocation() . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
		$woozoho_connector->
		$this->client                 = $client;
	}

	public function isEnabled() {
		return ( $this->apiCachingItemsTimeout != "disabled" ) ? true : false;
	}

	public function scheduleCaching() {
		wp_schedule_single_event( time(), 'woozoho_caching' );
	}

	public function cacheItems() {
		$this->client->writeDebug( "Zoho Cache", "Listing all cached items..." );
		$filename = $this->cacheLocation . "items.json";
		$folder   = dirname( $filename );

		if ( ! is_dir( $folder ) ) {
			mkdir( $folder );
		}

		//Get all items
		$itemsCache = $this->client->getAllItems();

		if ( ! empty( $itemsCache ) ) {
			if ( file_put_contents( $filename, json_encode( $itemsCache ) ) ) {
				$this->client->writeDebug( "Zoho Cache", "Successfully wrote items to cache." );

				return true;
			} else {
				$this->client->writeDebug( "Zoho Cache", "Error something went wrong with writing to items cache, check file permissions!" );

				return false;
			}
		} else {
			unlink( $filename );

			return false;
		}
	}

	public function checkItemsCache( $make_valid = false ) {
		$this->client->writeDebug( "API Cache", "Checking if cache is valid." );
		$fileName = $this->cacheLocation . "items.json";
		if ( file_exists( $fileName ) ) {
			$fileTime   = filectime( $fileName );
			$nowTime    = time();
			$expireTime = strtotime( "+ " . $this->apiCachingItemsTimeout, $fileTime );

			//$this->client->writeDebug("API Cache","File time: $fileTime, Expire Time: $expireTime, Now Time: $nowTime, Expire Time should be bigger than now time for cache to be valid.");

			if ( $expireTime >= $nowTime ) {
				$this->client->writeDebug( "API Cache", "Cache is still valid." );

				return true;
			} else {
				$this->client->writeDebug( "API Cache", "Cache is outdated, removing..." );
				unlink( $fileTime ); //Removing expired cache.
				if ( $make_valid ) {
					if ( $this->cacheItems() ) {
						return true;
					}
				}

				return false;
			}
		} else {
			$this->client->writeDebug( "API Cache", "No cache file is available." );
			if ( $make_valid ) {
				if ( $this->cacheItems() ) {
					return true;
				}
			}

			return false;
		}
	}

	public function getItem( $sku ) {
		$items      = $this->getCachedItems();
		$dataColumn = array_column( $items, 'sku' );
		$key        = array_search( $sku, $dataColumn );
		if ( $key !== false ) {
			$this->client->writeDebug( "Zoho Cache", "Found SKU '$sku' in item cache at " . $key );

			return $items[ $key ];
		} else {
			return false;
		}
	}

	public function getCachedItems() {
		$folderTests = plugin_dir_path( __DIR__ );
		$this->client->writeDebug( "Zoho Cache", "Folder Test: " . $folderTests );
		$cacheData = file_get_contents( $this->cacheLocation . "items.json" );
		if ( $cacheData ) {
			$returnData = json_decode( $cacheData );
			$this->client->writeDebug( "Zoho Cache", "Items in cache: " . count( $returnData ) );

			return $returnData;
		} else {
			return false;
		}
	}


}
