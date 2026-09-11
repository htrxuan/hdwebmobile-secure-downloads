<?php

namespace htrxuan\hdsd;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves an order number + order key into that order's downloadable items -- the ONLY
 * thing this plugin ever looks up, and the only class that touches order/download data.
 *
 * CVE-2026-15019 (CWE-22 Path Traversal, CVSS 7.5) in "Direct Download for WooCommerce"
 * (<= 1.19): the plugin's download endpoint read a file path from the request and served it
 * directly, and its "ownership" check only verified that SOME free/virtual/downloadable
 * product existed on the site at all -- not that the specific requested file belonged to the
 * specific order being claimed. That let an unauthenticated visitor read arbitrary files on
 * the server on any store that had at least one qualifying product.
 *
 * This class closes that entire class of bug by construction:
 *
 *  - There is no file path, filename, or anything filesystem-shaped anywhere in this plugin's
 *    code. The only two values ever taken from a request are an order id (cast with absint())
 *    and an order key (a plain string, compared -- never used to look anything up on disk).
 *  - Ownership is verified exactly the way WooCommerce's own core code verifies guest access
 *    to an order (its checkout/order-pay/thankyou handlers, see includes/class-wc-form-
 *    handler.php and includes/shortcodes/class-wc-shortcode-checkout.php): `$order->get_id()
 *    === $order_id && hash_equals($order->get_order_key(), $order_key)`. hash_equals() is
 *    used deliberately (not `===`) so string comparison can't leak timing information about
 *    how much of the key was guessed correctly. There is no "does a qualifying product exist
 *    anywhere" check standing in for "does this order belong to this requester" -- ownership
 *    is checked against the ONE order actually being claimed, every time.
 *  - The download links themselves are never built by this plugin. get_items() returns
 *    exactly what WC_Order::get_downloadable_items() produces -- URLs, expiry, and remaining-
 *    download counts that WooCommerce's own core already generated and will independently
 *    re-validate the moment the customer clicks one. This plugin never reads, opens, or
 *    streams a file; it only displays a link WooCommerce itself is willing to serve.
 *  - Lookups are rate-limited per visitor (a short transient) so the order key -- while high
 *    entropy -- isn't left open to unlimited guessing.
 */
final class HDSD_Lookup
{
    const RATE_LIMIT_ATTEMPTS = 10;
    const RATE_LIMIT_WINDOW   = 10 * MINUTE_IN_SECONDS;

    /**
     * @param int    $order_id  Raw, will be cast with absint().
     * @param string $order_key Raw, compared with hash_equals() -- never used as an array key,
     *                          a file path, or passed to any lookup function itself.
     * @return \WC_Order|\WP_Error The order on success, or a WP_Error with a deliberately
     *                             generic message (never "no such order" vs "wrong key" --
     *                             that distinction would let an attacker enumerate valid
     *                             order ids).
     */
    public static function resolve_order($order_id, $order_key)
    {
        if (self::is_rate_limited()) {
            return new \WP_Error('hdsd_rate_limited', __('Too many attempts. Please wait a few minutes and try again.', 'hdwebmobile-secure-downloads'));
        }

        $order_id  = absint($order_id);
        $order_key = (string) $order_key;

        $generic_error = new \WP_Error('hdsd_not_found', __('We could not find a matching order. Please double-check your order number and order key (shown on your order confirmation email).', 'hdwebmobile-secure-downloads'));

        if ($order_id < 1 || '' === $order_key) {
            self::record_attempt();
            return $generic_error;
        }

        $order = wc_get_order($order_id);

        // Exactly WooCommerce core's own guest-order-ownership check -- id match AND a
        // timing-safe key comparison. No other signal (email, IP, session) substitutes for it.
        if (!$order instanceof \WC_Order || $order->get_id() !== $order_id || !hash_equals((string) $order->get_order_key(), $order_key)) {
            self::record_attempt();
            return $generic_error;
        }

        return $order;
    }

    /**
     * The order's downloadable items, exactly as WooCommerce's own core produces them --
     * download_url, downloads_remaining, access_expires. Nothing here is computed, parsed, or
     * re-derived from a file path; it is a straight pass-through of WC_Order::
     * get_downloadable_items(), filtered only to items that are actually still claimable.
     *
     * @param \WC_Order $order
     * @return array
     */
    public static function get_items($order)
    {
        $items = array();
        foreach ($order->get_downloadable_items() as $item) {
            if ('' === (string) $item['download_url']) {
                continue; // Nothing to link to.
            }
            if ('0' === (string) $item['downloads_remaining']) {
                continue; // Download limit already used up.
            }
            if (!empty($item['access_expires']) && $item['access_expires'] instanceof \WC_DateTime && $item['access_expires']->getTimestamp() < time()) {
                continue; // Access window has passed.
            }
            $items[] = $item;
        }
        return $items;
    }

    private static function is_rate_limited()
    {
        return (int) get_transient(self::rate_key()) >= self::RATE_LIMIT_ATTEMPTS;
    }

    private static function record_attempt()
    {
        $key   = self::rate_key();
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW);
    }

    private static function rate_key()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        return 'hdsd_rl_' . md5($ip);
    }
}
