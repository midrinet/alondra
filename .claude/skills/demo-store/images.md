# Demo store images

The image-generation guide for `docker/demo-store/images/`: the source images of the coffee-roastery demo catalog, whose products are defined in `dev/helper/demo-store/seed.php`. They are generated with an AI image tool, and the seeder sideloads them from that directory. One row per file — each **Subject** cell is a complete prompt and repeats the shared treatment on purpose, so no row depends on another.

## Format and dimensions

**JPEG (`.jpg`), quality ~85.** These are photographic product shots on an opaque background: there is no transparency to preserve, JPEG is a fraction of PNG's size for continuous tone, and every PHP build in the supported range decodes it through GD or Imagick without question. The files are committed binaries, so keep each under ~1 MB.

**2048x2048, 1:1, for every product.** WooCommerce hard-crops `woocommerce_thumbnail` to 1:1 by default (`wc_get_image_size()`: 300px wide, `woocommerce_thumbnail_cropping` = `1:1`), so a non-square source is centre-cropped in the shop grid and whatever framing was composed outside that square is lost — composing at 1:1 means the shot ships as framed. WordPress regenerates every registered size on sideload and never upscales, so the source only has to cover the largest one, which is core's `2048x2048`; 2048 also stays under the `big_image_size_threshold` of 2560, above which WordPress rewrites the upload into a `-scaled` duplicate. `woocommerce_single` (600 wide) and `woocommerce_gallery_thumbnail` (100x100) fall out of the same file.

The home hero is not one of these. It is a theme asset, not a product image: see **The home hero** below.

## Shared visual treatment

Every image is shot as if by one studio on one afternoon: a seamless warm off-white paper sweep (#F4F0EA) with no horizon line, no props, no surface texture; one large soft key light from the upper left at roughly 45 degrees with a gentle fill from the right, leaving a short soft contact shadow falling to the lower right; neutral-warm white balance around 5200K; an 85mm-equivalent lens at product eye level, subject centred with even empty margin on all four sides and the whole product in focus; a restrained roastery palette of kraft paper, matte black, warm terracotta, brushed steel, walnut and amber glass; flat natural grade with slightly lifted blacks, no vignette, no HDR, no colour cast. Consistency here matters more than any single image being good — a set with matching backgrounds and light reads as one shop, a set of individually better shots with drifting backgrounds does not.

## Must not appear

- **No text, lettering or numerals of any kind** — text bakes one language into a binary that ships to every locale, and it renders as garbage at thumbnail size.
- **No logos, brand marks or recognisable real packaging** — a real brand rendered into a demo store is a trademark problem the moment a screenshot is published.
- **No watermarks or generator signatures** — these files are committed and appear in marketing captures.
- **No people, faces, hands or body parts** — likeness and consent risk, and they pull the eye off the product the tier table is meant to be read against.
- **No price tags, stickers, sale flashes or discount badges** — WooCommerce and Alondra render the price; a painted-on one contradicts the tier table the screenshot exists to show.
- **No frames, borders, mockup shadows or collage layouts** — the 1:1 crop cuts a baked frame in half.

## Files, and what happens when one is missing

Files live in `docker/demo-store/images/`, named exactly `<product-slug>.jpg` — the product's `slug` in `dev/helper/demo-store/seed.php` — because that is how the seeder locates them. Renaming a product renames its file in the same commit.

They are baked into the dev image by `docker/Dockerfile` and read from `/demo-store/images/` inside the container, not bind-mounted. Adding or changing one is therefore not enough on its own: the image has to be rebuilt and republished for every `<wp>-<php>` tag, or `scripts/setup` pulls a published tag that does not carry it.

A missing file must **fail loudly, naming the file, and abort provisioning** — the same rule the pinned-id assertion follows. A product created without an image renders WooCommerce's placeholder, which breaks the rule that every product has an image and silently puts a grey placeholder into a marketing capture. Sideloading stays local: no outbound request during provisioning.

## Images

| Filename | Product | Subject | Dimensions | Ratio |
|---|---|---|---|---|
| `cordillera-reserve-kilo.jpg` | Cordillera Reserve Kilo (`CDL-KG`) | A one-kilogram flat-bottom coffee bag in unprinted brown kraft paper with a matte black foil front panel and a small black one-way degassing valve, standing upright and turned three-quarters to the left, a loose scatter of dark roasted beans at its base. Seamless warm off-white sweep, large soft key from the upper left with gentle fill from the right, short soft shadow to the lower right, 5200K, 85mm at eye level, fully in focus, even margin all round. | 2048x2048 | 1:1 |
| `yirgacheffe-filter-roast.jpg` | Yirgacheffe Filter Roast (`YRG-250`) | A 250 g stand-up coffee pouch in pale sand-coloured matte kraft with a folded top, tin tie and a small round degassing valve, entirely unprinted, standing upright and turned three-quarters to the right. Seamless warm off-white sweep, large soft key from the upper left with gentle fill from the right, short soft shadow to the lower right, 5200K, 85mm at eye level, fully in focus, even margin all round. | 2048x2048 | 1:1 |
| `sumatra-nightfall-roast.jpg` | Sumatra Nightfall Roast (`SMT-250`) | A 250 g stand-up coffee pouch in deep charcoal matte foil with a copper-toned side seam and a small round degassing valve, entirely unprinted, standing upright and turned three-quarters to the left. Seamless warm off-white sweep, large soft key from the upper left with gentle fill from the right, short soft shadow to the lower right, 5200K, 85mm at eye level, fully in focus, even margin all round. | 2048x2048 | 1:1 |
| `cascade-house-blend.jpg` | Cascade House Blend (`CSC-000`, variable parent) | Two unprinted coffee pouches in warm terracotta matte foil standing side by side, a small 250 g pouch in front and a taller one-kilogram bag behind it slightly to the left, both turned three-quarters to the right so the size difference reads at a glance. Seamless warm off-white sweep, large soft key from the upper left with gentle fill from the right, short soft shadow to the lower right, 5200K, 85mm at eye level, fully in focus, even margin all round. | 2048x2048 | 1:1 |
| `stoneburr-hand-grinder.jpg` | Stoneburr Hand Grinder (`EQP-GRD`) | A hand-crank coffee grinder with a brushed stainless steel cylindrical body, a walnut crank knob and a folding steel handle, standing upright and turned three-quarters to the left, a few beans resting beside it. Seamless warm off-white sweep, large soft key from the upper left with gentle fill from the right, short soft shadow to the lower right, 5200K, 85mm at eye level, fully in focus, even margin all round. | 2048x2048 | 1:1 |
| `glass-pourover-dripper.jpg` | Glass Pourover Dripper (`EQP-DRP`) | An empty clear borosilicate glass cone pourover dripper with a walnut collar and a thin leather tie, seen slightly from above and turned three-quarters to the right so the ribbed interior of the cone is visible, sitting directly on the sweep. Seamless warm off-white sweep, large soft key from the upper left with gentle fill from the right, short soft shadow to the lower right, 5200K, 85mm at eye level, fully in focus, even margin all round. | 2048x2048 | 1:1 |
| `home-roasting-course.jpg` | Home Roasting Course (`DIG-CRS`, virtual) | A compact tabletop coffee roaster in matte cream and brushed steel with a clear glass drum showing beans mid-roast, turned three-quarters to the left, flanked by two small shallow ceramic dishes holding pale green unroasted beans and dark roasted beans. Seamless warm off-white sweep, large soft key from the upper left with gentle fill from the right, short soft shadow to the lower right, 5200K, 85mm at eye level, fully in focus, even margin all round. | 2048x2048 | 1:1 |
| `brew-guide-ebook.jpg` | Brew Guide Ebook (`DIG-EBK`, virtual) | A slim closed hardcover book bound in uncoated deep-green cloth with a blind-debossed abstract concentric-circle motif and no lettering whatsoever, lying flat and angled slightly, one corner lifted on a thin walnut riser. Seamless warm off-white sweep, large soft key from the upper left with gentle fill from the right, short soft shadow to the lower right, 5200K, 85mm at eye level, fully in focus, even margin all round. | 2048x2048 | 1:1 |
| `roastery-gift-box.jpg` | Roastery Gift Box (`BDL-GIFT`, bundle B1) | An open rigid kraft gift box with an untied linen ribbon, holding two unprinted coffee pouches — one pale sand, one deep charcoal — and a clear glass cone pourover dripper nested in natural crinkle paper, seen from a raised three-quarter angle. Seamless warm off-white sweep, large soft key from the upper left with gentle fill from the right, short soft shadow to the lower right, 5200K, 85mm, fully in focus, even margin all round. | 2048x2048 | 1:1 |
| `origin-sampler-pack.jpg` | Origin Sampler Pack (`BDL-SMPL`, bundle B2) | Two unprinted 250 g coffee pouches, one pale sand and one deep charcoal, standing side by side and slightly overlapping, held together by a plain unprinted kraft paper band, both turned three-quarters to the right. Seamless warm off-white sweep, large soft key from the upper left with gentle fill from the right, short soft shadow to the lower right, 5200K, 85mm at eye level, fully in focus, even margin all round. | 2048x2048 | 1:1 |
| `build-your-own-box.jpg` | Build Your Own Box (`BDL-MIX`, bundle B3) | Three unprinted 250 g coffee pouches in pale sand, deep charcoal and warm terracotta standing in a loose row inside an open shallow kraft tray, the terracotta one turned outward as if just chosen. Seamless warm off-white sweep, large soft key from the upper left with gentle fill from the right, short soft shadow to the lower right, 5200K, 85mm at eye level, fully in focus, even margin all round. | 2048x2048 | 1:1 |
| `brew-starter-kit.jpg` | Brew Starter Kit (`BDL-KIT`, bundle B4) | A brushed steel hand grinder with a walnut knob, a clear glass cone pourover dripper with a walnut collar and one unprinted pale sand coffee pouch, arranged in a shallow triangle and seen from a raised three-quarter angle. Seamless warm off-white sweep, large soft key from the upper left with gentle fill from the right, short soft shadows to the lower right, 5200K, 85mm, fully in focus, even margin all round. | 2048x2048 | 1:1 |
| `digital-brewing-pack.jpg` | Digital Brewing Pack (`BDL-DIGI`, bundle B5) | A slim deep-green cloth hardcover book with a blind-debossed concentric-circle motif and no lettering, lying flat in front of a compact matte cream and brushed steel tabletop roaster with a clear glass drum, the two overlapping slightly. Seamless warm off-white sweep, large soft key from the upper left with gentle fill from the right, short soft shadow to the lower right, 5200K, 85mm, fully in focus, even margin all round. | 2048x2048 | 1:1 |

Thirteen files, one per product.

## The home hero

`home-hero.jpg` lives in `dev/demo-theme/assets/`, not in `docker/demo-store/images/`. It is referenced by the child theme's `demo-theme/home` pattern through `get_theme_file_uri()`, so it is served from the theme directory, never sideloaded and never an attachment — the seeder must not see it, and a bind-mounted theme asset needs no image rebuild to change. It is also not shot to the product rules above: it is a wide band, and the headline sits above it rather than over it, so the frame carries no reserved empty area.

| Filename | Use | Subject | Dimensions | Ratio |
|---|---|---|---|---|
| `home-hero.jpg` | Home page hero band | A wide roastery still life: a loose row of unprinted kraft and charcoal coffee pouches with a brushed steel hand grinder and a clear glass pourover dripper. Seamless warm off-white sweep, large soft key from the upper left with gentle fill from the right, short soft shadows to the lower right, 5200K, eye level, fully in focus, no people. | 1376x768 | 16:9 |

The delivered file is 1376x768. It renders at the theme's `wideSize` of 1320px, so it never upscales; a replacement only has to stay at or above that width.

## Deliberately not in the list

- **The two `Cascade House Blend` variations (`CSC-250`, `CSC-KG`)** — `WC_Product_Variation::get_image_id()` falls back to the parent's featured image, and `cascade-house-blend.jpg` shows both sizes in one frame, so a variation image would only re-crop the same subject.
- **Category images** — nothing in the child theme renders `product_cat` thumbnails; six more files for markup that does not exist.
- **A site logo** — a wordmark is text, which is banned above. The header uses the site title.
