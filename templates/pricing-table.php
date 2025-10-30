<?php
if (!defined('ABSPATH')) exit;
global $product;
if (!$product) return;

$rules = get_option('wc_dynamic_rules', []);
?>
<table class="pricing-table">
    <thead>
        <tr>
            <th><?php _e('Quantity', 'wc-dynamic-pricing'); ?></th>
            <th><?php _e('Price', 'wc-dynamic-pricing'); ?></th>
            <th><?php _e('You Save', 'wc-dynamic-pricing'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php for ($i = 1; $i <= 10; $i++): ?>
            <?php
            $base = $product->get_price();
            $discount = 0;
            foreach ($rules as $rule) {
                if ($rule['type'] === 'product' && ($rule['target'] === 'all' || $rule['target'] == $product->get_id()) && $i >= $rule['min']) {
                    $discount = ($rule['discount_type'] === 'percent') ? ($base * $rule['discount_value'] / 100) : $rule['discount_value'];
                }
            }
            $final = $base - $discount;
            ?>
            <tr>
                <td><?php echo $i; ?></td>
                <td><?php echo wc_price($final * $i); ?></td>
                <td><?php echo wc_price($discount * $i); ?></td>
            </tr>
        <?php endfor; ?>
    </tbody>
</table>
