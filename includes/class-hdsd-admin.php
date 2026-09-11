<?php

namespace htrxuan\hdsd;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The hub tab. All settings are written through the WordPress Settings API (`options.php`),
 * which performs its own `manage_options` capability check and nonce verification -- there is
 * no custom write path.
 */
class HDSD_Admin
{
    const OPTION_KEY = 'hdsd_settings';

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
        require_once HDSD_PLUGIN_DIR . 'includes/class-hdsd-hub.php';
        add_filter('hdwebmobile_hub_tabs', array($this, 'register_hub_tabs'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public static function defaults()
    {
        return array(
            'show_on_thankyou' => 1,
            'lookup_page_url'  => '',
        );
    }

    public static function get_options()
    {
        $opts = get_option(self::OPTION_KEY, array());
        return wp_parse_args(is_array($opts) ? $opts : array(), self::defaults());
    }

    public static function get_option($key)
    {
        $opts = self::get_options();
        return isset($opts[$key]) ? $opts[$key] : null;
    }

    public function register_settings()
    {
        register_setting('hdsd_group', self::OPTION_KEY, array(
            'type'              => 'array',
            'sanitize_callback' => array($this, 'sanitize'),
            'default'           => self::defaults(),
        ));
    }

    public function sanitize($input)
    {
        return array(
            'show_on_thankyou' => !empty($input['show_on_thankyou']) ? 1 : 0,
            'lookup_page_url'  => isset($input['lookup_page_url']) ? esc_url_raw(trim($input['lookup_page_url'])) : '',
        );
    }

    public function register_hub_tabs($tabs)
    {
        $tabs['secure-downloads'] = array(
            'label'  => __('Secure Downloads', 'hdwebmobile-secure-downloads'),
            'order'  => 49,
            'render' => array($this, 'render_page'),
        );
        return $tabs;
    }

    public function render_page()
    {
        $o = self::get_options();
        ?>
        <p><?php esc_html_e('Let customers recover their WooCommerce download links using their order number and order key -- no WordPress account needed, and no file path is ever read from a request.', 'hdwebmobile-secure-downloads'); ?></p>
        <p><code>[hdsd_my_downloads]</code> <?php esc_html_e('-- place this shortcode on a page to let customers look up their downloads manually.', 'hdwebmobile-secure-downloads'); ?></p>

        <form method="post" action="options.php">
            <?php settings_fields('hdsd_group'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Order-received page', 'hdwebmobile-secure-downloads'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_on_thankyou]" value="1" <?php checked(!empty($o['show_on_thankyou'])); ?> />
                            <?php esc_html_e('Show download links on the order confirmation ("thank you") page', 'hdwebmobile-secure-downloads'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hdsd_lookup_page_url"><?php esc_html_e('Lookup page URL', 'hdwebmobile-secure-downloads'); ?></label></th>
                    <td>
                        <input type="url" id="hdsd_lookup_page_url" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[lookup_page_url]" value="<?php echo esc_attr($o['lookup_page_url']); ?>" placeholder="https://yourstore.com/find-my-downloads/" />
                        <p class="description"><?php esc_html_e('The URL of a page containing the [hdsd_my_downloads] shortcode. If set, order emails to the customer mention it as a way to recover a lost download link. Leave blank to skip the email mention.', 'hdwebmobile-secure-downloads'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Save Settings', 'hdwebmobile-secure-downloads')); ?>
        </form>
        <?php
    }
}
