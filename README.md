# BD Phone Guard

A Bangladeshi mobile number validator and formatter for WordPress. Customers type the same number
many ways — `01712345678`, `+8801712345678`, `8801712345678`, `01712-345678`, `০১৭১২৩৪৫৬৭৮`, with
invisible characters pasted from chat apps — and BD Phone Guard turns it all into one clean number
that couriers and SMS tools can actually use. Designed for cash-on-delivery stores.

Built for the WordPress.org plugin directory (slug: `bdphoneguard`).

## Layout

```
bdphoneguard/   the plugin itself — this folder is what ships (zip root / SVN trunk)
dev/            tests + build tooling (never shipped)
dist/           the built zip
SUBMISSION.md   step-by-step wordpress.org submission instructions
```

## The rules it implements

- Accepted spellings: `01712345678`, `+8801712345678`, `8801712345678`, `008801712345678`,
  `01712-345678`, `1712345678`, Bangla digits, arbitrary spaces/dashes/brackets/invisible characters.
- Output formats: `01712345678` (national), `01712-345678` (dashed), `+8801712345678` (E.164),
  or leave-as-typed.
- Valid: 11 digits, prefix `013`–`019` (Grameenphone 013/017, Banglalink 014/019, Teletalk 015,
  Airtel 016, Robi 018).
- Junk heuristic: the 8 digits after the prefix may not all be identical (`01700000000` fails).
  Toggleable in settings and via the `bdpg_reject_repeated_digits` filter.
- Error messages in Bangla, English, or auto-detected from the site locale.

## Integrations

- WooCommerce checkout (`billing_phone`, `shipping_phone`): validation via
  `woocommerce_after_checkout_validation`, normalization via
  `woocommerce_process_checkout_field_*_phone`.
- WooCommerce My Account: Addresses + Account details forms.
- `[bd_phone_guard name="…" label="…" placeholder="…" required="yes"]` shortcode with a live
  client-side hint (server stays the authority).
- PHP API: `bdpg_normalize_phone( $raw, $format )`, `bdpg_validate_phone( $raw )`,
  filters `bdpg_operator_prefixes`, `bdpg_reject_repeated_digits`, `bdpg_error_message`.

## Development

```bash
php dev/run-tests.php      # test suite with WP shims, no WordPress install needed
python3 dev/algorithm-check.py   # same algorithm in Python, for machines without PHP
bash dev/build.sh          # rebuild dist/bdphoneguard-<version>.zip
```

The JS file `assets/js/bdpg-public.js` mirrors `BD_Phone_Guard_Phone::parse()` — keep both in
sync when changing the rules. `dev/algorithm-check.py` is a third copy used only for verification.

## Scope

Deliberately no OTP/SMS verification (it costs money per message) and no external services at all.
Contact Form 7 support is planned for 1.1 — kept out of 1.0 to keep the first repository review
small.

License: GPL v2 or later.
