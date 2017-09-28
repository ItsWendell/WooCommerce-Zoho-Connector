<?php

class Woozoho_Connector_Zoho_API {
	const ERROR_TYPE_CURL = 'cUrl';
	const ERROR_TYPE_ZOHO = 'zoho';
	const SCOPE_PURCHASEORDERS = 'purchaseorders'; // planned to determine function return format (Json/Boolean)
	const SCOPE_SALESORDERS = 'salesorders';
	const SCOPE_ITEMGROUP = 'itemgroup';
	const SCOPE_INVOICES = 'invoices';
	const SCOPE_CONTACTS = 'contacts';
	const SCOPE_ITEMS = 'items';
	const SCOPE_BILLS = 'bills';
	const STATUS_ALL = 'Status.All';
	const STATUS_ACTIVE_CUSTOMERS = 'Status.ActiveCustomers';
	const STATUS_ACTIVE_VENDORS = 'Status.ActiveVendors';
	const STATUS_INACTIVE_CUSTOMERS = 'Status.InactiveCustomers';
	const STATUS_INACTIVE_VENDORS = 'Status.InactiveVendors';
	const STATUS_CRM = 'Status.Crm';
	const STATUS_INACTIVE = 'Status.Inactive';
	const STATUS_ACTIVE = 'Status.Active';

	public $organizationId;
	public $accessToken;
	private $base_url = 'books.zoho.com/api/v3';
	private $curl_object;

	//TODO: Add support of counting API calls on daily basis.
	//TODO: Before doing API calls figure out how many calls are required (maximum) and see if that fits within the limits of today, if not move the job to tomorrow.

	/**
	 * ZohoClient constructor.
	 *
	 * @param array $config accessToken is must, organizationId is optional in case only one organization
	 *
	 * @throws \Exception
	 */
	public function __construct( array $config = array() ) {
		if ( ! isset( $config['accessToken'] ) ) {
			throw new \Exception( 'You have to set \'accessToken\' to use zoho client' );
		}
		foreach ( $config as $property => $value ) {
			$this->{$property} = $value;
		}
	}

	/**
	 * @return \stdClass list of organization's properties
	 * @see https://www.zoho.com/inventory/api/v1/#organization-id
	 */
	public function retrieveOrganizationsInfo() {
		return $this->curlRequest( '/organizations' );
	}

	private function curlRequest( $alias, $method = 'GET', array $params = [], array $urlParams = [] ) {
		$this->curl_object = $this->initializeCurlObject();
		if ( $method == 'POST' ) {
			curl_setopt( $this->curl_object, CURLOPT_POST, true );
		} else {
			curl_setopt( $this->curl_object, CURLOPT_CUSTOMREQUEST, $method );
		}
		if ( $method == 'GET' ) {
			curl_setopt( $this->curl_object, CURLOPT_URL, $this->getUrlPath( $alias, $params ) );
		} else {
			curl_setopt( $this->curl_object, CURLOPT_URL, $this->getUrlPath( $alias, $urlParams ) );
			curl_setopt( $this->curl_object, CURLOPT_POSTFIELDS, $params );
		}


		//MORE CRAP
		//Authorization: Zoho-authtoken ba4604e8e433g9c892e360d53463oec5
		$auth_header = [
			'Authorization: Zoho-authtoken ' . $this->accessToken
		];


		curl_setopt( $this->curl_object, CURLOPT_AUTOREFERER, true );
		curl_setopt( $this->curl_object, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $this->curl_object, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $this->curl_object, CURLOPT_VERBOSE, 1 );
		$fp = fopen( dirname( __FILE__ ) . '/errorlog.txt', 'w' );
		curl_setopt( $this->curl_object, CURLOPT_STDERR, $fp );
		curl_setopt( $this->curl_object, CURLOPT_HTTPHEADER, $auth_header );

		return $this->execute();
	}

	//************************************** Item *********************************************

	private function initializeCurlObject() {
		$this->curl_object = curl_init( '' );
		curl_setopt( $this->curl_object, CURLOPT_RETURNTRANSFER, 1 );

		return $this->curl_object;
	}

	private function getUrlPath( $alias, $params = [] ) {
		return 'https://' . preg_replace( '/\/+/', '/', "{$this->base_url}/{$alias}" ) . '?'
		       . http_build_query( array_merge( $this->getAuthParams(), $params ?: [] ) );
	}

	/**
	 * Retrieves the array of authentication configs
	 * @return array
	 */
	public function getAuthParams() {
		return array_merge( [ 'organization_id' => $this->organizationId ] );
	}

	private function execute() {
		$return = curl_exec( $this->curl_object );
		if ( curl_errno( $this->curl_object ) ) {
			$errorNo   = curl_errno( $this->curl_object );
			$errorText = curl_error( $this->curl_object );
			throw new \Exception( "cUrl Error ({$errorNo}) : {$errorText}." );
		}
		curl_close( $this->curl_object );
		$return = json_decode( $return );
		if ( $return->code == 0 ) {
			return $return;
		} else {
			throw new \Exception( "Zoho Error {$this->accessToken} ({$return->code}) : {$return->message}." );
		}
		$this->register_api_call();
	}

	private function register_api_call() {

	}

	/**
	 * an extra function to update system settings
	 *
	 * @param string $scope select one of the scopes from constant
	 * @param array $params
	 *
	 * @return \stdClass
	 */
	public function updateSettings( $scope, $params ) {
		return $this->curlRequest( "/settings/$scope/", 'PUT', array( 'JSONString' => json_encode( $params ) ) );
	}

	/**
	 * @param array $params array of properties for the new item
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#create-a-item
	 */
	public function createItem( $params ) {
		return $this->curlRequest( '/items', 'POST', array( 'JSONString' => json_encode( $params ) ) );
	}

	/**
	 * @param string $item_id item to update
	 * @param array $params array of new properties
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#update-an-item
	 */
	public function updateItem( $item_id, $params ) {
		return $this->curlRequest( "/items/{$item_id}", 'PUT', [ 'JSONString' => json_encode( $params ) ] );
	}

	/**
	 * @param string $item_id to retrieve, leave empty to list all items
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#retrieve-a-item
	 */
	public function retrieveItem( $item_id = null ) {
		return $this->curlRequest( "/items/{$item_id}" );
	}

	/**
	 * an extra function allows you to search items by text portion
	 *
	 * @param string $search_text
	 *
	 * @return \stdClass
	 */
	public function searchItem( $search_text ) {
		return $this->listItems( [ 'search_text' => $search_text ] );
	}

	//************************************** Item Group *********************************************

	/**
	 * List all items
	 *
	 * @param array $filters an extra option allows you to filter items by specific fields, leave empty to list all items
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#list-all-item
	 */
	public function listItems( array $filters = array() ) {
		return $this->curlRequest( "/items/", 'GET', $filters );
	}

	public function listTaxes() {
		return $this->curlRequest( "/settings/taxes", 'GET' );
	}

	/**
	 * Deletes an item
	 *
	 * @param string $item_id item to delete
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#delete-an-item
	 */
	public function deleteItem( $item_id ) {
		return $this->curlRequest( "/items/{$item_id}", 'DELETE' );
	}

	/**
	 * Delete an existing item image
	 *
	 * @param string $item_id item to delete its image
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#delete-item-image
	 */
	public function deleteItemImage( $item_id ) {
		return $this->curlRequest( "/items/{$item_id}/image", 'DELETE' );
	}

	/**
	 * Change status of the item to <strong>active</strong>
	 *
	 * @param string $item_id
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#mark-as-active31
	 */
	public function activeItem( $item_id ) {
		return $this->curlRequest( "/items/{$item_id}/active", 'POST' );
	}

	/**
	 * Change status of the item to <strong>inactive</strong>
	 * @see https://www.zoho.com/inventory/api/v1/#mark-as-inactive32
	 *
	 * @param string $item_id
	 *
	 * @return \stdClass
	 */
	public function inactiveItem( $item_id ) {
		return $this->curlRequest( "/items/{$item_id}/inactive", 'POST' );
	}

	/**
	 * Create a item group
	 *
	 * @param array $params properties for the new item group
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#create-a-item-group
	 */
	public function createItemGroup( array $params ) {
		return $this->curlRequest( "/itemgroups", 'POST', [ 'JSONString' => json_encode( $params ) ] );
	}

	/**
	 * Update an Item Group
	 *
	 * @param string $itemgroup_id to update
	 * @param array $params new properties for the selected item group
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#update-an-item-group
	 */
	public function updateItemGroup( $itemgroup_id, array $params ) {
		return $this->curlRequest( "/itemgroups/{$itemgroup_id}", 'PUT', [ 'JSONString' => json_encode( $params ) ] );
	}

	//************************************** Purchase Order *********************************************

	/**
	 * List all Item Group
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#list-all-item-group
	 */
	public function listItemGroups() {
		return $this->retrieveItemGroup();
	}

	/**
	 * Retrieve a Item Group
	 *
	 * @param string $group_id to retrieve, leave empty to retrieve all item groups
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#retrieve-a-item-group
	 */
	public function retrieveItemGroup( $group_id = null ) {
		return $this->curlRequest( "/itemgroups/{$group_id}" );
	}

	/**
	 * Delete an Item Group
	 *
	 * @param string $group_id to be deleted
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#delete-an-item-group
	 */
	public function deleteItemGroup( $group_id ) {
		return $this->curlRequest( "/itemgroups/{$group_id}", 'DELETE' );
	}

	/**
	 * Active an Item Group
	 *
	 * @param string $group_id to be activated
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#mark-as-active22
	 */
	public function activeItemGroup( $group_id ) {
		return $this->curlRequest( "/itemgroups/{$group_id}/active", 'POST' );
	}

	/**
	 * Inactive an Item Group
	 *
	 * @param string $group_id to be disabled
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#mark-as-inactive23
	 */
	public function inactiveItemGroup( $group_id ) {
		return $this->curlRequest( "/itemgroups/{$group_id}/inactive", 'POST' );
	}

	/**
	 * Create a purchase order
	 *
	 * @param array $params
	 * @param bool $ignore ignore auto number generation, if true you must specify the new id, default is false
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#create-a-purchase-order
	 */
	public function createPurchaseOrder( array $params, $ignore = false ) {
		return $this->curlRequest( '/purchaseorders', 'POST',
			[ 'JSONString' => json_encode( $params ) ],
			[ 'ignore_auto_number_generation' => $ignore ? 'true' : 'false' ]
		);
	}

	/**
	 * Update details of an existing purchase order
	 *
	 * @param string $purchaseorder_id to update
	 * @param array $params
	 * @param bool $ignore ignore auto number generation, if true you must specify the new id, default is false
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#update-an-purchase-order
	 */
	public function updatePurchaseOrder( $purchaseorder_id, array $params, $ignore = false ) {
		return $this->curlRequest( "/purchaseorders/{$purchaseorder_id}", 'PUT',
			[ 'JSONString' => json_encode( $params ) ],
			[ 'ignore_auto_number_generation' => $ignore ? 'true' : 'false' ]
		);
	}

	//************************************** Sales Order *********************************************

	/**
	 * List all purchase order
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#retrieve-a-purchase-order
	 */
	public function listPurchaseOrders() {
		return $this->retrievePurchaseOrder();
	}

	/**
	 * Retrieve a purchase order
	 *
	 * @param string $purchaseorder_id to retrieve, leave empty to list all purchase orders
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#retrieve-a-purchase-order
	 */
	public function retrievePurchaseOrder( $purchaseorder_id = null ) {
		return $this->curlRequest( "/purchaseorders/{$purchaseorder_id}" );
	}

	/**
	 * Delete an purchase order
	 *
	 * @param string $purchaseorder_id to be deleted
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#delete-an-purchase-order
	 */
	public function deletePurchaseOrder( $purchaseorder_id ) {
		return $this->curlRequest( "/purchaseorders/{$purchaseorder_id}", 'DELETE' );
	}

	/**
	 * Mark as issued
	 *
	 * @param string $purchaseorder_id
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#mark-as-issued
	 */
	public function issuePurchaseOrder( $purchaseorder_id ) {
		return $this->curlRequest( "/purchaseorders/{$purchaseorder_id}/status/issued", 'POST' );
	}

	/**
	 * Mark as cancelled
	 *
	 * @param string $purchaseorder_id
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#mark-as-cancelled
	 */
	public function cancelPurchaseOrder( $purchaseorder_id ) {
		return $this->curlRequest( "/purchaseorders/{$purchaseorder_id}/status/cancelled", 'POST' );
	}

	/**
	 * Create a sales order
	 *
	 * @param array $params
	 * @param bool $ignore ignore auto number generation, if true you must specify the new id, default is false
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#create-a-sales-order
	 */
	public function createSalesOrder( array $params = [], $ignore = false ) {
		return $this->curlRequest( '/salesorders', 'POST',
			[ 'JSONString' => json_encode( $params ) ],
			[ 'ignore_auto_number_generation' => $ignore ? 'true' : 'false' ]
		);
	}

	//************************************** Contacts *********************************************

	/**
	 * Update details of an existing sales order
	 *
	 * @param string $salesorder_id to update
	 * @param array $params
	 * @param bool $ignore ignore auto number generation, if true you must specify the new id, default is false
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#update-an-sales-order
	 */
	public function updateSalesOrder( $salesorder_id, array $params, $ignore = false ) {
		return $this->curlRequest( "/salesorders/{$salesorder_id}", 'PUT',
			[ 'JSONString' => json_encode( $params ) ],
			[ 'ignore_auto_number_generation' => $ignore ? 'true' : 'false' ]
		);
	}

	/**
	 * Retrieve a sales order
	 *
	 * @param string $salesorder_id to retrieve, leave empty to list all purchase orders
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#retrieve-a-sales-order
	 */
	public function retrieveSalesOrder( $salesorder_id = null ) {
		return $this->curlRequest( "/salesorders/{$salesorder_id}" );
	}

	/**
	 * List all sales order
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#list-all-sales-order
	 */
	public function listSalesOrders( array $filters = [] ) {
		return $this->curlRequest( "/salesorders/", 'GET', $filters );
	}

	/**
	 * Delete an sales order
	 *
	 * @param string $salesorder_id to be deleted
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#delete-an-sales-order
	 */
	public function deleteSalesOrder( $salesorder_id ) {
		return $this->curlRequest( "/salesorders/{$salesorder_id}", 'DELETE' );
	}

	/**
	 * Mark as void
	 *
	 * @param string $salesorder_id
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#mark-as-void68
	 */
	public function set_sales_order_void( $salesorder_id ) {
		return $this->curlRequest( "/salesorders/{$salesorder_id}/status/void", 'POST' );
	}

	/**
	 * @param array $params
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#create-a-contact
	 */
	public function create_contact( array $params = [] ) {
		return $this->curlRequest( "/contacts", 'POST', [ 'JSONString' => json_encode( $params ) ] );
	}

	/**
	 * @param string $contact_id contact id to update
	 * @param array $params
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#update-an-contact
	 */
	public function update_contact( $contact_id, array $params = [] ) {
		return $this->curlRequest( "/contacts/{$contact_id}", 'PUT', [ 'JSONString' => json_encode( $params ) ] );
	}

	/**
	 * @param string $contact_id leave empty to list all contacts
	 * @param array $filters filters array, use constants STATUS_.. to filter by contact type
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#retrieve-a-contact
	 */
	public function get_contact( $contact_id = null, array $filters = [] ) {
		return $this->curlRequest( "/contacts/{$contact_id}", 'GET', $filters );
	}


	//************************************** Others *********************************************

	/**
	 * @param array $filters filters array, use constants STATUS_.. to filter by contact type
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#list-all-contact
	 */
	public function list_contacts( array $filters = [] ) {
		return $this->curlRequest( "/contacts", 'GET', $filters );
	}

	//------------------------------ Execution Functions --------------------------

	/**
	 * @param string $contact_id contact id to delete
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#delete-an-contact
	 */
	public function delete_contact( $contact_id ) {
		return $this->curlRequest( "/contacts/{$contact_id}", 'DELETE' );
	}

	/**
	 * @param string $contact_id contact id to active
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#mark-as-active
	 */
	public function set_contact_active( $contact_id ) {
		return $this->curlRequest( "/contacts/{$contact_id}/active", 'POST' );
	}

	/**
	 * @param string $contact_id contact id to inactive
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#mark-as-inactive
	 */
	public function set_contact_inactive( $contact_id ) {
		return $this->curlRequest( "/contacts/{$contact_id}/inactive", 'POST' );
	}

	/**
	 * @param string $contact_id contact id to active
	 *
	 * @return \stdClass
	 * @see https://www.zoho.com/inventory/api/v1/#mark-as-active
	 */
	public function createComment( $salesorder_id, $description ) {
		$params = array( "description" => $description );

		return $this->curlRequest( "/salesorders/{$salesorder_id}/comments", 'POST', [ 'JSONString' => json_encode( $params ) ] );
	}
}
