<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://digispark.nl/
 * @since             1.0.0
 * @package           Woozoho_Connector
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce Zoho Connector
 * Plugin URI:        https://digispark.nl/woozoho/
 * Description:       Beta version of a connector to Zoho for WooCommerce Sales Orders.
 * Version:           0.1
 * Author:            DigiSpark
 * Author URI:        https://digispark.nl/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woozoho-connector
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-woozoho-connector-activator.php
 */
function activate_woozoho_connector() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woozoho-connector-activator.php';
	Woozoho_Connector_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-woozoho-connector-deactivator.php
 */
function deactivate_woozoho_connector() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woozoho-connector-deactivator.php';
	Woozoho_Connector_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_woozoho_connector' );
register_deactivation_hook( __FILE__, 'deactivate_woozoho_connector' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-woozoho-connector.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_woozoho_connector() {

	$plugin = new Woozoho_Connector();
	$plugin->run();

}
run_woozoho_connector();
