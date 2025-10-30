<?php
if (!defined('ABSPATH')) exit;

use Automattic\WooCommerce\Utilities\OrderUtil; // For HPOS compatibility

class WC_Dynamic_Pricing_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_ajax']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function admin_menu() {
        add_menu_page(
            __('Dynamic Pricing', 'wc-dynamic-pricing'),
            __('Dynamic Pricing', 'wc-dynamic-pricing'),
            'manage_woocommerce',
            'wc-dynamic-pricing',
            [$this, 'dashboard_page'],
            'dashicons-tag',
            57
        );
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_wc-dynamic-pricing') return;

        wp_enqueue_style('wc-dynamic-admin', WC_DYNAMIC_PRICING_URL . 'assets/css/admin.css', [], WC_DYNAMIC_PRICING_VERSION);
        wp_enqueue_script('wc-dynamic-admin', WC_DYNAMIC_PRICING_URL . 'assets/js/admin.js', ['jquery'], WC_DYNAMIC_PRICING_VERSION, true);
        wp_localize_script('wc-dynamic-admin', 'wcDynamic', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_dynamic_nonce'),
            'initial_rules' => get_option('wc_dynamic_rules', [])
        ]);
    }

    public function dashboard_page() {
        $rules = get_option('wc_dynamic_rules', []);
        ?>
        <div class="wrap">
            <h1><?php _e('Dynamic Pricing & Discounts', 'wc-dynamic-pricing'); ?></h1>
            <a href="#" id="add-new-rule" class="page-title-action"><?php _e('Add New Rule', 'wc-dynamic-pricing'); ?></a>
            <hr class="wp-header-end">

            <div id="rule-form" style="display:none;">
                <?php $this->render_rule_form(); ?>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th>ID</th><th>Type</th><th>Target</th><th>Condition</th><th>Discount</th><th>Actions</th>
                </tr></thead>
                <tbody id="rules-list">
                    <?php $this->render_rules_table($rules); ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_rule_form($rule = [], $idx = '') {
        $rule = wp_parse_args($rule, [
            'type' => 'product', 'target' => 'all', 'condition' => 'quantity',
            'min' => '', 'discount_type' => 'percent', 'discount_value' => '',
            'role' => '', 'date_from' => '', 'date_to' => ''
        ]);
        ?>
        <form id="rule-form-inner">
            <input type="hidden" name="rule_index" value="<?php echo esc_attr($idx); ?>">
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Rule Type', 'wc-dynamic-pricing'); ?></label></th>
                    <td>
                        <select name="type" class="rule-type">
                            <option value="product" <?php selected($rule['type'], 'product'); ?>><?php _e('Product', 'wc-dynamic-pricing'); ?></option>
                            <option value="cart" <?php selected($rule['type'], 'cart'); ?>><?php _e('Cart', 'wc-dynamic-pricing'); ?></option>
                            <option value="shipping" <?php selected($rule['type'], 'shipping'); ?>><?php _e('Shipping', 'wc-dynamic-pricing'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr class="target-row">
                    <th><label><?php _e('Target', 'wc-dynamic-pricing'); ?></label></th>
                    <td>
                        <select name="target" class="target-select">
                            <option value="all"><?php _e('All Products', 'wc-dynamic-pricing'); ?></option>
                            <?php
                            $products = wc_get_products(['limit' => -1, 'status' => 'publish']);
                            foreach ($products as $p) {
                                echo '<option value="'.$p->get_id().'" '.selected($rule['target'], $p->get_id(), false).'>'.esc_html($p->get_name()).'</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Condition', 'wc-dynamic-pricing'); ?></label></th>
                    <td>
                        <select name="condition" class="condition-select">
                            <option value="quantity" <?php selected($rule['condition'], 'quantity'); ?>><?php _e('Minimum Quantity', 'wc-dynamic-pricing'); ?></option>
                            <option value="amount" <?php selected($rule['condition'], 'amount'); ?>><?php _e('Minimum Amount', 'wc-dynamic-pricing'); ?></option>
                            <option value="role" <?php selected($rule['condition'], 'role'); ?>><?php _e('User Role', 'wc-dynamic-pricing'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr class="cond-quantity cond-amount">
                    <th><label><?php _e('Min Value', 'wc-dynamic-pricing'); ?></label></th>
                    <td><input type="number" name="min" value="<?php echo esc_attr($rule['min']); ?>" step="any"></td>
                </tr>
                <tr class="cond-role" style="display:none;">
                    <th><label><?php _e('Role', 'wc-dynamic-pricing'); ?></label></th>
                    <td><?php wp_dropdown_roles($rule['role']); ?><input type="hidden" name="role" value="<?php echo esc_attr($rule['role']); ?>"></td>
                </tr>
                <tr>
                    <th><label><?php _e('Discount Type', 'wc-dynamic-pricing'); ?></label></th>
                    <td>
                        <select name="discount_type">
                            <option value="percent" <?php selected($rule['discount_type'], 'percent'); ?>><?php _e('Percentage', 'wc-dynamic-pricing'); ?></option>
                            <option value="fixed" <?php selected($rule['discount_type'], 'fixed'); ?>><?php _e('Fixed Amount', 'wc-dynamic-pricing'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Discount Value', 'wc-dynamic-pricing'); ?></label></th>
                    <td><input type="number" name="discount_value" value="<?php echo esc_attr($rule['discount_value']); ?>" step="any"></td>
                </tr>
                <tr>
                    <th><label><?php _e('Valid From', 'wc-dynamic-pricing'); ?></label></th>
                    <td><input type="date" name="date_from" value="<?php echo esc_attr($rule['date_from']); ?>"></td>
                </tr>
                <tr>
                    <th><label><?php _e('Valid To', 'wc-dynamic-pricing'); ?></label></th>
                    <td><input type="date" name="date_to" value="<?php echo esc_attr($rule['date_to']); ?>"></td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php $idx !== '' ? _e('Update', 'wc-dynamic-pricing') : _e('Add Rule', 'wc-dynamic-pricing'); ?></button>
                <a href="#" id="cancel-rule" class="button"><?php _e('Cancel', 'wc-dynamic-pricing'); ?></a>
            </p>
        </form>
        <?php
    }

    private function render_rules_table($rules) {
        if (empty($rules)) {
            echo '<tr><td colspan="6">' . __('No rules yet.', 'wc-dynamic-pricing') . '</td></tr>';
            return;
        }
        foreach ($rules as $idx => $rule) {
            echo '<tr data-index="'.$idx.'">
                <td>'.($idx+1).'</td>
                <td>'.ucfirst($rule['type']).'</td>
                <td>'.wc_dynamic_human_target($rule).'</td>
                <td>'.wc_dynamic_human_condition($rule).'</td>
                <td>'.wc_dynamic_human_discount($rule).'</td>
                <td><a href="#" class="edit-rule">Edit</a> | <a href="#" class="delete-rule" style="color:#a00;">Delete</a></td>
            </tr>';
        }
    }

    public function register_ajax() {
        add_action('wp_ajax_wc_dynamic_save_rule', [$this, 'ajax_save_rule']);
        add_action('wp_ajax_wc_dynamic_delete_rule', [$this, 'ajax_delete_rule']);
        add_action('wp_ajax_wc_dynamic_get_rule', [$this, 'ajax_get_rule']);

        // Live price on product page
        add_action('wp_ajax_wc_dynamic_live_price', [$this, 'ajax_live_price']);
        add_action('wp_ajax_nopriv_wc_dynamic_live_price', [$this, 'ajax_live_price']);
    }

    public function ajax_live_price() {
        check_ajax_referer('wc_dynamic_live_price', 'security');

        $product_id = absint($_POST['product_id']);
        $qty        = max(1, intval($_POST['quantity']));
        $product    = wc_get_product($product_id);

        if (!$product) {
            wp_send_json_error();
        }

        // Force quantity for this request
        $_REQUEST['quantity'] = $qty;

        $core   = new WC_Dynamic_Pricing_Core();
        $price  = $core->adjust_product_price($product->get_price(), $product);
        $html   = wc_price($price);

        wp_send_json_success(['price_html' => $html]);
    }
    
    public function ajax_save_rule() {
        check_ajax_referer('wc_dynamic_nonce', 'nonce');
        $data = $_POST['rule'];
        $rules = get_option('wc_dynamic_rules', []);

        $rule = [
            'type' => sanitize_key($data['type']),
            'target' => $data['target'] === 'all' ? 'all' : absint($data['target']),
            'condition' => sanitize_key($data['condition']),
            'min' => in_array($data['condition'], ['quantity', 'amount']) ? floatval($data['min']) : '',
            'discount_type' => sanitize_key($data['discount_type']),
            'discount_value' => floatval($data['discount_value']),
            'role' => $data['condition'] === 'role' ? sanitize_text_field($data['role']) : '',
            'date_from' => sanitize_text_field($data['date_from']),
            'date_to' => sanitize_text_field($data['date_to']),
        ];

        if (isset($data['index']) && $data['index'] !== '') {
            $rules[intval($data['index'])] = $rule;
        } else {
            $rules[] = $rule;
        }

        update_option('wc_dynamic_rules', $rules);
        wp_send_json_success(['rules' => $rules]);
    }

    public function ajax_delete_rule() {
        check_ajax_referer('wc_dynamic_nonce', 'nonce');
        $idx = intval($_POST['index']);
        $rules = get_option('wc_dynamic_rules', []);
        if (isset($rules[$idx])) {
            unset($rules[$idx]);
            $rules = array_values($rules);
            update_option('wc_dynamic_rules', $rules);
        }
        wp_send_json_success(['rules' => $rules]);
    }

    public function ajax_get_rule() {
        check_ajax_referer('wc_dynamic_nonce', 'nonce');
        $idx = intval($_POST['index']);
        $rules = get_option('wc_dynamic_rules', []);
        if (isset($rules[$idx])) {
            wp_send_json_success($rules[$idx]);
        }
        wp_send_json_error();
    }
}
