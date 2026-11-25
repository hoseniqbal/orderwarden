=== OrderWarden: Stop Card Testing & Fake Orders for WooCommerce ===
Contributors: iqbal511138
Tags: card testing, fake orders, woocommerce fraud, checkout protection, anti-fraud
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stop card-testing bots and fake orders at your WooCommerce checkout, without CAPTCHA. Clean up thousands of failed orders in one click.

== Description ==

Woke up to hundreds of failed $1 orders? That is a **card-testing attack**: bots use your checkout to find out which stolen credit cards still work. You pay the gateway fees, your order list fills with junk, and your payment provider may freeze your account.

Most of these bots never open your checkout page. They send requests straight to WooCommerce, so a CAPTCHA on the page never sees them. **OrderWarden** stops them where they arrive, before WooCommerce creates an order or contacts your payment gateway.

= How it works =

When a real shopper opens your store, their browser quietly receives a signed token. That token travels with their order. A request that arrives without a valid token never loaded your store, so OrderWarden turns it away. Shoppers see nothing and solve nothing.

= Features =

* **Checkout protection without CAPTCHA**. Covers the classic checkout, the block checkout (Store API), the "Pay for order" page and express payment buttons.
* **Works with every payment gateway**. Stripe, PayPal, WooPayments, Authorize.net, Square, Braintree and the rest. Nothing to configure per gateway.
* **Safe by default**. Automatic mode only starts blocking a checkout type after it has seen one genuine order go through there, so a theme or caching problem never turns real customers away.
* **Failed payment limit**. An address that fails payment again and again (a bot driving a real browser) is turned away for a while.
* **One-click cleanup of failed orders**. Remove thousands of failed orders left by an attack, in the background, without timeouts. Move them to Trash or delete them permanently, guest orders only or all.
* **Attack alerts by email**. Know when an attack starts, at most one email per hour.
* **Gateway checklist**. Shows the free protections to switch on in the payment gateways you actually use, plus Cloudflare and WooCommerce settings.
* **Dashboard**. Blocked attempts in the last 24 hours and 7 days, and what was stopped and why.
* **Returning customers are trusted**. Logged-in customers with a paid order are never blocked (optional).
* **IP allowlist** with CIDR ranges.
* **Private by design**. Everything runs on your own server. No external service, no API key, no tracking. IP addresses are stored masked.
* **Fast and compatible**. Works with page caching (WP Rocket, LiteSpeed, Cloudflare), HPOS and the Cart and Checkout blocks.

= What it does not stop =

We would rather be honest than promise too much. A bot that drives a real browser and loads your checkout page gets a genuine token. The failed payment limit catches most of these, because they fail payment after payment. For the strongest protection, pair OrderWarden with your gateway's own fraud tools and Cloudflare's free Bot Fight Mode. The Checklist tab shows you how.

== Installation ==

1. Install and activate OrderWarden from Plugins > Add New.
2. Go to WooCommerce > OrderWarden.
3. Place one normal test order in a regular browser. Protection switches on for that checkout type right away.
4. Open the Checklist tab and switch on the free protections in your payment gateway.

== Frequently Asked Questions ==

= Will it block my real customers? =

It is built not to. In Automatic mode, OrderWarden does not block anything on a checkout type until it has seen a genuine order go through there with a valid token. Logged-in customers with a paid order are never blocked, and shop managers are never blocked. If you want to be extra careful, use Learning mode first: it only logs what it would have blocked.

= Do I need a CAPTCHA as well? =

No. OrderWarden works without one. A CAPTCHA does not hurt, but it does not stop bots that post straight to the checkout endpoint.

= Does it work with page caching? =

Yes. The token is fetched by a small script after the page loads, so a cached page never carries an old token.

= Does it send my data anywhere? =

No. All checks run on your own server. OrderWarden makes no external requests.

= I use a custom or headless checkout. =

Use the `orderwarden_skip_check` filter to exempt your requests, or add your server's IP to the allowlist.

= Express payment buttons appear on pages outside WooCommerce. =

Add `add_filter( 'orderwarden_load_script', '__return_true' );` to load the token script on every page.

= What does the cleanup remove? =

Only orders with the status "Failed". You choose guest orders only or all, an optional minimum age, and Trash or permanent deletion.

= Is there a Pro version? =

A separate Pro add-on is planned, with automatic bans, Slack, WhatsApp and Telegram alerts, a weekly report, full attack history and a dashboard for many stores. Its pages appear in the OrderWarden menu marked "Pro" and say "Coming soon" until it is released. Nothing in this free plugin is locked or limited.

== Screenshots ==

1. Overview: blocked attempts and protection status for each checkout type.
2. Clean up failed orders in the background.
3. Settings.
4. Checklist for your payment gateways.

== Changelog ==

= 1.0.0 =
* First release: token-based checkout protection (classic, block, pay for order, express buttons), failed payment limit, failed order cleanup, email alerts, gateway checklist, dashboard.

== Upgrade Notice ==

= 1.0.0 =
First release.
