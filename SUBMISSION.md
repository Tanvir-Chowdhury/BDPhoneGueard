# BD Phone Guard — Submission Kit

Everything needed to submit this plugin to the WordPress.org plugin directory.
The distributable zip is at `dist/bd-phone-guard-1.0.0.zip`.

---

## 0. Pre-flight checklist (do these in order)

1. ~~**Put your real WordPress.org username in two places**~~ — **Done:** `tanvir11744` is set as
   `Contributors` in readme.txt, as `Author` in the plugin header, and in the LICENSE/.pot
   copyright lines. The zip in `dist/` is rebuilt with it.

2. **Run the test suite** (a static PHP 8.4 binary lives in `local-run/bin/php` for this; any PHP 7.2+ works):
   ```bash
   php dev/run-tests.php
   # expected: "51 tests, 0 failures"
   ```
   These tests were run and pass, along with a live end-to-end check on the local
   demo site (classic checkout, block checkout via the Store API, and the admin
   settings screen all verified — junk blocked with the right messages, Bangla
   digits stored as `01712345678`).

3. **Install the zip on a test site** (local is fine) and check:
   - Settings → BD Phone Guard renders and saves.
   - Put `[bd_phone_guard]` on a page, type `০১৭১২৩৪৫৬৭৮` and `017-0000-0000` — valid and fake
     respectively — and watch the live hint.
   - With WooCommerce + a COD product: checkout with `01234567890` must be blocked with the
     prefix error; checkout with `+8801712345678` must save `01712345678` on the order.

4. **Run the official Plugin Check** (Plugins → Add New → search "Plugin Check", install, run it
   against BD Phone Guard, category "plugin repo"). Fix anything it flags before uploading.

5. Rebuild the zip after any edit: `bash dev/build.sh`

---

## 1. Submit

Log in at wordpress.org with the account whose username you put in the readme, then open:

**https://wordpress.org/plugins/developers/add/**

Fill the form exactly like this:

- **Plugin name:** `BD Phone Guard`
- **Plugin slug:** `bd-phone-guard`
- **A brief description of the plugin in a couple of sentences:**

  > BD Phone Guard validates and normalizes Bangladeshi mobile numbers on WooCommerce checkout and
  > My Account forms. It converts Bangla digits (০১৭১২৩৪৫৬৭৮) to English, strips spaces, dashes and
  > invisible characters, accepts every common format (01712345678, +8801712345678, 8801712345678,
  > 008801712345678), validates the 11-digit length and the operator prefix (013–019), rejects
  > obviously fake numbers like 01700000000, and saves every number in the store's chosen format
  > (01XXXXXXXXX, 01XXX-XXXXXX or +8801XXXXXXXXX) with friendly error messages in Bangla or English.
  > It is pure PHP on the user's own site: no API calls, no SMS/OTP service, no external servers.
  > A [bd_phone_guard] shortcode and a small PHP API (bdpg_normalize_phone / bdpg_validate_phone)
  > make it usable in any form, WooCommerce or not.

- Upload `dist/bd-phone-guard-1.0.0.zip`.

## 2. While you wait

Review usually takes **1–7 days**. You will get an email from `plugins@wordpress.org`. **Reply to
that email within a few days** (from the same address) even if only to confirm — silence is the
most common reason reviews stall. Reviewers often ask for small fixes (escaping, i18n, readme
wording); every point they raise is already handled in this codebase, so you can usually reply
"fixed in the attached/updated zip" if needed.

## 3. After approval — check in to SVN

You'll get an email confirming the slug `https://wordpress.org/plugins/bd-phone-guard/` is yours.
Then:

```bash
sudo apt install subversion   # once
svn co https://plugins.svn.wordpress.org/bd-phone-guard/ bd-phone-guard-svn
cd bd-phone-guard-svn

# copy the plugin files (the contents of the zip) into trunk/
cp -R /path/to/bd-phone-guard/bd-phone-guard/* trunk/

svn add trunk/* --force
svn ci -m "Initial import of BD Phone Guard 1.0.0" --username YOUR_WPORG_USERNAME
```

The page goes live within a few minutes of the commit.

## 4. Optional but recommended, soon after

Add display assets to the `assets/` directory of SVN (NOT inside trunk):

- `assets/banner-772x250.png` and `assets/banner-1544x500.png`
- `assets/icon-128x128.png`, `icon-256x256.png`, `icon.svg`
- `assets/screenshot-1.png` … (settings page, shortcode in action) and a
  `== Screenshots ==` section in readme.txt listing them.

Commit: `svn add assets/* --force && svn ci -m "Add assets"`

After release, ask for translations at translate.wordpress.org and add the `bangladesh` tag —
both help the "family of Bangladesh tools" idea from the plan.

## 5. What the codebase already satisfies (likely review points)

| Review point | Where |
| --- | --- |
| Unique prefix | All functions `bdpg_*`, classes `BD_Phone_Guard_*`, constants `BDPG_*` |
| Sanitized input | `sanitize_text_field( wp_unslash( ... ) )` on all `$_POST` reads; whitelist sanitize callback for settings |
| Escaped output | `esc_html` / `esc_attr` / `esc_url` at every echo; JS uses `textContent` only |
| Nonce on settings form | WordPress Settings API (`settings_fields()`), capability `manage_options` |
| License | GPL v2 or later in the file header, readme and LICENSE |
| No external calls | Zero `wp_remote_*`, `curl`, sockets; pure PHP + regex |
| Uninstall cleanup | `uninstall.php` deletes the single option, multisite-aware |
| readme.txt | Full description/installation/FAQ/changelog, no brand names in title/slug (WooCommerce mentioned only as compatibility in description) |
| i18n | Text domain `bd-phone-guard`, `.pot` file included, translations auto-load from translate.wordpress.org, Bangla error strings built in |

## 6. Roadmap after 1.0

- **1.1:** Contact Form 7 `tel` field validation (the planned second release, kept out of 1.0 to
  make the first review small).
- Later: other form plugins, order-list bulk "bad number" scan, and the sibling Bangladesh tools
  under one author profile.
