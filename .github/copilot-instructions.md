# AI Agent Instructions for WooCommerce Dynamic Pricing & Discounts

## Project Overview
This WordPress plugin extends WooCommerce with dynamic pricing and discount capabilities. The core functionality is built around a rule engine that can modify prices based on various conditions.

## Project Structure & Module Organization
The plugin boots from `woo-dynamic-pricing-discounts.php`, which registers hooks and loads the classes under `includes/`. Core pricing logic sits in `includes/class-wc-dynamic-pricing-core.php`, while admin, frontend, and helper routines live in sibling files. Static assets are grouped under `assets/` (`css/`, `js/`, and `images/`), and can be enqueued selectively. Public markup is rendered via `templates/pricing-table.php`, overridable through a theme at `woocommerce/dynamic-pricing/pricing-table.php`. Language files under `languages/` keep translations in sync. Bootstrap code for automated tests resides in `tests/bootstrap.php`.

## Build, Test, and Development Commands
Run the plugin inside a WordPress sandbox (e.g. `wp-env start`) that mounts this directory into `/wp-content/plugins/woo-dynamic-pricing-discounts`. Activate with `wp plugin activate woo-dynamic-pricing-discounts`. Before committing, lint modified PHP files with `php -l path/to/file.php`. When preparing a release, package the plugin using `zip -r dist/woo-dynamic-pricing-discounts.zip . -x '*.git*' 'tests/*'`. Use `npm run dev` only if you introduce tooling; otherwise rely on the checked-in assets.

## Coding Style & Naming Conventions
Follow WordPress PHP standards: four-space indentation, K&R braces, and `snake_case` function names. Use `WC_Dynamic_Pricing_*` for class names and prefix globals with `wc_dynamic_` to avoid collisions. Escape output with `esc_html__`, `wp_kses_post`, or `wc_price`, and sanitize input using `sanitize_text_field` or `wc_clean`. Keep translations wrapped in `__()` or `_e()` with the `wc-dynamic-pricing` text domain.

## Testing Guidelines
The test scaffold expects PHPUnit with the WordPress test suite. After configuring the suite (e.g. via `wp scaffold plugin-tests` and `bin/install-wp-tests.sh`), run `vendor/bin/phpunit --bootstrap tests/bootstrap.php`. Name new tests `tests/test-*.php` and cover both rule evaluation and display rendering. Treat complex discount scenarios as separate test cases to keep assertions focused.

## Commit & Pull Request Guidelines
Write imperative commit subjects under 72 characters (e.g. `Add role-based cart discount rules`). Reference tickets with `Refs #123` or `Fixes #123` when applicable. Pull requests should describe the behavior change, outline test coverage, and include before/after screenshots for UI adjustments. Ensure PRs remain scope-limited; split large feature work into reviewable increments.

## Security & Configuration Tips
Never trust user input: validate rule values before persistence and escape admin notices. Use nonces for AJAX actions registered in `class-wc-dynamic-pricing-admin.php`. When sharing configuration exports, strip customer-identifying data and rotate API keys prior to committing sample files.

## Key Components

### Core Pricing Engine
- Main class: `WC_Dynamic_Pricing_Core` in `includes/class-wc-dynamic-pricing-core.php`
- Handles price adjustments at both product and cart levels
- Uses WordPress transients for temporary discount storage

### Rule Structure
Rules are stored in WordPress options table as `wc_dynamic_rules` with the following components:
- `type`: 'product', 'cart', or 'shipping'
- `condition`: 'amount' or quantity-based
- `target`: product ID or 'all'
- `discount_type`: 'percent' or fixed amount
- `discount_value`: numeric value
- Additional conditions: role, date_from, date_to, min value

## Key Integration Points

### WooCommerce Hooks
The plugin integrates at several key points:
```php
add_filter('woocommerce_product_get_price', [...])
add_filter('woocommerce_cart_item_price', [...])
add_action('woocommerce_cart_calculate_fees', [...])
add_filter('woocommerce_shipping_methods', [...])
```

### Transient Cleanup
Cart-related transients are cleaned up on:
- Cart emptied
- Cart updated
- WooCommerce session cleanup

## Development Patterns

### Price Calculations
1. Always use `apply_rules_to_price()` for consistent price modifications
2. Ensure prices never go below 0 (enforced in core)
3. Cache results using WordPress transients for performance

### Rule Processing
1. Check conditions in order: role → date range → minimum value
2. Use the `matches_target()` method to validate product rules
3. Calculate discounts with `calculate_discount()` method

### Cart Interactions
1. Use `get_current_quantity()` to handle both product page and cart contexts
2. Generate unique fee IDs using rule hash + total for tracking
3. Always check for admin context with `is_admin() && !wp_doing_ajax()`

## Common Pitfalls
1. Cart calculation hooks may fire multiple times - use transients carefully
2. Price filters affect both display and calculations - consider context
3. Remember to handle both regular and sale prices consistently
4. Be careful with floating point comparisons in price calculations

## Testing Guidelines
1. Test price adjustments in both product and cart contexts
2. Verify role-based restrictions work with different user roles
3. Ensure date-based rules handle timezone differences correctly
4. Check transient cleanup prevents stale discount applications