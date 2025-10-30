=== WooCommerce Dynamic Pricing & Discounts ===
Contributors: Caaza Developers
Tags: woocommerce, dynamic pricing, discounts, bulk pricing, bogo
Requires at least: 5.6
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced dynamic pricing rules for WooCommerce: quantity discounts, cart totals, user roles, BOGO, and more.

== Description ==

Create powerful pricing rules:
* Quantity-based tiers
* Cart total discounts
* Role-based pricing
* Date-limited offers
* Pricing tables on product pages
* Shortcodes & notices

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin
3. Go to **Dynamic Pricing** in the admin menu

== Changelog ==

 * = 1.1.3 - 2025-10-29 =
 * - **Fixed**: Product page now shows **original price (strikethrough) + discounted price**.
 *   - Uses `get_regular_price()` (unfiltered) for original price.
 *   - Live AJAX update includes full `<del><ins>` HTML.
 *

Feature,Status
HPOS Compatibility,Fully supported
Admin Dashboard,Complete CRUD UI
Pricing Rules Engine,"Product, Cart, Shipping, Role, Date"
Frontend Display,"Pricing tables, notices, shortcodes"
Code Quality,"Modular, secure, translation-ready"
Version Bump,1.0.0 → 1.1.0

= 1.0.0 =
* Initial release

*****************************************************************************

| File | Purpose |
woo-dynamic-pricing-discounts.php | Main plugin header + loader. Includes all classes.
readme.txt,"WordPress.org description, changelog, installation."
license.txt,GPL-2.0+ license.
uninstall.php,Deletes wc_dynamic_rules option on uninstall.
assets/css/style.css,Styles pricing table on product pages.
assets/css/admin.css,Clean admin dashboard UI.
assets/js/admin.js,"AJAX add/edit/delete rules, dynamic form."
includes/class-wc-dynamic-pricing-core.php,"All pricing logic (hooks, calculations)."
includes/class-wc-dynamic-pricing-admin.php,"Admin menu, dashboard, AJAX, settings."
includes/class-wc-dynamic-pricing-frontend.php,"Pricing tables, notices, shortcodes."
includes/helpers.php,"human_target(), calculate_discount(), etc."
templates/pricing-table.php,Theme-overridable via your-theme/woocommerce/dynamic-pricing/pricing-table.php
languages/*.pot,"For translations (_e(), __() ready)."


woo-dynamic-pricing-discounts/
│
├── woo-dynamic-pricing-discounts.php          Main plugin file (entry point)
├── readme.txt                                 Standard WP plugin readme
├── license.txt                                GPL license
├── uninstall.php                              Cleanup on uninstall
│
├── assets/
│   ├── css/
│   │   ├── style.css                      Frontend pricing table styles
│   │   └── admin.css                      Admin dashboard styles
│   │
│   ├── js/
│   │   ├── admin.js                       Admin dashboard JavaScript (AJAX)
│   │   └── frontend.js                    (Optional) Future frontend enhancements
│   │
│   └── images/
│       └── icon-256x256.png               Plugin icon for WP.org
│
├── includes/
│   ├── class-wc-dynamic-pricing-core.php      Core pricing logic
│   ├── class-wc-dynamic-pricing-admin.php     Admin UI, menu, AJAX
│   ├── class-wc-dynamic-pricing-frontend.php  Frontend display (tables, notices)
│   └── helpers.php                            Shared utility functions
│
├── languages/
│   ├── wc-dynamic-pricing.pot                 Translation template
│   └── wc-dynamic-pricing-en_US.po            Example translation
│
├── templates/
│   └── pricing-table.php                      Overridable pricing table template
│
└── tests/
    └── bootstrap.php                          (Optional) PHPUnit setup
