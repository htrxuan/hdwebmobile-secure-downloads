# HDWebmobile Secure Downloads

Let customers recover their WooCommerce download links using their order number and key -- no file path is ever read from a request.

- **WordPress.org:** https://wordpress.org/plugins/hdwebmobile-secure-downloads/
- **Requires:** WordPress 6.9+, WooCommerce, PHP 7.4+
- **License:** GPLv2 or later

## Description

Download links appear automatically on the order confirmation page, and a `[hdsd_my_downloads]` shortcode lets anyone who lost the email or closed the tab look them up again using their order number and order key — no WordPress account required. Every link shown is exactly the one WooCommerce's own core already generated for that order; this plugin never builds, guesses, or serves a file itself.

## Why this plugin exists

"Direct Download for WooCommerce" (≤ 1.19) shipped CVE-2026-15019 (CWE-22 Path Traversal, CVSS 7.5): its download endpoint read a file path from the request and served it, checking only that *some* qualifying product existed on the site — not that the specific file belonged to the specific order being claimed. That let an unauthenticated visitor read arbitrary server files.

This plugin is built so that mistake has nowhere to happen:

* **No file path is ever read from a request.** Only an order number and an order key are ever taken from a request — plain identifiers, never a filesystem path.
* **Ownership is checked against the specific order being claimed**, exactly the way WooCommerce's own core checks guest order access: the order id must match AND the key must satisfy `hash_equals()` against that order's real key.
* **This plugin never reads or streams a file.** Every link is `WC_Order::get_downloadable_items()`'s own pre-built URL, independently re-validated by WooCommerce core when clicked.
* Lookups are rate-limited; a wrong order number and a wrong key produce the same generic message, so the form can't be used to enumerate valid order numbers.

## Features

* Download links shown automatically on the order confirmation page
* `[hdsd_my_downloads]` shortcode for later recovery via order number + key, no account needed
* Optional reminder line in order emails pointing at your lookup page
* Every link is WooCommerce's own — expiry and remaining-download limits respected automatically
* Rate-limited, non-revealing error messages

## Limitations

* Surfaces links to files already configured via WooCommerce's own downloadable-product feature — doesn't add that feature itself
* No email-based "resend my link" flow in this version; recovery is via the lookup form only
* The optional email reminder is a plain text pointer to your lookup page, not a resend of the actual links

## Installation

1. Upload to `/wp-content/plugins/hdwebmobile-secure-downloads`, or install through the WordPress plugins screen.
2. Activate. WooCommerce must already be installed and active.
3. Go to **WooCommerce > HDWebmobile > Secure Downloads**.

## License

GPLv2 or later — https://www.gnu.org/licenses/gpl-2.0.html
