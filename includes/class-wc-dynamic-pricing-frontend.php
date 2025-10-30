<?php
/**
 * Frontend display and live updates for WooCommerce Dynamic Pricing & Discounts
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Dynamic_Pricing_Frontend {

    public function __construct() {
        // Notices
        add_action( 'woocommerce_before_cart', [ $this, 'cart_notice' ] );

        // Pricing table on product page
        add_action( 'woocommerce_single_product_summary', [ $this, 'pricing_table' ], 25 );

        // Shortcodes
        add_shortcode( 'pricing_table', [ $this, 'shortcode_pricing_table' ] );
        add_shortcode( 'dynamic_discounts', [ $this, 'shortcode_discounted_products' ] );

        // Price HTML: show original + discounted
        add_filter( 'woocommerce_get_price_html', [ $this, 'price_html' ], 100, 2 );

        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // AJAX handlers
        add_action( 'wp_ajax_wc_dynamic_live_price', [ $this, 'ajax_live_price' ] );
        add_action( 'wp_ajax_nopriv_wc_dynamic_live_price', [ $this, 'ajax_live_price' ] );

        add_action( 'wp_ajax_wc_dynamic_update_cart', [ $this, 'ajax_update_cart' ] );
        add_action( 'wp_ajax_nopriv_wc_dynamic_update_cart', [ $this, 'ajax_update_cart' ] );

        // "You Save X%" in cart totals
        add_action( 'woocommerce_cart_totals_after_order_total', [ $this, 'display_cart_savings' ] );
        add_action( 'woocommerce_review_order_after_order_total', [ $this, 'display_cart_savings' ] );
    }

    /* -----------------------------------------------------------------
       ENQUEUE ASSETS
    ----------------------------------------------------------------- */
    public function enqueue_assets() {
        if ( is_product() ) {
            wp_enqueue_style(
                'wc-dynamic-pricing',
                WC_DYNAMIC_PRICING_URL . 'assets/css/style.css',
                [],
                WC_DYNAMIC_PRICING_VERSION
            );
            wp_add_inline_script( 'jquery', $this->live_product_script() );
        }

        if ( is_cart() || is_checkout() ) {
            wp_add_inline_script( 'jquery', $this->live_cart_script() );
        }
    }

    /* -----------------------------------------------------------------
       PRICE HTML – show strikethrough
    ----------------------------------------------------------------- */
    public function price_html( $price_html, $product ) {
        if ( is_admin() || ! $product->is_visible() ) {
            return $price_html;
        }

        $core             = new WC_Dynamic_Pricing_Core();
        $regular_price    = $product->get_regular_price();
        $discounted_price = $core->adjust_product_price( $product->get_price(), $product );

        if ( $discounted_price < $regular_price ) {
            return '<del>' . wc_price( $regular_price ) . '</del> <ins>' . wc_price( $discounted_price ) . '</ins>';
        }

        return $price_html;
    }

    /* -----------------------------------------------------------------
       LIVE PRODUCT PAGE PRICE
    ----------------------------------------------------------------- */
    private function live_product_script() {
        return "
        jQuery(function($){
            var updatePrice = function(){
                var qty = parseInt($('.qty').val()) || 1;
                var data = {
                    action: 'wc_dynamic_live_price',
                    product_id: $('input[name=product_id]').val() || $('[name=add-to-cart]').val(),
                    quantity: qty,
                    security: '" . wp_create_nonce( 'wc_dynamic_live_price' ) . "'
                };
                $.post(wc_add_to_cart_params.ajax_url, data, function(res){
                    if (res.success) {
                        $('.summary .price').html(res.data.price_html);
                    }
                });
            };
            $(document).on('change keyup', '.qty', updatePrice);
            updatePrice();
        });
        ";
    }

    public function ajax_live_price() {
        check_ajax_referer( 'wc_dynamic_live_price', 'security' );

        $product_id = absint( $_POST['product_id'] ?? 0 );
        $qty        = max( 1, intval( $_POST['quantity'] ?? 1 ) );
        $product    = wc_get_product( $product_id );

        if ( ! $product ) {
            wp_send_json_error();
        }

        $_REQUEST['quantity'] = $qty;

        $core             = new WC_Dynamic_Pricing_Core();
        $regular_price    = $product->get_regular_price();
        $discounted_price = $core->adjust_product_price( $product->get_price(), $product );

        if ( $discounted_price < $regular_price ) {
            $html = '<del>' . wc_price( $regular_price ) . '</del> <ins>' . wc_price( $discounted_price ) . '</ins>';
        } else {
            $html = wc_price( $discounted_price );
        }

        wp_send_json_success( [ 'price_html' => $html ] );
    }

    /* -----------------------------------------------------------------
       LIVE CART SCRIPT
    ----------------------------------------------------------------- */
    private function live_cart_script() {
        return "
        jQuery(function($){
            var timeout;
            $(document).on('change keyup', '.qty', function(){
                clearTimeout(timeout);
                timeout = setTimeout(function(){
                    var items = [];
                    $('.cart_item').each(function(){
                        var name = $(this).find('input[name^=cart]').attr('name');
                        var match = name ? name.match(/\\[([a-z0-9]+)\\]/) : null;
                        var key   = match ? match[1] : '';
                        var qty   = parseInt($(this).find('.qty').val()) || 0;
                        if (key && qty > 0) items.push({key: key, quantity: qty});
                    });

                    $.post(wc_add_to_cart_params.ajax_url, {
                        action: 'wc_dynamic_update_cart',
                        cart_items: items,
                        security: '" . wp_create_nonce( 'wc_dynamic_update_cart' ) . "'
                    }, function(res){
                        if (res.success) {
                            $('.cart-subtotal .amount').html(res.data.subtotal);
                            $('.order-total .amount').html(res.data.total);
                            if (res.data.discount) {
                                $('.cart-discount .amount').html(res.data.discount);
                            }
                            if (res.data.savings) {
                                var $savings = $('.cart-savings');
                                if ($savings.length === 0) {
                                    $('.order-total').before('<tr class=\"cart-savings\"><th colspan=\"2\">' + res.data.savings + '</th></tr>');
                                } else {
                                    $savings.html('<th colspan=\"2\">' + res.data.savings + '</th>');
                                }
                            } else {
                                $('.cart-savings').remove();
                            }
                            $('.woocommerce-cart-form').replaceWith(res.data.fragments.cart);
                            $(document.body).trigger('updated_wc_div');
                        }
                    });
                }, 600);
            });
        });
        ";
    }

    /* -----------------------------------------------------------------
       AJAX: LIVE CART UPDATE
    ----------------------------------------------------------------- */
    public function ajax_update_cart() {
        check_ajax_referer( 'wc_dynamic_update_cart', 'security' );

        $cart_items = $_POST['cart_items'] ?? [];
        $cart       = WC()->cart;

        foreach ( $cart_items as $item ) {
            if ( isset( $cart->get_cart()[ $item['key'] ] ) ) {
                $cart->set_quantity( $item['key'], $item['quantity'], false );
            }
        }

        $cart->calculate_totals();

        ob_start();
        woocommerce_cart_totals();
        $cart_html = ob_get_clean();

        $savings_text = $this->get_savings_text( $cart );

        wp_send_json_success( [
            'subtotal'  => wc_price( $cart->get_subtotal() ),
            'total'     => wc_price( $cart->get_total() ),
            'discount'  => $this->get_discount_amount_html( $cart ),
            'savings'   => $savings_text,
            'fragments' => [ 'cart' => $cart_html ],
        ] );
    }

    /* -----------------------------------------------------------------
       HELPER: Extract savings text
    ----------------------------------------------------------------- */
    private function get_savings_text( $cart ) {
        $savings_text    = '';
        $discount_amount = 0;
        $applied_rules   = [];

        foreach ( $cart->get_fees() as $fee ) {
            if ( stripos( $fee->name, 'Cart Discount' ) === false ) {
                continue;
            }

            $discount_amount += abs( $fee->amount );

            preg_match( '/#([a-z0-9]+)$/', $fee->name, $matches );
            $fee_id = $matches[1] ?? '';
            if ( $fee_id ) {
                $rule = get_transient( $fee_id );
                if ( $rule && is_array( $rule ) ) {
                    $applied_rules[] = $rule;
                }
            }
        }

        if ( ! empty( $applied_rules ) ) {
            foreach ( $applied_rules as $rule ) {
                if ( $rule['discount_type'] === 'percent' ) {
                    $savings_text .= sprintf( __( 'You Save %s%%', 'wc-dynamic-pricing' ), $rule['discount_value'] );
                } else {
                    $savings_text .= sprintf( __( 'You Save %s', 'wc-dynamic-pricing' ), wc_price( $rule['discount_value'] ) );
                }
                $savings_text .= '<br>';
            }
        } elseif ( $discount_amount > 0 && $cart->get_subtotal() > 0 ) {
            $percent = round( ( $discount_amount / $cart->get_subtotal() ) * 100 );
            $savings_text = sprintf( __( 'You Save %s%%', 'wc-dynamic-pricing' ), $percent );
        }

        return $savings_text;
    }

    /* -----------------------------------------------------------------
       HELPER: Get discount amount HTML
    ----------------------------------------------------------------- */
    private function get_discount_amount_html( $cart ) {
        $total_discount = 0;
        foreach ( $cart->get_fees() as $fee ) {
            if ( stripos( $fee->name, 'Cart Discount' ) !== false ) {
                $total_discount += abs( $fee->amount );
            }
        }
        return $total_discount > 0 ? '-' . wc_price( $total_discount ) : '';
    }

    /* -----------------------------------------------------------------
       DISPLAY "YOU SAVE %"
    ----------------------------------------------------------------- */
    public function display_cart_savings() {
        $cart = WC()->cart;
        $savings_text = $this->get_savings_text( $cart );

        if ( $savings_text ) {
            echo '<tr class="cart-savings"><th colspan="2">' . wp_kses_post( $savings_text ) . '</th></tr>';
        }
    }

    /* -----------------------------------------------------------------
       CART NOTICE & SHORTCODES
    ----------------------------------------------------------------- */
    public function cart_notice() {
        $rules = get_option( 'wc_dynamic_rules', [] );
        foreach ( $rules as $rule ) {
            if ( in_array( $rule['type'], [ 'product', 'cart' ], true ) && ! empty( $rule['discount_value'] ) ) {
                wc_print_notice( __( 'You have active discounts!', 'wc-dynamic-pricing' ), 'notice' );
                break;
            }
        }
    }

    public function pricing_table() {
        global $product;
        if ( ! $product ) return;
        echo $this->shortcode_pricing_table( [ 'product_id' => $product->get_id() ] );
    }

    public function shortcode_pricing_table( $atts ) {
        $atts = shortcode_atts( [ 'product_id' => 0 ], $atts );
        $product = wc_get_product( $atts['product_id'] );
        if ( ! $product ) return '';

        $rules = get_option( 'wc_dynamic_rules', [] );
        $table = '<table class="pricing-table"><thead><tr><th>' . __( 'Qty', 'wc-dynamic-pricing' ) . '</th><th>' . __( 'Price', 'wc-dynamic-pricing' ) . '</th><th>' . __( 'Save', 'wc-dynamic-pricing' ) . '</th></tr></thead><tbody>';

        for ( $i = 1; $i <= 10; $i++ ) {
            $base = $product->get_price();
            $discount = 0;
            foreach ( $rules as $rule ) {
                if ( $rule['type'] === 'product' && ( $rule['target'] === 'all' || $rule['target'] == $product->get_id() ) && $i >= $rule['min'] ) {
                    $discount = ( new WC_Dynamic_Pricing_Core() )->calculate_discount( $base, $rule );
                }
            }
            $final = $base - $discount;
            $table .= "<tr><td>{$i}</td><td>" . wc_price( $final * $i ) . "</td><td>" . wc_price( $discount * $i ) . "</td></tr>";
        }
        $table .= '</tbody></table>';
        return $table;
    }

    public function shortcode_discounted_products( $atts ) {
        $atts = shortcode_atts( [ 'limit' => 4 ], $atts );
        $products = wc_get_products( [
            'limit' => $atts['limit'],
            'meta_query' => [ [ 'key' => '_sale_price', 'compare' => 'EXISTS' ] ],
        ] );

        ob_start();
        if ( $products ) {
            echo '<ul class="discounted-products">';
            foreach ( $products as $p ) {
                wc_get_template_part( 'content', 'product' );
            }
            echo '</ul>';
        }
        return ob_get_clean();
    }
}<?php
/**
 * Frontend display and live updates for WooCommerce Dynamic Pricing & Discounts
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Dynamic_Pricing_Frontend {

    public function __construct() {
        // Notices
        add_action( 'woocommerce_before_cart', [ $this, 'cart_notice' ] );

        // Pricing table on product page
        add_action( 'woocommerce_single_product_summary', [ $this, 'pricing_table' ], 25 );

        // Shortcodes
        add_shortcode( 'pricing_table', [ $this, 'shortcode_pricing_table' ] );
        add_shortcode( 'dynamic_discounts', [ $this, 'shortcode_discounted_products' ] );

        // Price HTML: show original + discounted
        add_filter( 'woocommerce_get_price_html', [ $this, 'price_html' ], 100, 2 );

        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // AJAX handlers
        add_action( 'wp_ajax_wc_dynamic_live_price', [ $this, 'ajax_live_price' ] );
        add_action( 'wp_ajax_nopriv_wc_dynamic_live_price', [ $this, 'ajax_live_price' ] );

        add_action( 'wp_ajax_wc_dynamic_update_cart', [ $this, 'ajax_update_cart' ] );
        add_action( 'wp_ajax_nopriv_wc_dynamic_update_cart', [ $this, 'ajax_update_cart' ] );

        // "You Save X%" in cart totals
        add_action( 'woocommerce_cart_totals_after_order_total', [ $this, 'display_cart_savings' ] );
        add_action( 'woocommerce_review_order_after_order_total', [ $this, 'display_cart_savings' ] );
    }

    /* -----------------------------------------------------------------
       ENQUEUE ASSETS
    ----------------------------------------------------------------- */
    public function enqueue_assets() {
        if ( is_product() ) {
            wp_enqueue_style(
                'wc-dynamic-pricing',
                WC_DYNAMIC_PRICING_URL . 'assets/css/style.css',
                [],
                WC_DYNAMIC_PRICING_VERSION
            );
            wp_add_inline_script( 'jquery', $this->live_product_script() );
        }

        if ( is_cart() || is_checkout() ) {
            wp_add_inline_script( 'jquery', $this->live_cart_script() );
        }
    }

    /* -----------------------------------------------------------------
       PRICE HTML – show strikethrough
    ----------------------------------------------------------------- */
    public function price_html( $price_html, $product ) {
        if ( is_admin() || ! $product->is_visible() ) {
            return $price_html;
        }

        $core             = new WC_Dynamic_Pricing_Core();
        $regular_price    = $product->get_regular_price();
        $discounted_price = $core->adjust_product_price( $product->get_price(), $product );

        if ( $discounted_price < $regular_price ) {
            return '<del>' . wc_price( $regular_price ) . '</del> <ins>' . wc_price( $discounted_price ) . '</ins>';
        }

        return $price_html;
    }

    /* -----------------------------------------------------------------
       LIVE PRODUCT PAGE PRICE
    ----------------------------------------------------------------- */
    private function live_product_script() {
        return "
        jQuery(function($){
            var updatePrice = function(){
                var qty = parseInt($('.qty').val()) || 1;
                var data = {
                    action: 'wc_dynamic_live_price',
                    product_id: $('input[name=product_id]').val() || $('[name=add-to-cart]').val(),
                    quantity: qty,
                    security: '" . wp_create_nonce( 'wc_dynamic_live_price' ) . "'
                };
                $.post(wc_add_to_cart_params.ajax_url, data, function(res){
                    if (res.success) {
                        $('.summary .price').html(res.data.price_html);
                    }
                });
            };
            $(document).on('change keyup', '.qty', updatePrice);
            updatePrice();
        });
        ";
    }

    public function ajax_live_price() {
        check_ajax_referer( 'wc_dynamic_live_price', 'security' );

        $product_id = absint( $_POST['product_id'] ?? 0 );
        $qty        = max( 1, intval( $_POST['quantity'] ?? 1 ) );
        $product    = wc_get_product( $product_id );

        if ( ! $product ) {
            wp_send_json_error();
        }

        $_REQUEST['quantity'] = $qty;

        $core             = new WC_Dynamic_Pricing_Core();
        $regular_price    = $product->get_regular_price();
        $discounted_price = $core->adjust_product_price( $product->get_price(), $product );

        if ( $discounted_price < $regular_price ) {
            $html = '<del>' . wc_price( $regular_price ) . '</del> <ins>' . wc_price( $discounted_price ) . '</ins>';
        } else {
            $html = wc_price( $discounted_price );
        }

        wp_send_json_success( [ 'price_html' => $html ] );
    }

    /* -----------------------------------------------------------------
       LIVE CART SCRIPT
    ----------------------------------------------------------------- */
    private function live_cart_script() {
        return "
        jQuery(function($){
            var timeout;
            $(document).on('change keyup', '.qty', function(){
                clearTimeout(timeout);
                timeout = setTimeout(function(){
                    var items = [];
                    $('.cart_item').each(function(){
                        var name = $(this).find('input[name^=cart]').attr('name');
                        var match = name ? name.match(/\\[([a-z0-9]+)\\]/) : null;
                        var key   = match ? match[1] : '';
                        var qty   = parseInt($(this).find('.qty').val()) || 0;
                        if (key && qty > 0) items.push({key: key, quantity: qty});
                    });

                    $.post(wc_add_to_cart_params.ajax_url, {
                        action: 'wc_dynamic_update_cart',
                        cart_items: items,
                        security: '" . wp_create_nonce( 'wc_dynamic_update_cart' ) . "'
                    }, function(res){
                        if (res.success) {
                            $('.cart-subtotal .amount').html(res.data.subtotal);
                            $('.order-total .amount').html(res.data.total);
                            if (res.data.discount) {
                                $('.cart-discount .amount').html(res.data.discount);
                            }
                            if (res.data.savings) {
                                var $savings = $('.cart-savings');
                                if ($savings.length === 0) {
                                    $('.order-total').before('<tr class=\"cart-savings\"><th colspan=\"2\">' + res.data.savings + '</th></tr>');
                                } else {
                                    $savings.html('<th colspan=\"2\">' + res.data.savings + '</th>');
                                }
                            } else {
                                $('.cart-savings').remove();
                            }
                            $('.woocommerce-cart-form').replaceWith(res.data.fragments.cart);
                            $(document.body).trigger('updated_wc_div');
                        }
                    });
                }, 600);
            });
        });
        ";
    }

    /* -----------------------------------------------------------------
       AJAX: LIVE CART UPDATE
    ----------------------------------------------------------------- */
    public function ajax_update_cart() {
        check_ajax_referer( 'wc_dynamic_update_cart', 'security' );

        $cart_items = $_POST['cart_items'] ?? [];
        $cart       = WC()->cart;

        foreach ( $cart_items as $item ) {
            if ( isset( $cart->get_cart()[ $item['key'] ] ) ) {
                $cart->set_quantity( $item['key'], $item['quantity'], false );
            }
        }

        $cart->calculate_totals();

        ob_start();
        woocommerce_cart_totals();
        $cart_html = ob_get_clean();

        $savings_text = $this->get_savings_text( $cart );

        wp_send_json_success( [
            'subtotal'  => wc_price( $cart->get_subtotal() ),
            'total'     => wc_price( $cart->get_total() ),
            'discount'  => $this->get_discount_amount_html( $cart ),
            'savings'   => $savings_text,
            'fragments' => [ 'cart' => $cart_html ],
        ] );
    }

    /* -----------------------------------------------------------------
       HELPER: Extract savings text
    ----------------------------------------------------------------- */
    private function get_savings_text( $cart ) {
        $savings_text    = '';
        $discount_amount = 0;
        $applied_rules   = [];

        foreach ( $cart->get_fees() as $fee ) {
            if ( stripos( $fee->name, 'Cart Discount' ) === false ) {
                continue;
            }

            $discount_amount += abs( $fee->amount );

            preg_match( '/#([a-z0-9]+)$/', $fee->name, $matches );
            $fee_id = $matches[1] ?? '';
            if ( $fee_id ) {
                $rule = get_transient( $fee_id );
                if ( $rule && is_array( $rule ) ) {
                    $applied_rules[] = $rule;
                }
            }
        }

        if ( ! empty( $applied_rules ) ) {
            foreach ( $applied_rules as $rule ) {
                if ( $rule['discount_type'] === 'percent' ) {
                    $savings_text .= sprintf( __( 'You Save %s%%', 'wc-dynamic-pricing' ), $rule['discount_value'] );
                } else {
                    $savings_text .= sprintf( __( 'You Save %s', 'wc-dynamic-pricing' ), wc_price( $rule['discount_value'] ) );
                }
                $savings_text .= '<br>';
            }
        } elseif ( $discount_amount > 0 && $cart->get_subtotal() > 0 ) {
            $percent = round( ( $discount_amount / $cart->get_subtotal() ) * 100 );
            $savings_text = sprintf( __( 'You Save %s%%', 'wc-dynamic-pricing' ), $percent );
        }

        return $savings_text;
    }

    /* -----------------------------------------------------------------
       HELPER: Get discount amount HTML
    ----------------------------------------------------------------- */
    private function get_discount_amount_html( $cart ) {
        $total_discount = 0;
        foreach ( $cart->get_fees() as $fee ) {
            if ( stripos( $fee->name, 'Cart Discount' ) !== false ) {
                $total_discount += abs( $fee->amount );
            }
        }
        return $total_discount > 0 ? '-' . wc_price( $total_discount ) : '';
    }

    /* -----------------------------------------------------------------
       DISPLAY "YOU SAVE %"
    ----------------------------------------------------------------- */
    public function display_cart_savings() {
        $cart = WC()->cart;
        $savings_text = $this->get_savings_text( $cart );

        if ( $savings_text ) {
            echo '<tr class="cart-savings"><th colspan="2">' . wp_kses_post( $savings_text ) . '</th></tr>';
        }
    }

    /* -----------------------------------------------------------------
       CART NOTICE & SHORTCODES
    ----------------------------------------------------------------- */
    public function cart_notice() {
        $rules = get_option( 'wc_dynamic_rules', [] );
        foreach ( $rules as $rule ) {
            if ( in_array( $rule['type'], [ 'product', 'cart' ], true ) && ! empty( $rule['discount_value'] ) ) {
                wc_print_notice( __( 'You have active discounts!', 'wc-dynamic-pricing' ), 'notice' );
                break;
            }
        }
    }

    public function pricing_table() {
        global $product;
        if ( ! $product ) return;
        echo $this->shortcode_pricing_table( [ 'product_id' => $product->get_id() ] );
    }

    public function shortcode_pricing_table( $atts ) {
        $atts = shortcode_atts( [ 'product_id' => 0 ], $atts );
        $product = wc_get_product( $atts['product_id'] );
        if ( ! $product ) return '';

        $rules = get_option( 'wc_dynamic_rules', [] );
        $table = '<table class="pricing-table"><thead><tr><th>' . __( 'Qty', 'wc-dynamic-pricing' ) . '</th><th>' . __( 'Price', 'wc-dynamic-pricing' ) . '</th><th>' . __( 'Save', 'wc-dynamic-pricing' ) . '</th></tr></thead><tbody>';

        for ( $i = 1; $i <= 10; $i++ ) {
            $base = $product->get_price();
            $discount = 0;
            foreach ( $rules as $rule ) {
                if ( $rule['type'] === 'product' && ( $rule['target'] === 'all' || $rule['target'] == $product->get_id() ) && $i >= $rule['min'] ) {
                    $discount = wc_dynamic_calculate_discount( $base, $rule );
                }
            }
            $final = $base - $discount;
            $table .= "<tr><td>{$i}</td><td>" . wc_price( $final * $i ) . "</td><td>" . wc_price( $discount * $i ) . "</td></tr>";
        }
        $table .= '</tbody></table>';
        return $table;
    }

    public function shortcode_discounted_products( $atts ) {
        $atts = shortcode_atts( [ 'limit' => 4 ], $atts );
        $products = wc_get_products( [
            'limit' => $atts['limit'],
            'meta_query' => [ [ 'key' => '_sale_price', 'compare' => 'EXISTS' ] ],
        ] );

        ob_start();
        if ( $products ) {
            echo '<ul class="discounted-products">';
            foreach ( $products as $p ) {
                wc_get_template_part( 'content', 'product' );
            }
            echo '</ul>';
        }
        return ob_get_clean();
    }
}
