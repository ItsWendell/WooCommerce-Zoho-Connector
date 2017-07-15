<?php

/**
 * Fired during plugin activation
 *
 * @link       https://digispark.nl/
 * @since      1.0.0
 *
 * @package    Woozoho_Connector
 * @subpackage Woozoho_Connector/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Woozoho_Connector
 * @subpackage Woozoho_Connector/includes
 * @author     Wendell Misiedjan <me@digispark.nl>
 */
class Woozoho_Connector_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		self::install_db();
		self::cronjob();
	}

	private function cronjob()
	{
		if( !wp_next_scheduled( 'woozoho_sync_orders' ) ) {
			wp_schedule_event( time(), 'daily', 'mycronjob' );

		}
	}


	public function install_db()
	{
		//TODO: Save and check database version for upgrades.
		global $wpdb;
		$table_name = $wpdb->prefix . 'woozoho_orders_tracker';

		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE ".$table_name." (
            'ID' bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            'post_id' bigint(20) UNSIGNED NOT NULL,
            'status' text NOT NULL,
            'date_created' datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            'date_updated' datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            'tries' int(11) NOT NULL,
            'message' text NOT NULL,
            PRIMARY KEY ('ID'),
            KEY 'post_id' ('post_id')
			) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
		else {
			//We already have the database there.
		}
	}
}
