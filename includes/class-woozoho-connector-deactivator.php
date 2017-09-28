<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://digispark.nl/
 * @since      1.0.0
 *
 * @package    Woozoho_Connector
 * @subpackage Woozoho_Connector/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Woozoho_Connector
 * @subpackage Woozoho_Connector/includes
 * @author     Wendell Misiedjan <me@digispark.nl>
 */
class Woozoho_Connector_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 * @var true|false
	 * @since    1.0.0
	 */
	public static function deactivate( $dependencies_not_met ) {
		//TODO: Remove database on deactivation?
	}

}
