---
name: wp-readme
description: "Use whenever writing or editing readme.txt — the WordPress.org plugin listing. Covers the headers the directory parser actually honours, section order and limits, the Keep a Changelog vocabulary this project writes changelogs in, the register to write in, how to reference the paid add-on within the directory guidelines, and what to verify before publishing."
---

# Writing readme.txt

`readme.txt` is the WordPress.org plugin listing. It is read by store owners deciding whether to install, and by the plugin review team deciding whether to accept. Both audiences punish the same thing: text written for the developer who wrote the code.

## Headers

The directory's parser honours exactly nine, and silently ignores anything else:

`Contributors`, `Donate link`, `Tags`, `Requires at least`, `Tested up to`, `Requires PHP`, `Stable tag`, `License`, `License URI`.

`Requires Plugins` is a **plugin-file** header, not a readme header — the parser ignores it here. `WC requires at least` / `WC tested up to` likewise live only in `alondra.php`.

There is no header for a paid version, a pro link, or a support URL. Do not invent one.

`Stable tag`, `Requires at least`, `Requires PHP` and `Tested up to` must match `alondra.php` exactly. A `Tested up to` below the current WordPress release is reported by Plugin Check as an **error**, not a warning, so it has to be raised and actually exercised before every submission.

## Sections and limits

Order: `== Description ==`, `== Installation ==`, `== Frequently Asked Questions ==`, `== Screenshots ==`, `== Changelog ==`, `== Upgrade Notice ==`. Anything the parser does not recognise is appended to the end of the Description, so a stray heading does not error — it just lands somewhere surprising.

Enforced limits: short description ≤ 150 characters, Description ≤ 2500 words, FAQ ≤ 5000 words, Changelog ≤ 5000 words, **at most 5 tags**. Overrunning truncates the section rather than failing, which is worse than an error because nothing tells you.

**Tags are the exception, on purpose.** `readme.txt` keeps its twelve tags by the product owner's decision: the directory displays the first five, so keep the list ordered by importance. Plugin Check's `readme_parser_warnings_too_many_tags` warning is expected and accepted; do not trim the list in response to it.

`== Screenshots ==` captions are numbered and must match the `screenshot-N` files one for one. The images are published separately, from `.wordpress-org/` to the SVN root — see `scripts/publish_assets_to_wordpress.sh`, which refuses a mismatch.

## Listing images

Not part of readme.txt, but the captions depend on them. The rules come from the [plugin assets page](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/) — check it rather than recalling it, because the accepted formats are not symmetric and are easy to get backwards:

| | Names | Formats | Max |
|---|---|---|---|
| Banner | `banner-772x250`, `banner-1544x500` | `jpg`, `png` | 4 MB |
| Icon | `icon-128x128`, `icon-256x256`, `icon.svg` | `png`, `jpg`, **`gif`** | 1 MB |
| Screenshot | `screenshot-N` | `png`, `jpg` (**no gif**) | 10 MB |

Icons take gif; screenshots do not. Banners and screenshots also accept a locale suffix (`banner-772x250-rtl.png`, `screenshot-1-de.png`), and a localized screenshot is a translation of that screenshot, not an additional one. `icon.svg` needs a raster fallback beside it. **Filenames must be lowercase** — an uppercase name is ignored without any error, which is the same silent failure as a misspelling.

`scripts/publish_assets_to_wordpress.sh` enforces all of this and refuses a caption/file mismatch.

## Changelog

Write entries as `* Prefix: sentence.`, using the [Keep a Changelog](https://keepachangelog.com) vocabulary:

`Added` · `Changed` · `Deprecated` · `Removed` · `Fixed` · `Security`

Group in that order within a version. Keep a Changelog uses these as section headings; here they are inline prefixes, because the directory renders a flat bullet list and extra headings inside a version read as noise.

```
= 1.0.3 =
* Added: Prices are now cached, so a page showing several tiered products makes fewer queries.
* Changed: WordPress 6.8 or later is now required.
* Fixed: Dismissing a notification in the admin now keeps it hidden.
* Security: Pricing group actions in the admin require a valid nonce.
```

One change per bullet. If a bullet needs the word "and" to join two unrelated effects, it is two bullets.

**readme.txt carries only the current version.** `== Changelog ==` holds the latest version's entry followed by one line linking to the full changelog at `https://alondra.midri.net/changelog`, and `== Upgrade Notice ==` holds only the latest version's notice. The full history is maintained on the website. When releasing a new version, **replace** the entry (and the notice) rather than prepending, and make sure the outgoing entry is on the website first. `scripts/publish_to_wordpress.sh` only checks that `= <version> =` for the release is present.

**Only list versions this plugin actually published in the directory.** A version that never shipped here is not backfilled on the website either: it would document a release nobody could have installed from this listing.

**One line per notable change, written with the change.** A commit that fixes, adds, removes or changes something a shop owner could notice adds its line under the version being prepared in the same change, not at release time.

## Register

Write for someone who runs a shop, not someone who reads the code.

- Describe what the reader would **notice**, never the mechanism. "Saving a pricing group no longer discards its prices" — not "a zero affected-rows return is no longer read as a failure".
- **Do not dwell on the database.** Naming tables, migrations, schema or repair makes a routine update sound dangerous. "Plugin updates are now applied on their own" says everything the reader can act on; "a site missing its tables now repairs itself" only plants a worry.
- Frame a safety behaviour as protection, not as a failure mode: "if an update cannot be completed, tiered pricing pauses instead of affecting your storefront".
- No file names, function names, commit hashes or issue numbers anywhere in the file.
- Drop anything genuinely invisible to a shop owner — dev tooling, test changes, CI, log noise.

## Upgrade Notice

At most **300 characters**, and the parser runs `strip_tags`/`esc_html` over it, so **a link there does not render**. Plain prose only. Say why to update, in the order the reader cares: security first, then the thing most likely to have bitten them.

## Referencing the paid version

Permitted, explicitly, by Detailed Plugin Guideline 5: *"Attempting to upsell the user on ad-hoc products and features is acceptable, provided it falls within bounds of guideline 11."* But:

- Put it at the **end of `== Description ==`**, plus at most one FAQ entry. There is no official section or header for it; this is the convention the review team points at.
- Link `https://alondra.midri.net` plainly — no UTM cloaking, shortener or redirect.
- **Never in `Donate link`** (documented as a donation link) and **never in `== Upgrade Notice ==`** (tags are stripped, so it would not render anyway).
- Frame it as what the paid plugin **adds**, never as what this one **lacks**. Guideline 9 prohibits "Implying users must pay to unlock included features", and Guideline 5 treats a control the plugin ships but cannot vary (a disabled field, a pinned hidden input) as a locked feature. Prose only — never a control, a placeholder or a hidden input.
- Before listing a feature as paid-only, **check it against this plugin's code**, not against the add-on's own listing. Something this plugin already does (for example, striking through regular prices inside its prices table) must not be presented as an add-on feature.

## Before publishing

- Run the file through the [readme validator](https://wordpress.org/plugins/developers/readme-validator/). A plain `curl -X POST` against it returns the empty form; post it through the form itself, and sanity-check your method with a deliberately broken readme first, or a silent non-result reads as a pass.
- Re-read the changelog as a shop owner who has never seen the code. Anything you cannot act on, cut.
