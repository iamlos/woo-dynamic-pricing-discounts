<?php
if (!defined('ABSPATH')) exit;

function wc_dynamic_human_target($rule) {
    if ($rule['target'] === 'all') return __('All Products', 'wc-dynamic-pricing');
    $product = wc_get_product($rule['target']);
    return $product ? $product->get_name() : '#' . $rule['target'];
}

function wc_dynamic_human_condition($rule) {
    if ($rule['condition'] === 'role' && !empty($rule['role'])) {
        $r = get_role($rule['role']);
        return $r ? $r->name : $rule['role'];
    }
    $map = [
        'quantity' => __('Qty ≥ %s', 'wc-dynamic-pricing'),
        'amount'   => __('Total ≥ %s', 'wc-dynamic-pricing'),
    ];
    return isset($map[$rule['condition']]) ? sprintf($map[$rule['condition']], $rule['min']) : $rule['condition'];
}

function wc_dynamic_human_discount($rule) {
    return $rule['discount_type'] === 'percent'
        ? $rule['discount_value'] . '%'
        : wc_price($rule['discount_value']);
}

function wc_dynamic_calculate_discount($base, $rule) {
    return $rule['discount_type'] === 'percent'
        ? ($base * $rule['discount_value']) / 100
        : $rule['discount_value'];
}
