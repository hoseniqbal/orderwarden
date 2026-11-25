#!/usr/bin/env bash
# Simulate a card-testing bot against a LOCAL test store.
# Usage: tests/attack-sim.sh https://satkhira-wp.test
#
# Expected with OrderWarden protecting the channel:
#   classic   -> JSON with "result":"failure" and our generic message, and NO new order
#   store_api -> HTTP 403 with code "orderwarden_blocked"
#
# Only run this against your own local or staging site.

set -u
SITE="${1:?Give the site URL, for example https://satkhira-wp.test}"
JAR="$(mktemp)"
UA="Mozilla/5.0 (attack-sim)"

echo "== 1. Classic checkout (?wc-ajax=checkout) without token"
# Bots fetch the checkout page once to get the WooCommerce nonce, then post directly.
NONCE=$(curl -sk -c "$JAR" -b "$JAR" -A "$UA" "$SITE/checkout/" | grep -o 'name="woocommerce-process-checkout-nonce" value="[^"]*"' | sed 's/.*value="//;s/"//')
echo "   checkout nonce: ${NONCE:-<none found - add a product to cart first or check the URL>}"
curl -sk -c "$JAR" -b "$JAR" -A "$UA" -X POST "$SITE/?wc-ajax=checkout" \
  --data-urlencode "woocommerce-process-checkout-nonce=$NONCE" \
  --data-urlencode "billing_first_name=Bot" --data-urlencode "billing_last_name=Test" \
  --data-urlencode "billing_email=bot@example.com" --data-urlencode "billing_country=US" \
  --data-urlencode "payment_method=stripe" | head -c 600; echo

echo
echo "== 2. Block checkout (Store API) without token"
CART_TOKEN=$(curl -sk -D - -o /dev/null -A "$UA" "$SITE/wp-json/wc/store/v1/cart" | grep -i '^cart-token:' | awk '{print $2}' | tr -d '\r')
curl -sk -A "$UA" -X POST "$SITE/wp-json/wc/store/v1/checkout" \
  -H "Content-Type: application/json" -H "Cart-Token: $CART_TOKEN" \
  -d '{"billing_address":{"first_name":"Bot","last_name":"Test","email":"bot@example.com","country":"US"},"payment_method":"stripe"}' \
  -w "\n   HTTP %{http_code}\n" | head -c 600; echo

echo
echo "== 3. Forged token"
curl -sk -A "$UA" -X POST "$SITE/wp-json/wc/store/v1/checkout" \
  -H "Content-Type: application/json" -H "X-OrderWarden-Token: 1700000000.abcdef.1234567890.deadbeef" \
  -d '{}' -w "\n   HTTP %{http_code}\n" | head -c 400; echo

rm -f "$JAR"
echo
echo "Now open WooCommerce > OrderWarden: the attempts should be listed (as 'Would block' while learning)."
