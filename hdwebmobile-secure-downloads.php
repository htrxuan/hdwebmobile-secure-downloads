<?php

/**
 * Plugin Name: HDWebmobile Secure Downloads
 * Plugin URI: https://hdwebmobile.com/plugins/hdwebmobile-secure-downloads/
 * Description: Let customers recover their WooCommerce download links using their order number and key -- no file path is ever read from a request.
 * Version: 1.0.0
 * Author: htrxuan - Han Tran
 * Author URI: https://hdwebmobile.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hdwebmobile-secure-downloads
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.9
 */

namespace htrxuan\hdsd;

if (!defined('ABSPATH')) {
    exit;
}

define('HDSD_VERSION', '1.0.0');
define('HDSD_PLUGIN_FILE', __FILE__);
define('HDSD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HDSD_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once HDSD_PLUGIN_DIR . 'includes/class-hdsd-activator.php';

register_activation_hook(__FILE__, array(HDSD_Activator::class, 'activate'));

add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', HDSD_PLUGIN_FILE, true);
    }
});

add_action('plugins_loaded', function () {
    require_once HDSD_PLUGIN_DIR . 'includes/class-hdsd-core.php';
    HDSD_Core::get_instance();
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $donate_link = '<a href="https://paypal.me/htrxuan/20" target="_blank" rel="noopener noreferrer">' . esc_html__('Donate', 'hdwebmobile-secure-downloads') . '</a>';
    array_unshift($links, $donate_link);
    return $links;
});
