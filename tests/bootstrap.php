<?php
/**
 * PHPUnit bootstrap for Woo Dynamic Pricing.
 *
 * Loads the WordPress test suite and the plugin under test.
 *
 * @package Woo_Dynamic_Pricing_Discounts
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find the WordPress tests directory (looked in {$_tests_dir}).\n";
	echo "Run `composer install` followed by `composer install-wp-tests` to download it.\n";
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin so the test suite recognizes hooks.
 */
tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( dirname( __FILE__ ) ) . '/woo-dynamic-pricing-discounts.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';
