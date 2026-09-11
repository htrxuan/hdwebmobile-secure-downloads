<?php

namespace htrxuan\hdsd;

if (!defined('ABSPATH')) {
    exit;
}

final class HDSD_Core
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->includes();
        $this->init_hooks();
    }

    private function __clone()
    {
    }

    private function includes()
    {
        require_once HDSD_PLUGIN_DIR . 'includes/class-hdsd-lookup.php';
        require_once HDSD_PLUGIN_DIR . 'includes/class-hdsd-admin.php';
        require_once HDSD_PLUGIN_DIR . 'includes/class-hdsd-frontend.php';
    }

    private function init_hooks()
    {
        add_action('admin_notices', array($this, 'render_missing_woocommerce_notice'));

        if (!class_exists('WooCommerce')) {
            return;
        }

        // HDSD_Admin owns the hdwebmobile_hub_tabs registration used by the shared hub
        // page, so it must load unconditionally (not only when is_admin()).
        HDSD_Admin::get_instance();
        HDSD_Frontend::get_instance();
    }

    public function render_missing_woocommerce_notice()
    {
        $screen = get_current_screen();
        if (!$screen || 'plugins' !== $screen->id) {
            return;
        }

        if (!get_transient('hdsd_wc_missing_notice')) {
            return;
        }
        delete_transient('hdsd_wc_missing_notice');
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php esc_html_e('HDWebmobile Secure Downloads requires WooCommerce to be installed and active. The plugin has been deactivated.', 'hdwebmobile-secure-downloads'); ?>
            </p>
        </div>
        <?php
    }
}
