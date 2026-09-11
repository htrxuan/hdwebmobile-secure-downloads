=== HDWebmobile Secure Downloads ===
Contributors: htrxuan
Donate link: https://paypal.me/htrxuan/20
Tags: woocommerce, downloads, digital downloads, download links, order key
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let customers recover their WooCommerce download links using their order number and key -- no file path is ever read from a request.

== Description ==

HDWebmobile Secure Downloads gives customers a way to get back to their downloadable products after checkout. Download links appear automatically on the order confirmation page, and a `[hdsd_my_downloads]` shortcode lets anyone who lost the email or closed the tab look them up again using their order number and order key -- no WordPress account required.

Every download link shown is exactly the one WooCommerce's own core already generated for that order. This plugin never builds, guesses, or serves a file itself -- it only displays links WooCommerce is already willing to hand out, after confirming the requester actually owns the order.

= Why this plugin exists =
"Direct Download for WooCommerce" (versions up to and including 1.19) shipped CVE-2026-15019 (CWE-22 Path Traversal, CVSS 7.5): its download endpoint read a file path straight from the request and served it, and its "ownership" check only confirmed that *some* free, virtual, downloadable product existed on the site -- not that the specific file being requested belonged to the specific order being claimed. That let an unauthenticated visitor read arbitrary files on the server on any store with at least one qualifying product.

This plugin is built so that mistake has nowhere to happen:

* **No file path is ever read from a request, anywhere in this plugin.** The only values ever taken from a request are an order number and an order key -- plain identifiers, never used as a filesystem path.
* **Ownership is checked against the specific order being claimed**, the same way WooCommerce's own core checks guest access to an order (its checkout and order-received pages): the order id must match AND the supplied key must satisfy `hash_equals()` against that exact order's real key. There is no "does a qualifying item exist somewhere" shortcut standing in for "does this requester own this order."
* **This plugin never reads or streams a file.** Every download link shown is `WC_Order::get_downloadable_items()`'s own pre-built URL -- generated and independently re-validated by WooCommerce core itself the moment it's clicked.
* Lookups are rate-limited per visitor, and a wrong order number and a wrong key both produce the same generic message, so the lookup form can't be used to enumerate valid order numbers.

= Key Features =
* Download links shown automatically on the order confirmation ("thank you") page
* A `[hdsd_my_downloads]` shortcode: customers recover their links later with just their order number and order key, no account needed
* Optional reminder line in order emails pointing at your lookup page
* Every link is WooCommerce's own, unmodified -- expiry and remaining-download limits are respected automatically
* Rate-limited lookups; identical, non-revealing error message for any invalid attempt

= Limitations (please read before installing) =
* This plugin does not add downloadable-product functionality -- it surfaces links to files already configured as downloads on your products via WooCommerce's own built-in downloadable-product feature
* No email-based "resend my link" flow in this version; recovery is via the order number + order key lookup only
* The optional email reminder is a plain line of text pointing at your lookup page, not a full resend of the actual links

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/hdwebmobile-secure-downloads` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress. WooCommerce must already be installed and active.
3. Go to **WooCommerce > HDWebmobile > Secure Downloads** to configure it.

== How to Use ==

= 1. Automatic display =
With "Show download links on the order confirmation page" enabled (the default), a customer's downloads for downloadable products appear right on the thank-you page after checkout.

= 2. Manual recovery =
Add the `[hdsd_my_downloads]` shortcode to any page. A customer enters their order number and order key (both shown on their order confirmation and in their order emails) to see their downloads again.

= 3. (Optional) Email reminder =
Set "Lookup page URL" to the page where you placed the shortcode, and order emails to the customer will mention it as a way to recover a lost download link.

== Screenshots ==

1. Download links shown automatically on the order confirmation page.
2. The [hdsd_my_downloads] lookup form and results.
3. The Secure Downloads settings tab under WooCommerce > HDWebmobile.

== Changelog ==

= 1.0.0 =
* Initial release: automatic download display on the order-received page, a manual order-number-and-key lookup shortcode, and an optional order-email reminder -- every link sourced directly from WooCommerce's own download registry, never a file path handled by this plugin.
