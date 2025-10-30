<?php
/**
 * Core pricing engine for WooCommerce Dynamic Pricing & Discounts
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// use Automattic\WooCommerce\Utilities\OrderUtil;

class WC_Dynamic_Pricing_Core {

    public function __construct() {
        // Product price adjustments
        add_filter( 'woocommerce_product_get_price', [ $this, 'adjust_product_price' ], 10, 2 );
        add_filter( 'woocommerce_product_get_regular_price', [ $this, 'adjust_product_price' ], 10, 2 );

        // Cart item price display
        add_filter( 'woocommerce_cart_item_price', [ $this, 'cart_item_price' ], 10, 3 );

        // Apply cart-level discounts
        add_action( 'woocommerce_cart_calculate_fees', [ $this, 'apply_cart_discounts' ] );

        // Dynamic shipping
        add_filter( 'woocommerce_shipping_methods', [ $this, 'dynamic_shipping' ] );

        // Clean up transients on cart update/clear
        add_action( 'woocommerce_cart_emptied', [ $this, 'clear_discount_transients' ] );
        add_action( 'woocommerce_cart_updated', [ $this, 'clear_discount_transients' ] );
        // Add to __construct()
        add_action( 'woocommerce_cleanup_sessions', [ $this, 'clear_discount_transients' ] );
    }

    /**
     * Adjust product price based on quantity (product page & cart)
     */
    public function adjust_product_price( $price, $product ) {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return $price;
        }

        $qty = $this->get_current_quantity( $product );

        return $this->apply_rules_to_price( $price, $product, $qty );
    }

    /**
     * Cart item price (shows per-item price in cart)
     */
    public function cart_item_price( $price_html, $cart_item, $cart_item_key ) {
        $product = $cart_item['data'];
        $qty     = $cart_item['quantity'];
        $new_price = $this->apply_rules_to_price( $product->get_price(), $product, $qty );

        return wc_price( $new_price );
    }

    /**
     * Apply cart-level discounts (total amount or quantity)
     */
    public function apply_cart_discounts( $cart ) {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        $rules    = get_option( 'wc_dynamic_rules', [] );
        $total    = $cart->get_subtotal();
        $qty      = $cart->get_cart_contents_count();

        foreach ( $rules as $rule ) {
            if ( $rule['type'] !== 'cart' ) {
                continue;
            }

            $value = ( $rule['condition'] === 'amount' ) ? $total : $qty;
            if ( ! $this->check_condition( $rule, $value ) ) {
                continue;
            }

            $discount = $this->calculate_discount( $total, $rule );

            $fee_id = 'wc_dynamic_discount_' . md5( serialize( $rule ) . $total );
            set_transient( $fee_id, $rule, HOUR_IN_SECONDS );

            $cart->add_fee(
                __( 'Cart Discount', 'wc-dynamic-pricing' ) . ' #' . $fee_id,
                -$discount,
                true,
                ''
            );
        }
    } // ← THIS CLOSING BRACE WAS MISSING!
          /**
           * Calculate discount based on rule type
           */
          private function calculate_discount( $base, $rule ) {
              if ( ! is_array( $rule ) || empty( $rule['discount_type'] ) || empty( $rule['discount_value'] ) ) {
                  return 0;
              }

              $value = floatval( $rule['discount_value'] );

              if ( $rule['discount_type'] === 'percent' ) {
                  return $base * ( $value / 100 );
              } else {
                  return min( $value, $base ); // Fixed amount, cap at base
              }
          }

            $discount = $this->calculate_discount( $total, $rule );

            // Generate unique fee ID for transient storage
            $fee_id = 'wc_dynamic_discount_' . md5( serialize( $rule ) . $total );

            // Store rule in transient (expires in 1 hour)
            set_transient( $fee_id, $rule, HOUR_IN_SECONDS );

            // Add fee with 4 parameters only (name, amount, taxable, tax_class)
            $cart->add_fee(
                __( 'Cart Discount', 'wc-dynamic-pricing' ) . ' #' . $fee_id, // Unique name with ID
                -$discount,
                true, // taxable
                ''    // tax class
            );
        }

    /**
     * Clear discount transients when cart changes
     */
    private function clear_discount_transients() {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_dynamic_discount_%'" );
    }

    /**
     * Dynamic free shipping based on cart total
     */
    public function dynamic_shipping( $methods ) {
        $rules = get_option( 'wc_dynamic_rules', [] );
        $total = WC()->cart ? WC()->cart->get_subtotal() : 0;

        foreach ( $rules as $rule ) {
            if ( $rule['type'] === 'shipping' && $rule['condition'] === 'amount' && $total >= $rule['min'] ) {
                $methods['free_shipping'] = 'WC_Shipping_Free_Shipping';
                break;
            }
        }
        return $methods;
    }

    /**
     * Core rule engine: apply all matching product rules
     */
    private function apply_rules_to_price( $base_price, $product, $qty ) {
        $rules = get_option( 'wc_dynamic_rules', [] );

        foreach ( $rules as $rule ) {
            if ( $rule['type'] !== 'product' ) {
                continue;
            }
            if ( ! $this->matches_target( $product, $rule['target'] ) ) {
                continue;
            }
            if ( ! $this->check_condition( $rule, $qty ) ) {
                continue;
            }

            $discount = $this->calculate_discount( $total, $rule );
        }

        return max( 0, $base_price );
    }

    /**
     * Get current quantity (product page or cart)
     */
    private function get_current_quantity( $product ) {
        $qty = 1;

        // Product page: quantity from form
        if ( isset( $_REQUEST['quantity'] ) ) {
            $qty = max( 1, intval( $_REQUEST['quantity'] ) );
        }
        // If already in cart, use cart quantity
        elseif ( WC()->cart && WC()->session ) {
            $cart_id = WC()->cart->generate_cart_id( $product->get_id() );
            $in_cart = WC()->cart->find_product_in_cart( $cart_id );
            if ( $in_cart ) {
                $cart_item = WC()->cart->get_cart_item( $in_cart );
                $qty = $cart_item ? $cart_item['quantity'] : 1;
            }
        }

        return $qty;
    }

    /**
     * Check if product matches rule target
     */
    private function matches_target( $product, $target ) {
        return $target === 'all' || $product->get_id() == $target;
    }

    /**
     * Check all rule conditions (role, date, min value)
     */
    private function check_condition( $rule, $value ) {
        $user = wp_get_current_user();
        $now  = current_time( 'timestamp' );

        // Role
        if ( ! empty( $rule['role'] ) && ! in_array( $rule['role'], (array) $user->roles, true ) ) {
            return false;
        }

        // Date range
        if ( ! empty( $rule['date_from'] ) && $now < strtotime( $rule['date_from'] ) ) {
            return false;
        }
        if ( ! empty( $rule['date_to'] ) && $now > strtotime( $rule['date_to'] ) ) {
            return false;
        }

        // Min value (quantity or amount)
        return $value >= $rule['min'];
    }
}
