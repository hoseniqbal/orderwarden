# OrderWarden – Stop Card Testing & Fake Orders for WooCommerce

Free WordPress plugin. Stops card-testing bots and fake orders at the WooCommerce checkout without CAPTCHA, and cleans up thousands of failed orders in one click.

- **wordpress.org slug (planned):** `orderwarden`
- **Text domain / prefix:** `orderwarden` / `OrderWarden_`, `orderwarden_`
- **Requires:** WordPress 6.2+, PHP 7.4+, WooCommerce 7.0+

## How it works

1. `assets/js/orderwarden-token.js` loads on store pages, fetches a signed token from `GET /wp-json/orderwarden/v1/token` and attaches it to checkout requests: as the `X-OrderWarden-Token` header (fetch/XHR to `wc-ajax=` and `/wc/store/`) and as the hidden field `orderwarden_token` in classic checkout and pay-for-order forms.
2. `OrderWarden_Guard` checks the token on every checkout entry point before an order is created:
   | Channel | Hook |
   |---|---|
   | Classic checkout + express buttons | `woocommerce_checkout_process` |
   | Block checkout (Store API) | `rest_pre_dispatch` on `POST /wc/store/v*/checkout(/{id})` |
   | Pay for order | `woocommerce_before_pay_action` |
3. Token = `issued.random.ua_fingerprint.hmac`. Rejected when missing, forged, older than 6h, used < 1s after issue, or by a different user agent.
4. **Automatic mode** learns per channel and only enforces after it has seen one genuine token on that channel.
5. `OrderWarden_Velocity` blocks an IP after N failed orders (`woocommerce_order_status_failed`) in a window.
6. `OrderWarden_Cleaner` removes failed orders in batches of 50 with Action Scheduler.

## Structure

```
orderwarden.php                      bootstrap, constants, HPOS/blocks compatibility
includes/class-orderwarden-plugin.php    wiring, activation, cron
includes/class-orderwarden-settings.php  defaults + sanitize
includes/class-orderwarden-request.php   IP, UA, CIDR helpers
includes/class-orderwarden-token.php     issue / verify tokens
includes/class-orderwarden-guard.php     checkout hooks, decision, REST token route, script enqueue
includes/class-orderwarden-velocity.php  failed payment limit
includes/class-orderwarden-logger.php    {prefix}orderwarden_events table, stats, purge
includes/class-orderwarden-alerts.php    attack email
includes/class-orderwarden-cleaner.php   background failed-order cleanup
includes/class-orderwarden-admin.php     WooCommerce > OrderWarden (Overview, Settings, Cleanup, Checklist)
assets/js/orderwarden-token.js
tests/attack-sim.sh                  curl bot simulation for a local store
```

## Test plan (local)

1. Local WP + WooCommerce, one simple product, Stripe in test mode. Make one page with the **classic** checkout shortcode and keep the **block** checkout too.
2. Activate. Overview shows all three channels as *Learning*.
3. Run `tests/attack-sim.sh https://your-site.test` → attempts show as *Would block*, orders are still created (learning).
4. Place a real order in a browser on the classic checkout, then on the block checkout → those channels flip to *Protected*.
5. Run the attack script again → classic returns our error with no new order; Store API returns HTTP 403.
6. Place real orders again in a browser (both checkouts, logged out) → must succeed.
7. Test with a cache plugin on (WP Rocket / LiteSpeed) → real orders must still succeed.
8. Velocity: use Stripe test card `4000 0000 0000 0002` (declined) 3 times → 4th attempt blocked.
9. Cleanup: create failed orders (declined card) → Cleanup tab → Move to Trash → count goes to 0.
10. Run **Plugin Check** (PCP) → zero errors.

## Test results (25 Nov 2026, SiteGround test site, WP 7.1.5, WooCommerce 11.3.1)

| Test | Result |
|---|---|
| Activate plugin, open dashboard | OK, no errors |
| Learning mode: bot POST to Store API checkout | Allowed and logged as "Would block" |
| Real order on block checkout (shop manager) | OK, block checkout switched to Protected |
| Store API bot without token / with forged token | HTTP 403 `orderwarden_blocked`, no order created |
| Real guest order on classic checkout (shortcode page) | OK (#287), classic switched to Protected |
| Classic bot with WooCommerce nonce but no token | `result: failure`, generic message, no order created |
| Real guest payment on Pay for order page | OK (#285), pay switched to Protected |
| Pay for order bot without token | Error notice, order stayed `pending` |
| 2 failed orders from one IP (limit set to 2) | Next checkout HTTP 403 "Too many failed payments", even with a valid token; released after the block time |
| Valid token from a guest after release | OK (#286) |
| Cleanup, guest only, move to Trash | Guest failed order trashed, customer failed order kept |
| Plugin Check 2.2.0 (General, Plugin Repo, Security) | No errors, no warnings |

Plugin Check's runtime categories (Performance, Accessibility) fail on SiteGround for every plugin (also Template Kit Import), so that is the host, not OrderWarden.

Not yet tested: a real card gateway (Stripe test mode), express buttons on product pages, a page cache serving a logged-out checkout page.

## Before submitting to wordpress.org

- [ ] Set `Contributors:` in `readme.txt` to the real wordpress.org username(s).
- [ ] On the submission page, use the one-time slug change to make the slug `orderwarden` (WordPress derives a longer slug from the plugin name).
- [ ] Zip without `tests/`, `README.md`, `.git*` (see `.distignore`).
