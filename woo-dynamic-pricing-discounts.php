<?php
/**
 * Plugin Name: WooCommerce Dynamic Pricing & Discounts
 * Plugin URI:  https://caaza.co/woo-dynamic-pricing
 * Description: Advanced dynamic pricing, bulk discounts, cart rules, BOGO, role-based pricing, and more for WooCommerce.
 * Version:     1.1.8
 * Author:      Caaza Developers
 * Author URI:  https://caaza.co
 * Text Domain: wc-dynamic-pricing
 * Domain Path: /languages
 * License:     GPL-2.0+
 * Requires at least: 5.6
 * Tested up to: 6.5
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 *
 * == Changelog ==
 * Version:     1.1.9
 * ...
 * = 1.1.9 - 2025-10-29 =
 * - **Fixed**: All syntax/logic errors in frontend.php.
 * - "You Save X%" **always displays** in cart/checkout.
 * Version:     1.1.8
 * ...
 * = 1.1.8 - 2025-10-29 =
 * - **Fixed**: "You Save X%" now **always displays** in cart & checkout.
 *   - Improved fee detection
 *   - Full HTML re-render on live update
 *  - Supports multiple rules
 * = 1.1.5 - 2025-10-29 =
 * - **New**: "You Save X%" displayed in cart totals (live updates).
 *
 * = 1.1.4 - 2025-10-29 =
 * - **Fixed**: Product-page price display error (PHP fatal) caused by incorrect method calls.
 *   - `price_html()` now safely checks context and uses `get_regular_price()`.
 *   - AJAX live-price returns full `<del><ins>` HTML.
 *
 * = 1.1.3 - 2025-10-29 =
 * - **Fixed**: Product page now shows **original price (strikethrough) + discounted price**.
 *   - Uses `get_regular_price()` (unfiltered) for original price.
 *   - Live AJAX update includes full `<del><ins>` HTML.
 *
 * = 1.1.2 - 2025-10-29 =
 * - **Live Cart Updates**: Cart subtotal, total, and discount update instantly when quantity changes.
 *   - No page refresh needed.
 *   - Works on cart and checkout pages.
 *   - Preserves WooCommerce fragments and triggers `updated_wc_div`.
 *
 * = 1.1.1 - 2025-10-29 =
 * - **Fixed**: Quantity-based discounts now update instantly on the product page and in the cart.
 *   - Live price update via AJAX when the quantity field changes.
 *   - Cart item price uses real cart quantity.
 *   - Core engine re-uses `apply_rules_to_price()` for consistency.
 *
 * = 1.1.0 - 2025-10-29 =
 * - HPOS compatibility, admin dashboard, modular code, etc.
 *
 *
 * = 1.1.0 - 2025-10-29 =
 * - **HPOS Compatibility**: Full support for WooCommerce High-Performance Order Storage (HPOS).
 *   - Declared compatibility via `Automattic\WooCommerce\Utilities\FeaturesUtil`.
 *   - Added `OrderUtil` import in core and admin classes for future-proof order handling.
 *   - Created optional `WC_Dynamic_Pricing_HPOS` utility class for safe order operations.
 * - **Plugin Architecture**: Modularized into `includes/` classes (`core`, `frontend`, `admin`, `helpers`).
 * - **Admin Dashboard**: Added top-level menu with AJAX-powered rule management (add/edit/delete).
 * - **Frontend**: Pricing tables, cart notices, shortcodes, and dynamic shipping rules.
 * - **Security & Performance**: Nonces, sanitization, capability checks, and efficient rule evaluation.
 * - **No Breaking Changes**: All existing functionality preserved and enhanced.
 */


 if (!defined('ABSPATH')) {
    exit;
}

define('WC_DYNAMIC_PRICING_VERSION', '1.1.3');
define('WC_DYNAMIC_PRICING_PATH', plugin_dir_path(__FILE__));
define('WC_DYNAMIC_PRICING_URL', plugin_dir_url(__FILE__));

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

class WC_Dynamic_Pricing_Loader {
    public function __construct() {
        add_action('plugins_loaded', [$this, 'load']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_uninstall_hook(__FILE__, 'WC_Dynamic_Pricing_Loader::uninstall');
    }

    public function load() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'missing_woocommerce_notice']);
            return;
        }

        $this->load_textdomain();
        $this->includes();

        do_action('wc_dynamic_pricing_loaded');
    }

    public function missing_woocommerce_notice() {
        echo '<div class="notice notice-error"><p>' . __('WooCommerce Dynamic Pricing requires WooCommerce to be active.', 'wc-dynamic-pricing') . '</p></div>';
    }

    private function load_textdomain() {
        load_plugin_textdomain('wc-dynamic-pricing', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    private function includes() {
        require_once WC_DYNAMIC_PRICING_PATH . 'includes/helpers.php';
        require_once WC_DYNAMIC_PRICING_PATH . 'includes/class-wc-dynamic-pricing-core.php';
        require_once WC_DYNAMIC_PRICING_PATH . 'includes/class-wc-dynamic-pricing-frontend.php';
        require_once WC_DYNAMIC_PRICING_PATH . 'includes/class-wc-dynamic-pricing-admin.php';

        new WC_Dynamic_Pricing_Core();
        new WC_Dynamic_Pricing_Frontend();
        if (is_admin()) {
            new WC_Dynamic_Pricing_Admin();
        }
    }

    public function activate() {
        if (!get_option('wc_dynamic_rules')) {
            update_option('wc_dynamic_rules', []);
        }
    }

    public static function uninstall() {
        if (!defined('WP_UNINSTALL_PLUGIN')) return;
        delete_option('wc_dynamic_rules');
    }
}

new WC_Dynamic_Pricing_Loader();
