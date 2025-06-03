<?php
/**
 * Plugin Name: WooCommerce Currency Switcher
 * Description: Плъгин за смяна на валута за WooCommerce.
 * Version: 1.0
 * Author: Striweb
 */

if (!defined('ABSPATH')) exit;

define('CURRENCY_SWITCHER_DIR', plugin_dir_path(__FILE__));

require_once CURRENCY_SWITCHER_DIR . 'includes/functions.php';

function cs_enqueue_assets() {
    wp_enqueue_style('cs-style', plugin_dir_url(__FILE__) . 'assets/style.css');
}
add_action('wp_enqueue_scripts', 'cs_enqueue_assets');

if (is_admin()) {
  require_once CURRENCY_SWITCHER_DIR . 'includes/admin-page.php';
}