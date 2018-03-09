<?php
/**
 * Settings API Tests.
 *
 * @package WooCommerce\Tests\API
 * @since 3.0.0
 */

class Settings extends WC_REST_Unit_Test_Case {

	/**
	 * Setup our test server, endpoints, and user info.
	 */
	public function setUp() {
		parent::setUp();
		$this->endpoint = new WC_REST_DEV_Setting_Options_Controller();
		WC_Helper_Settings::register();
		$this->user = $this->factory->user->create( array(
			'role' => 'administrator',
		) );
	}

	/**
	 * Test route registration.
	 *
	 * @since 3.0.0
	 */
	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wc/v3/settings', $routes );
		$this->assertArrayHasKey( '/wc/v3/settings/batch', $routes );
		$this->assertArrayHasKey( '/wc/v3/settings/(?P<group_id>[\w-]+)', $routes );
		$this->assertArrayHasKey( '/wc/v3/settings/(?P<group_id>[\w-]+)/batch', $routes );
		$this->assertArrayHasKey( '/wc/v3/settings/(?P<group_id>[\w-]+)/(?P<id>[\w-]+)', $routes );
	}

	/**
	 * Test getting all groups.
	 *
	 * @since 3.0.0
	 */
	public function test_get_groups() {
		wp_set_current_user( $this->user );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings' ) );
		$data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );

		$this->assertContains( array(
			'id'          => 'test',
			'label'       => 'Test extension',
			'parent_id'   => '',
			'description' => 'My awesome test settings.',
			'sub_groups'  => array( 'sub-test' ),
			'_links'      => array(
				'options' => array(
					array(
						'href' => rest_url( '/wc/v3/settings/test' ),
					),
				),
			),
		), $data );

		$this->assertContains( array(
			'id'          => 'sub-test',
			'label'       => 'Sub test',
			'parent_id'   => 'test',
			'description' => '',
			'sub_groups'  => array(),
			'_links'      => array(
				'options' => array(
					array(
						'href' => rest_url( '/wc/v3/settings/sub-test' ),
					),
				),
			),
		), $data );
	}

	/**
	 * Test /settings without valid permissions/creds.
	 *
	 * @since 3.0.0
	 */
	public function test_get_groups_without_permission() {
		wp_set_current_user( 0 );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings' ) );
		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test /settings without valid permissions/creds.
	 *
	 * @since 3.0.0
	 * @covers WC_Rest_Settings_Controller::get_items
	 */
	public function test_get_groups_none_registered() {
		wp_set_current_user( $this->user );

		remove_all_filters( 'woocommerce_settings_groups' );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings' ) );
		$this->assertEquals( 500, $response->get_status() );

		WC_Helper_Settings::register();
	}

	/**
	 * Test groups schema.
	 *
	 * @since 3.0.0
	 */
	public function test_get_group_schema() {
		$request = new WP_REST_Request( 'OPTIONS', '/wc/v3/settings' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertEquals( 5, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'parent_id', $properties );
		$this->assertArrayHasKey( 'label', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'sub_groups', $properties );
	}

	/**
	 * Test settings schema.
	 *
	 * @since 3.0.0
	 */
	public function test_get_setting_schema() {
		$request = new WP_REST_Request( 'OPTIONS', '/wc/v3/settings/test/woocommerce_shop_page_display' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertEquals( 10, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'group_id', $properties );
		$this->assertArrayHasKey( 'label', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'value', $properties );
		$this->assertArrayHasKey( 'default', $properties );
		$this->assertArrayHasKey( 'tip', $properties );
		$this->assertArrayHasKey( 'placeholder', $properties );
		$this->assertArrayHasKey( 'type', $properties );
		$this->assertArrayHasKey( 'options', $properties );
	}

	/**
	 * Test getting a single group.
	 *
	 * @since 3.0.0
	 */
	public function test_get_group() {
		wp_set_current_user( $this->user );

		// test route callback receiving an empty group id
		$result = $this->endpoint->get_group_settings( '' );
		$this->assertIsWPError( $result );

		// test getting a group that does not exist
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings/not-real' ) );
		$this->assertEquals( 404, $response->get_status() );

		// test getting the 'invalid' group
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings/invalid' ) );
		$this->assertEquals( 404, $response->get_status() );

		// test getting a valid group
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings/general' ) );
		$data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );

		$this->assertContains( array(
    		'id' => 'woocommerce_price_num_decimals',
			'label' => 'Number of decimals',
			'description' => 'This sets the number of decimal points shown in displayed prices.',
			'type' => 'number',
			'default' => 2,
			'tip' => 'This sets the number of decimal points shown in displayed prices.',
			'value' => 2,
			'_links' => array(
				'self' => array(
					array(
						'href' => rest_url( '/wc/v3/settings/general/woocommerce_price_num_decimals' ),
					),
				),
				'collection' => array(
					array(
						'href' => rest_url( '/wc/v3/settings/general' ),
					),
				),
			),
		), $data );

		// test getting a valid group with settings attached to it
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings/test' ) );
		$data = $response->get_data();
		$this->assertEquals( 1, count( $data ) );
		$this->assertEquals( 'woocommerce_shop_page_display', $data[0]['id'] );
		$this->assertEmpty( $data[0]['value'] );
	}

	/**
	 * Test getting a single group without permission.
	 *
	 * @since 3.0.0
	 */
	public function test_get_group_without_permission() {
		wp_set_current_user( 0 );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings/coupon-data' ) );
		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test updating a single setting.
	 *
	 * @since 3.0.0
	 */
	public function test_update_setting() {
		wp_set_current_user( $this->user );

		// test defaults first
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings/test/woocommerce_shop_page_display' ) );
		$data = $response->get_data();
		$this->assertEquals( '', $data['value'] );

		// test updating shop display setting
		$request = new WP_REST_Request( 'PUT', sprintf( '/wc/v3/settings/%s/%s', 'test', 'woocommerce_shop_page_display' ) );
		$request->set_body_params( array(
			'value' => 'both',
		) );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( 'both', $data['value'] );
		$this->assertEquals( 'both', get_option( 'woocommerce_shop_page_display' ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wc/v3/settings/%s/%s', 'test', 'woocommerce_shop_page_display' ) );
		$request->set_body_params( array(
			'value' => 'subcategories',
		) );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( 'subcategories', $data['value'] );
		$this->assertEquals( 'subcategories', get_option( 'woocommerce_shop_page_display' ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wc/v3/settings/%s/%s', 'test', 'woocommerce_shop_page_display' ) );
		$request->set_body_params( array(
			'value' => '',
		) );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( '', $data['value'] );
		$this->assertEquals( '', get_option( 'woocommerce_shop_page_display' ) );
	}

	/**
	 * Test updating multiple settings at once.
	 *
	 * @since 3.0.0
	 */
	public function test_update_settings() {
		wp_set_current_user( $this->user );

		// test defaults first
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings/test' ) );
		$data = $response->get_data();
		$this->assertEquals( '', $data[0]['value'] );

		// test setting both at once
		$request = new WP_REST_Request( 'POST', '/wc/v3/settings/test/batch' );
		$request->set_body_params( array(
			'update' => array(
				array(
					'id'    => 'woocommerce_shop_page_display',
					'value' => 'both',
				),
			),
		) );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( 'both', $data['update'][0]['value'] );
		$this->assertEquals( 'both', get_option( 'woocommerce_shop_page_display' ) );

		// test bulk settings batch endpoint
		$request = new WP_REST_Request( 'POST', '/wc/v3/settings/batch' );
		$request->set_body_params( array(
			'update' => array(
				array(
					'group_id' => 'test',
					'id'       => 'woocommerce_shop_page_display',
					'value'     => 'subcategories',
				),
				array(
					'group_id' => 'products',
					'id'       => 'woocommerce_dimension_unit',
					'value'     => 'yd',
				),
			),
		) );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'subcategories', $data['update'][0]['value'] );
		$this->assertEquals( 'yd', $data['update'][1]['value'] );
		$this->assertEquals( 'yd', get_option( 'woocommerce_dimension_unit' ) );

		// test updating one, but making sure the other value stays the same
		$request = new WP_REST_Request( 'POST', '/wc/v3/settings/test/batch' );
		$request->set_body_params( array(
			'update' => array(
				array(
					'id'    => 'woocommerce_shop_page_display',
					'value' => 'subcategories',
				),
			),
		) );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'subcategories', $data['update'][0]['value'] );
		$this->assertEquals( 'subcategories', get_option( 'woocommerce_shop_page_display' ) );
	}

	/**
	 * Test getting a single setting.
	 *
	 * @since 3.0.0
	 */
	public function test_get_setting() {
		wp_set_current_user( $this->user );

		// test getting an invalid setting from a group that does not exist
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings/not-real/woocommerce_shop_page_display' ) );
		$data = $response->get_data();
		$this->assertEquals( 404, $response->get_status() );

		// test getting an invalid setting from a group that does exist
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings/invalid/invalid' ) );
		$data = $response->get_data();
		$this->assertEquals( 404, $response->get_status() );

		// test getting a valid setting
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings/test/woocommerce_shop_page_display' ) );
		$data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );

		$this->assertEquals( 'woocommerce_shop_page_display', $data['id'] );
		$this->assertEquals( 'Shop page display', $data['label'] );
		$this->assertEquals( '', $data['default'] );
		$this->assertEquals( 'select', $data['type'] );
		$this->assertEquals( '', $data['value'] );
	}

	/**
	 * Test getting a single setting without valid user permissions.
	 *
	 * @since 3.0.0
	 */
	public function test_get_setting_without_permission() {
		wp_set_current_user( 0 );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings/test/woocommerce_shop_page_display' ) );
		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Tests the GET single setting route handler receiving an empty setting ID.
	 *
	 * @since 3.0.0
	 */
	public function test_get_setting_empty_setting_id() {
		$result = $this->endpoint->get_setting( 'test', '' );

		$this->assertIsWPError( $result );
	}

	/**
	 * Tests the GET single setting route handler receiving an invalid setting ID.
	 *
	 * @since 3.0.0
	 */
	public function test_get_setting_invalid_setting_id() {
		$result = $this->endpoint->get_setting( 'test', 'invalid' );

		$this->assertIsWPError( $result );
	}

	/**
	 * Tests the GET single setting route handler encountering an invalid setting type.
	 *
	 * @since 3.0.0
	 */
	public function test_get_setting_invalid_setting_type() {
		// $controller = $this->getMock( 'WC_Rest_Setting_Options_Controller', array( 'get_group_settings', 'is_setting_type_valid' ) );
		$controller = $this->getMockBuilder( 'WC_Rest_Setting_Options_Controller' )->setMethods( array( 'get_group_settings', 'is_setting_type_valid' ) )->getMock();

		$controller
			->expects( $this->any() )
			->method( 'get_group_settings' )
			->will( $this->returnValue( WC_Helper_Settings::register_test_settings( array() ) ) );

		$controller
			->expects( $this->any() )
			->method( 'is_setting_type_valid' )
			->will( $this->returnValue( false ) );

		$result = $controller->get_setting( 'test', 'woocommerce_shop_page_display' );

		$this->assertIsWPError( $result );
	}

	/**
	 * Test updating a single setting without valid user permissions.
	 *
	 * @since 3.0.0
	 */
	public function test_update_setting_without_permission() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wc/v3/settings/%s/%s', 'test', 'woocommerce_shop_page_display' ) );
		$request->set_body_params( array(
			'value' => 'subcategories',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 401, $response->get_status() );
	}


	/**
	 * Test updating multiple settings without valid user permissions.
	 *
	 * @since 3.0.0
	 */
	public function test_update_settings_without_permission() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/wc/v3/settings/test/batch' );
		$request->set_body_params( array(
			'update' => array(
				array(
					'id'    => 'woocommerce_shop_page_display',
					'value' => 'subcategories',
				),
			),
		) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test updating a bad setting ID.
	 *
	 * @since 3.0.0
	 * @covers WC_Rest_Setting_Options_Controller::update_item
	 */
	public function test_update_setting_bad_setting_id() {
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', '/wc/v3/settings/test/invalid' );
		$request->set_body_params( array(
			'value' => 'test',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Tests our classic setting registration to make sure settings added for WP-Admin are available over the API.
	 *
	 * @since 3.0.0
	 */
	public function test_classic_settings() {
		wp_set_current_user( $this->user );

		// Make sure the group is properly registered
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings/products' ) );
		$data = $response->get_data();
		$this->assertTrue( is_array( $data ) );
		$this->assertContains( array(
			'id'          => 'woocommerce_downloads_require_login',
			'label'       => 'Access restriction',
			'description' => 'Downloads require login',
			'type'        => 'checkbox',
			'default'     => 'no',
			'tip'         => 'This setting does not apply to guest purchases.',
			'value'       => 'no',
			'_links'      => array(
				'self' => array(
					array(
						'href' => rest_url( '/wc/v3/settings/products/woocommerce_downloads_require_login' ),
					),
				),
				'collection' => array(
					array(
						'href' => rest_url( '/wc/v3/settings/products' ),
					),
				),
			),
	), $data );

		// test get single
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings/products/woocommerce_dimension_unit' ) );
		$data = $response->get_data();

		$this->assertEquals( 'cm', $data['default'] );

		// test update
		$request = new WP_REST_Request( 'PUT', sprintf( '/wc/v3/settings/%s/%s', 'products', 'woocommerce_dimension_unit' ) );
		$request->set_body_params( array(
			'value' => 'yd',
		) );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( 'yd', $data['value'] );
		$this->assertEquals( 'yd', get_option( 'woocommerce_dimension_unit' ) );
	}

	/**
	 * Tests our email etting registration to make sure settings added for WP-Admin are available over the API.
	 *
	 * @since 3.0.0
	 */
	public function test_email_settings() {
		wp_set_current_user( $this->user );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings/email_new_order' ) );
		$settings = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );

		$this->assertContains( array(
			'id'          => 'recipient',
			'label'       => 'Recipient(s)',
			'description' => 'Enter recipients (comma separated) for this email. Defaults to <code>admin@example.org</code>.',
			'type'        => 'text',
			'default'     => '',
			'tip'         => 'Enter recipients (comma separated) for this email. Defaults to <code>admin@example.org</code>.',
			'value'       => '',
			'_links'      => array(
				'self' => array(
					array(
						'href' => rest_url( '/wc/v3/settings/email_new_order/recipient' ),
					),
				),
				'collection' => array(
					array(
						'href' => rest_url( '/wc/v3/settings/email_new_order' ),
					),
				),
			),
		), $settings );

		// test get single
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings/email_new_order/subject' ) );
		$setting  = $response->get_data();

		$this->assertEquals( array(
			'id'          => 'subject',
			'label'       => 'Subject',
			'description' => 'Available placeholders: <code>{site_title}, {order_date}, {order_number}</code>',
			'type'        => 'text',
			'default'     => '',
			'tip'         => 'Available placeholders: <code>{site_title}, {order_date}, {order_number}</code>',
			'value'       => '',
			'group_id' => 'email_new_order',
		), $setting );

		// test update
		$request = new WP_REST_Request( 'PUT', sprintf( '/wc/v3/settings/%s/%s', 'email_new_order', 'subject' ) );
		$request->set_body_params( array(
			'value' => 'This is my subject',
		) );
		$response = $this->server->dispatch( $request );
		$setting  = $response->get_data();

		$this->assertEquals( array(
			'id'          => 'subject',
			'label'       => 'Subject',
			'description' => 'Available placeholders: <code>{site_title}, {order_date}, {order_number}</code>',
			'type'        => 'text',
			'default'     => '',
			'tip'         => 'Available placeholders: <code>{site_title}, {order_date}, {order_number}</code>',
			'value'       => 'This is my subject',
			'group_id' => 'email_new_order',
		), $setting );

		// test updating another subject and making sure it works with a "similar" id
		$request = new WP_REST_Request( 'GET', sprintf( '/wc/v3/settings/%s/%s', 'email_customer_new_account', 'subject' ) );
		$response = $this->server->dispatch( $request );
		$setting  = $response->get_data();

		$this->assertEmpty( $setting['value'] );

		// test update
		$request = new WP_REST_Request( 'PUT', sprintf( '/wc/v3/settings/%s/%s', 'email_customer_new_account', 'subject' ) );
		$request->set_body_params( array(
			'value' => 'This is my new subject',
		) );
		$response = $this->server->dispatch( $request );
		$setting  = $response->get_data();

		$this->assertEquals( 'This is my new subject', $setting['value'] );

		// make sure the other is what we left it
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings/email_new_order/subject' ) );
		$setting  = $response->get_data();

		$this->assertEquals( 'This is my subject', $setting['value'] );
	}

	/**
	 * Test validation of checkbox settings.
	 *
	 * @since 3.0.0
	 */
	public function test_validation_checkbox() {
		wp_set_current_user( $this->user );

		// test bogus value
		$request = new WP_REST_Request( 'PUT', sprintf( '/wc/v3/settings/%s/%s', 'email_cancelled_order', 'enabled' ) );
		$request->set_body_params( array(
			'value' => 'not_yes_or_no',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );

		// test yes
		$request = new WP_REST_Request( 'PUT', sprintf( '/wc/v3/settings/%s/%s', 'email_cancelled_order', 'enabled' ) );
		$request->set_body_params( array(
			'value' => 'yes',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		// test no
		$request = new WP_REST_Request( 'PUT', sprintf( '/wc/v3/settings/%s/%s', 'email_cancelled_order', 'enabled' ) );
		$request->set_body_params( array(
			'value' => 'no',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test validation of radio settings.
	 *
	 * @since 3.0.0
	 */
	public function test_validation_radio() {
		wp_set_current_user( $this->user );

		// not a valid option
		$request = new WP_REST_Request( 'PUT', sprintf( '/wc/v3/settings/%s/%s', 'shipping', 'woocommerce_ship_to_destination' ) );
		$request->set_body_params( array(
			'value' => 'billing2',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );

		// valid
		$request = new WP_REST_Request( 'PUT', sprintf( '/wc/v3/settings/%s/%s', 'shipping', 'woocommerce_ship_to_destination' ) );
		$request->set_body_params( array(
			'value' => 'billing',
		) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test validation of multiselect.
	 *
	 * @since 3.0.0
	 */
	public function test_validation_multiselect() {
		wp_set_current_user( $this->user );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', sprintf( '/wc/v3/settings/%s/%s', 'general', 'woocommerce_specific_allowed_countries' ) ) );
		$setting  = $response->get_data();
		$this->assertEmpty( $setting['value'] );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wc/v3/settings/%s/%s', 'general', 'woocommerce_specific_allowed_countries' ) );
		$request->set_body_params( array(
			'value' => array( 'AX', 'DZ', 'MMM' ),
		) );
		$response = $this->server->dispatch( $request );
		$setting  = $response->get_data();
		$this->assertEquals( array( 'AX', 'DZ' ), $setting['value'] );
	}

	/**
	 * Test validation of select.
	 *
	 * @since 3.0.0
	 */
	public function test_validation_select() {
		wp_set_current_user( $this->user );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', sprintf( '/wc/v3/settings/%s/%s', 'products', 'woocommerce_weight_unit' ) ) );
		$setting  = $response->get_data();
		$this->assertEquals( 'kg', $setting['value'] );

		// invalid
		$request = new WP_REST_Request( 'PUT', sprintf( '/wc/v3/settings/%s/%s', 'products', 'woocommerce_weight_unit' ) );
		$request->set_body_params( array(
			'value' => 'pounds', // invalid, should be lbs
		) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );

		// valid
		$request = new WP_REST_Request( 'PUT', sprintf( '/wc/v3/settings/%s/%s', 'products', 'woocommerce_weight_unit' ) );
		$request->set_body_params( array(
			'value' => 'lbs', // invalid, should be lbs
		) );
		$response = $this->server->dispatch( $request );
		$setting  = $response->get_data();
		$this->assertEquals( 'lbs', $setting['value'] );
	}

	/**
	 * All settings using the 'image_width' type have been moved to the customizer.
	 * This adds one back so we can still test the validation in `test_validation_image_width`.
	 */
	public function add_shop_thumbnail_image_size_setting( $settings ) {
		$settings[] = array(
			'title'    => __( 'Product thumbnails', 'woocommerce' ),
			'desc'     => __( 'This size is usually used for the gallery of images on the product page. (W x H)', 'woocommerce' ),
			'id'       => 'shop_thumbnail_image_size',
			'css'      => '',
			'type'     => 'image_width',
			'default'  => array(
				'width'  => '180',
				'height' => '180',
				'crop'   => 1,
			),
			'desc_tip' => true,
		);
		return $settings;
	}
	/**
	 * Test validation of image_width.
	 *
	 * @since 3.0.0
	 */
	public function test_validation_image_width() {
		wp_set_current_user( $this->user );

		add_filter( 'woocommerce_product_settings', array( $this, 'add_shop_thumbnail_image_size_setting' ) );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', sprintf( '/wc/v3/settings/%s/%s', 'products', 'shop_thumbnail_image_size' ) ) );
		$setting  = $response->get_data();
		$this->assertEquals( array( 'width' => 180, 'height' => 180, 'crop' => true ), $setting['value'] );

		// test bogus
		$request = new WP_REST_Request( 'PUT', sprintf( '/wc/v3/settings/%s/%s', 'products', 'shop_thumbnail_image_size' ) );
		$request->set_body_params( array(
			'value' => array(
				'width'  => 400,
				'height' => 200,
				'crop'   => 'asdasdasd',
			),
		) );
		$response = $this->server->dispatch( $request );
		$setting  = $response->get_data();
		$this->assertEquals( array( 'width' => 400, 'height' => 200, 'crop' => true ), $setting['value'] );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wc/v3/settings/%s/%s', 'products', 'shop_thumbnail_image_size' ) );
		$request->set_body_params( array(
			'value' => array(
				'width'  => 200,
				'height' => 100,
				'crop'   => false,
			),
		) );
		$response = $this->server->dispatch( $request );
		$setting  = $response->get_data();
		$this->assertEquals( array( 'width' => 200, 'height' => 100, 'crop' => false ), $setting['value'] );
	}

	/**
	 * Test to make sure the 'base location' setting is present in the response.
	 * That it is returned as 'select' and not 'single_select_country',
	 * and that both state and country options are returned.
	 *
	 * @since 3.0.7
	 */
	public function test_woocommerce_default_country() {
		wp_set_current_user( $this->user );
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/wc/v3/settings/general/woocommerce_default_country' ) );
		$setting  = $response->get_data();

		$this->assertEquals( 'select', $setting['type'] );
		$this->assertArrayHasKey( 'GB', $setting['options'] );
		$this->assertArrayHasKey( 'US:OR', $setting['options'] );
	}

}
