<?php
/**
 * Basic smoke test to verify the plugin can activate within the WP test suite.
 *
 * @package Woo_Dynamic_Pricing_Discounts
 */

class Test_Plugin_Bootstrap extends WP_UnitTestCase {

	/**
	 * Ensure the main plugin class loads without fatal errors.
	 */
	public function test_plugin_classes_are_available() {
		$this->assertTrue( class_exists( 'WC_Dynamic_Pricing_Core' ), 'Core pricing class should be autoloaded.' );
		$this->assertTrue( function_exists( 'wc_dynamic_calculate_discount' ), 'Helper functions should be available.' );
	}

	public function test_plugin_bootstrap_action_fires() {
		$this->assertGreaterThan(
			0,
			did_action( 'wc_dynamic_pricing_loaded' ),
			'Plugin loader should trigger the wc_dynamic_pricing_loaded action.'
		);
	}
}
