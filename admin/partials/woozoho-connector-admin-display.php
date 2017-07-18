<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://digispark.nl/
 * @since      1.0.0
 *
 * @package    Woozoho_Connector
 * @subpackage Woozoho_Connector/admin/partials
 */

/*

Initial settings for the admin page
 - woozoho_api_key
 - woozoho_organisation_id
 - woozoho_enable_logs

Possible later settings
 - woozoho_live_sync
 - woozoho_sync_period - how often syncing needs to happen.
*/
?>

    <div class="wrap">

        <h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

        <form method="post" name="woozoho-connector_options" action="options.php">
			<?php
			$options = get_option( $this->plugin_name );
			settings_fields( $this->plugin_name );
			?>

            <!-- remove some meta and generators from the <head> -->
            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row"><label for="enable_logging">Enable Logging</label></th>
                    <td><input type="checkbox" id="<?php echo $this->plugin_name; ?>-enable_logging"
                               name="<?php echo $this->plugin_name; ?>[enable_logging]"
                               value="1" <?php checked( $options["enable_logging"], 1 ); ?>/></td>
                </tr>
                <tr>
                    <th scope="row"><label for="zoho_key">Zoho API Key</label></th>
                    <td><input type="text" class="regular-text" id="<?php echo $this->plugin_name; ?>-zoho_key"
                               name="<?php echo $this->plugin_name; ?>[zoho_key]"
                               value="<?php echo $options["zoho_key"] ?>"/>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="zoho_organisation_id">Zoho Organisation ID</label></th>
                    <td><input type="text" class="regular-text"
                               id="<?php echo $this->plugin_name; ?>-zoho_organisation_id"
                               name="<?php echo $this->plugin_name; ?>[zoho_organisation_id]"
                               value="<?php echo $options["zoho_organisation_id"] ?>"/></td>
                </tr>
                <tr>
                    <th scope="row"><label for="zoho_organisation_id">Sync Orders Every:</label></th>
                    <td><input type="text" class="regular-text"
                               id="<?php echo $this->plugin_name; ?>-sync_orders_interval"
                               name="<?php echo $this->plugin_name; ?>[sync_orders_interval]"
                               value="<?php echo $options["sync_orders_interval"] ?>"/></td>
                </tr>
                </tbody>
            </table>

			<?php submit_button( 'Save all changes', 'primary', 'submit', true ); ?>

        </form>

        <form method="get" name="woozoho-connector_options" action="">
			<?php submit_button( 'Find duplicates.', 'primary', 'finddup', true ); ?>
        </form>

    </div>


    <!-- This file should primarily consist of HTML with a little bit of PHP. -->

<?php
//Array:
// - SKU
//PRODUCTS
if ( $_GET["find"] == "dup" ) {
	$blog_id           = get_current_blog_id();
	$full_product_list = array();
	$loop              = new WP_Query( array(
		'post_status'    => 'publish',
		'post_type'      => array( 'product' ),
		'posts_per_page' => - 1
	) );
	while ( $loop->have_posts() ) : $loop->the_post();
		$theid   = get_the_ID();
		$product = new WC_Product( $theid );
		// its a variable product
		$sku = get_post_meta( $theid, '_sku', true );

		$parent      = get_post_meta( $theid, '_woonet_network_is_child_product_id', true );
		$parent_site = get_post_meta( $theid, '_woonet_network_is_child_site_id', true );
		$thetitle    = get_the_title();
		// add product to array but don't add the parent of product variations
		$args  = array(
			'post_type'   => 'product',
			'post_status' => 'publish',
			'meta_query'  => array(
				array(
					'key'   => '_sku',
					'value' => $sku,
				),
				array(
					'key'   => '_price',
					'value' => $product->get_price(),
				)
			),
		);
		$query = new WP_Query( $args );

		switch_to_blog( $parent_site );
		$parent_post_status = get_post_status( parent );
		switch_to_blog( $blog_id );

		if ( $query->post_count > 1 && $sku != null ) {
			echo 'happen.';
			$duplicates[ $sku ][] = array(
				'ID'            => $theid,
				'PRICE'         => $product->get_price(),
				'TITEL'         => $thetitle,
				'PARENT:'       => $parent,
				'PARENT_STATUS' => $parent_post_status
			);
		}
		if ( ! empty( $sku ) ) {
			$full_product_list[] = array( $thetitle, $sku, $theid );
		}
	endwhile;
	wp_reset_query();
	echo '<h2>POST DUPLICATES:' . $duplicates . '</h2>';
	var_dump( $duplicates );
} else if ( $_GET['go'] == "dieren" ) {
	$client   = new ZohoConnector();
	$contacts = $client->zohoClient->listContacts(); //Get all contacts
	print_r( $contacts );
	$contacts = $contacts->contacts;
	echo '<h2>Contacts: ' . count( $contacts ) . '</h2>';
	?>
    <table style="width:100%">
        <tr>
            <th>User Id</th>
            <th>Company Name</th>
            <th>Contact Name</th>
            <th>Status</th>
            <th>Adres</th>
            <th>Stad</th>
            <th>Provincie</th>
            <th>Postcode</th>
        </tr>

	<?
	foreach ( $contacts as $contact ) {
		$contactid  = $contact->contact_id;
		$orders     = $client->getSalesOrders( $contact->contact_id );
		$orders_num = count( $orders );
		//echo $contactid. ' - '. $orders_num . '<br/>';
		if ( $orders_num > 0 ) {
			$order = $client->zohoClient->retrieveSalesOrder( $orders[0]->salesorder_id )->salesorder;
			//print_r($order);
			$item_id = $order->line_items[0]->item_id;
			$item    = $client->zohoClient->retrieveItem( $item_id )->item;
			// echo $item_id. ' - '.$item->group_name .'<br/><br/>';
			//print_r($item);
			if ( $item->group_name == "Dierenbranche 2" ||
			     $item->group_name == "Dierenbranche"
			) {
				$details  = $client->zohoClient->retrieveContact( $contactid )->contact;
				$shipping = $details->shipping_address;
				$result   = '<tr>';
				$result   .= '<td>' . $contactid . '</td>';
				$result   .= '<td>' . $details->company_name . '</td>';
				$result   .= '<td>' . $details->contact_name . '</td>';
				$result   .= '<td>' . $details->status . '</td>';
				$result   .= '<td>' . $details->shipping_address->steet . '</td>';
				$result   .= '<td>' . $details->shipping_address->city . '</td>';
				$result   .= '<td>' . $details->shipping_address->state . '</td>';
				$result   .= '<td>' . $details->shipping_address->zip . '</td>';
				$result   .= '</tr>';
				echo $result;
			}
		}
	}
	echo '</table>';
	//Get one order from contact.
	//Get first product from contact.
	//Get group from product

}
