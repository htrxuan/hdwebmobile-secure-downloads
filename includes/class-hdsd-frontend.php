<?php

namespace htrxuan\hdsd;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Everywhere a customer can actually see their download links.
 *
 * Two different trust boundaries, handled two different (correct) ways:
 *
 *  1. The order-received ("thank you") page. WooCommerce's own checkout shortcode already
 *     validates `hash_equals($order->get_order_key(), $_GET['key'])` BEFORE it ever fires the
 *     `woocommerce_thankyou` action (confirmed directly in
 *     includes/shortcodes/class-wc-shortcode-checkout.php and templates/checkout/thankyou.php
 *     -- the action only fires inside the branch reached after a real, key-matched $order was
 *     found). So re-deriving the order from the id this hook receives is safe without this
 *     plugin re-checking the key itself here -- WooCommerce already gated the entire page.
 *
 *  2. The `[hdsd_my_downloads]` manual lookup (someone who lost the email/closed the tab).
 *     This is NOT on a WC-gated page, so it goes through HDSD_Lookup::resolve_order(), which
 *     re-does WooCommerce's own hash_equals() ownership check explicitly.
 */
final class HDSD_Frontend
{

    const FIELD_ORDER = 'hdsd_order';
    const FIELD_KEY   = 'hdsd_key';

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
        add_shortcode('hdsd_my_downloads', array($this, 'shortcode'));

        if (HDSD_Admin::get_option('show_on_thankyou')) {
            add_action('woocommerce_thankyou', array($this, 'render_on_thankyou'), 20);
        }

        $lookup_url = (string) HDSD_Admin::get_option('lookup_page_url');
        if ('' !== $lookup_url) {
            add_action('woocommerce_email_after_order_table', array($this, 'render_email_reminder'), 20, 2);
        }
    }

    /* ---------- order-received page: WC already validated the key for this page ---------- */

    public function render_on_thankyou($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
            return;
        }
        $items = HDSD_Lookup::get_items($order);
        if (empty($items)) {
            return;
        }
        echo '<section class="hdsd-downloads">';
        echo '<h2>' . esc_html__('Your downloads', 'hdwebmobile-secure-downloads') . '</h2>';
        echo $this->render_items_table($items); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_items_table() escapes every dynamic value at output.
        echo '</section>';
    }

    /* ---------- order emails: a reminder pointing at the manual lookup, customer copy only -- */

    public function render_email_reminder($order, $sent_to_admin)
    {
        if ($sent_to_admin || !$order instanceof \WC_Order) {
            return;
        }
        if (empty(HDSD_Lookup::get_items($order))) {
            return;
        }
        $url = (string) HDSD_Admin::get_option('lookup_page_url');
        if ('' === $url) {
            return;
        }
        printf(
            '<p>%s</p>',
            wp_kses_post(sprintf(
                /* translators: %s: the "find my downloads" page URL */
                __('Lost your download link? Visit %s and enter your order number and order key (both shown above) to get it again.', 'hdwebmobile-secure-downloads'),
                '<a href="' . esc_url($url) . '">' . esc_html($url) . '</a>'
            ))
        );
    }

    /* ---------- [hdsd_my_downloads]: manual lookup, explicitly re-checked ---------- */

    public function shortcode()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only lookup; ownership is the real gate (HDSD_Lookup::resolve_order()'s hash_equals() check), rate-limited, not a state change a nonce would protect.
        $submitted_order = isset($_GET[self::FIELD_ORDER]) ? sanitize_text_field(wp_unslash($_GET[self::FIELD_ORDER])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
        $submitted_key = isset($_GET[self::FIELD_KEY]) ? sanitize_text_field(wp_unslash($_GET[self::FIELD_KEY])) : '';

        ob_start();

        if ('' !== $submitted_order || '' !== $submitted_key) {
            $result = HDSD_Lookup::resolve_order($submitted_order, $submitted_key);
            if (is_wp_error($result)) {
                echo '<p class="hdsd-error">' . esc_html($result->get_error_message()) . '</p>';
                $this->render_form($submitted_order);
            } else {
                $items = HDSD_Lookup::get_items($result);
                if (empty($items)) {
                    echo '<p>' . esc_html__('This order has no downloads currently available (they may have expired or reached their download limit).', 'hdwebmobile-secure-downloads') . '</p>';
                } else {
                    echo '<h3>' . esc_html__('Your downloads', 'hdwebmobile-secure-downloads') . '</h3>';
                    echo $this->render_items_table($items); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside render_items_table().
                }
            }
        } else {
            $this->render_form('');
        }

        return ob_get_clean();
    }

    private function render_form($order_number_value)
    {
        ?>
        <form class="hdsd-lookup-form" method="get">
            <p>
                <label for="hdsd_order_field"><?php esc_html_e('Order number', 'hdwebmobile-secure-downloads'); ?></label><br />
                <input type="text" id="hdsd_order_field" name="<?php echo esc_attr(self::FIELD_ORDER); ?>" value="<?php echo esc_attr($order_number_value); ?>" required />
            </p>
            <p>
                <label for="hdsd_key_field"><?php esc_html_e('Order key', 'hdwebmobile-secure-downloads'); ?></label><br />
                <input type="text" id="hdsd_key_field" name="<?php echo esc_attr(self::FIELD_KEY); ?>" placeholder="wc_order_&hellip;" required />
                <br /><span class="description"><?php esc_html_e('Shown on your order confirmation page and in your order emails.', 'hdwebmobile-secure-downloads'); ?></span>
            </p>
            <p><button type="submit" class="button"><?php esc_html_e('Find my downloads', 'hdwebmobile-secure-downloads'); ?></button></p>
        </form>
        <?php
    }

    /**
     * @param array $items From HDSD_Lookup::get_items() -- already WooCommerce's own
     *                      pre-built download_url values; this method only escapes and lays
     *                      them out, it never builds a URL itself.
     */
    private function render_items_table($items)
    {
        ob_start();
        ?>
        <table class="hdsd-downloads-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Product', 'hdwebmobile-secure-downloads'); ?></th>
                    <th><?php esc_html_e('File', 'hdwebmobile-secure-downloads'); ?></th>
                    <th><?php esc_html_e('Remaining', 'hdwebmobile-secure-downloads'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item) : ?>
                    <tr>
                        <td><?php echo esc_html($item['product_name']); ?></td>
                        <td><a href="<?php echo esc_url($item['download_url']); ?>"><?php echo esc_html($item['download_name'] ?: $item['product_name']); ?></a></td>
                        <td><?php echo ('' === (string) $item['downloads_remaining']) ? esc_html__('Unlimited', 'hdwebmobile-secure-downloads') : esc_html($item['downloads_remaining']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
}
