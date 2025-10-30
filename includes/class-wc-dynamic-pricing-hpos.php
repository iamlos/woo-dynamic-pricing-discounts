<?php
/**
 * HPOS Compatibility Utilities for Dynamic Pricing Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Utilities\OrderUtil;

class WC_Dynamic_Pricing_HPOS {

    /**
     * Check if HPOS is enabled
     *
     * @return bool
     */
    public static function is_hpos_enabled() {
        return class_exists(OrderUtil::class) && OrderUtil::custom_orders_table_usage_is_enabled();
    }

    /**
     * Get order type safely (HPOS-compatible)
     *
     * @param int $order_id
     * @return string|false
     */
    public static function get_order_type($order_id) {
        if (!class_exists(OrderUtil::class)) {
            // Fallback for pre-HPOS
            $post_type = get_post_type($order_id);
            return in_array($post_type, wc_get_order_types()) ? $post_type : false;
        }
        return OrderUtil::get_order_type($order_id);
    }

    /**
     * Check if ID is an order (HPOS-compatible)
     *
     * @param int $order_id
     * @param array $order_types
     * @return bool
     */
    public static function is_order($order_id, $order_types = []) {
        if (!class_exists(OrderUtil::class)) {
            return in_array(get_post_type($order_id), $order_types ?: wc_get_order_types());
        }
        return OrderUtil::is_order($order_id, $order_types ?: wc_get_order_types());
    }

    /**
     * Example: Apply rule to order meta (future extension)
     *
     * @param WC_Order $order
     * @param array $rule
     */
    public static function apply_discount_to_order_meta($order, $rule) {
        if (!$order) return;

        // HPOS-safe meta update
        $order->update_meta_data('_dynamic_discount_applied', $rule['discount_value']);
        $order->save(); // Routes to correct table
    }
}

// Initialize if needed (add to loader: new WC_Dynamic_Pricing_HPOS(); )
