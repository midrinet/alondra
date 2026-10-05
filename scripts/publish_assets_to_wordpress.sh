#!/bin/bash

# Publishes the WordPress.org listing assets (banner, icon, screenshots) to the
# plugin's SVN repository.
#
# WordPress.org serves the listing images from an assets/ directory at the ROOT
# of the SVN repository — a sibling of trunk/ and tags/, never inside either. It
# is independent of the published version, so this runs separately from
# scripts/publish_to_wordpress.sh and needs no release.
#
# The sources are the images in .wordpress-org/ of this git checkout; they are not
# in the release ZIP. The decision to commit is a content comparison against
# what SVN already holds, so running this twice in a row is a no-op.
#
# Accepted names — WordPress.org silently ignores everything else:
#
#   banner-772x250, banner-1544x500      .png .jpg           max 4MB
#   icon-128x128, icon-256x256           .png .jpg .gif      max 1MB
#   icon.svg                             (needs a PNG fallback alongside it)
#   screenshot-N                         .png .jpg           max 10MB
#
# Banners and screenshots also take a locale suffix — rtl, or a language code with
# an optional region: banner-772x250-rtl.png, screenshot-1-de.png, banner-772x250-es_ES.jpg.
# A localized file is a variant of the asset it names, not an extra screenshot.
# Filenames are lowercase; only the region half of a locale may be uppercase.
#
# Usage: scripts/publish_assets_to_wordpress.sh [options]
#
#   --prune                  svn rm files that exist in SVN but not in .wordpress-org/.
#                            Without it they are reported and left alone.
#   --force                  Skip the extra confirmation asked when screenshots change.
#   --skip-dimension-check   Do not verify that banner/icon pixel sizes match their
#                            filenames (only use it when no image tool is available).
#   --dry-run                Print the plan; never svn add, rm or commit.
#   -h, --help               Show this help.
#
# Requires: svn, and ImageMagick's identify or python3 for the dimension check.
# Set SVN_USERNAME to skip the WordPress.org username prompt.
# Set SVN_REPO to point at another repository (used by the tests).

# Colors for the output
GREEN=$(tput setaf 2)
RED=$(tput setaf 1)
YELLOW=$(tput setaf 3)
NC=$(tput sgr0) # No color

PLUGIN_SLUG="alondra"

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ASSETS_DIR="$REPO_ROOT/.wordpress-org"

ROOT_PATH="${TMPDIR:-/tmp}"
TEMP_SVN_REPO="$ROOT_PATH/${PLUGIN_SLUG}-svn-assets"

SVN_REPO="${SVN_REPO:-https://plugins.svn.wordpress.org/${PLUGIN_SLUG}/}"

read_input() {
	local message="$1" # Message to show to the user
	read -r -e -p "$message" input_value
	echo "$input_value"
}

usage() {
	sed -n '3,39p' "${BASH_SOURCE[0]}" | sed -e 's/^# \{0,1\}//'
}

# Prints "<width> <height>" for an image, or fails if it cannot be determined.
image_dimensions() {
	local file="$1" dims
	if command -v identify > /dev/null 2>&1; then
		dims=$(identify -format '%w %h' "$file" 2> /dev/null)
		if [[ -n "$dims" ]]; then
			echo "$dims"
			return 0
		fi
	fi
	dims=$(python3 - "$file" 2> /dev/null <<- 'PY'
		import struct, sys
		with open(sys.argv[1], 'rb') as fh:
		    head = fh.read(24)
		    if head[:8] == b'\x89PNG\r\n\x1a\n':
		        print(*struct.unpack('>II', head[16:24]))
		    elif head[:4] == b'GIF8':
		        print(*struct.unpack('<HH', head[6:10]))
		    elif head[:2] == b'\xff\xd8':
		        fh.seek(2)
		        while True:
		            byte = fh.read(1)
		            if not byte:
		                break
		            if byte != b'\xff':
		                continue
		            marker = fh.read(1)
		            while marker == b'\xff':
		                marker = fh.read(1)
		            if not marker:
		                break
		            code = marker[0]
		            if 0xc0 <= code <= 0xcf and code not in (0xc4, 0xc8, 0xcc):
		                fh.read(3)
		                height, width = struct.unpack('>HH', fh.read(4))
		                print(width, height)
		                break
		            size = struct.unpack('>H', fh.read(2))[0]
		            fh.seek(size - 2, 1)
	PY
	)
	if [[ -n "$dims" ]]; then
		echo "$dims"
		return 0
	fi
	return 1
}


PRUNE=0
FORCE=0
SKIP_DIMENSION_CHECK=0
DRY_RUN=0

while [[ $# -gt 0 ]]; do
	case "$1" in
		--prune) PRUNE=1 ;;
		--force) FORCE=1 ;;
		--skip-dimension-check) SKIP_DIMENSION_CHECK=1 ;;
		--dry-run) DRY_RUN=1 ;;
		-h | --help)
			usage
			exit 0
			;;
		*)
			echo "${RED}Error: Unknown option '$1'.${NC}"
			usage
			exit 1
			;;
	esac
	shift
done

set -e
clear

echo "${NC}--------------------------------"
echo "WordPress.org LISTING ASSETS"
echo "--------------------------------"
echo "Source:     $ASSETS_DIR"
echo "Repository: ${SVN_REPO}assets/"
echo ""

# Collect the local files
if [[ ! -d "$ASSETS_DIR" ]]; then
	echo "${RED}Error: $ASSETS_DIR does not exist.${NC}"
	exit 1
fi

LOCAL_FILES=()
while IFS= read -r -d '' ENTRY; do
	LOCAL_FILES+=("$(basename "$ENTRY")")
# *-source.svg is the editable master a banner is exported from, not a listing asset.
done < <(find "$ASSETS_DIR" -mindepth 1 -maxdepth 1 ! -name '*-source.svg' -print0 | sort -z)

if [[ ${#LOCAL_FILES[@]} -eq 0 ]]; then
	echo "${RED}Error: $ASSETS_DIR is empty, there is nothing to publish.${NC}"
	exit 1
fi

# Check 1: only names WordPress.org actually recognises, each within its size limit.
# It ignores anything else silently, so a typo would commit fine and change nothing
# on the listing. The locale suffix stays deliberately loose on the token itself —
# WordPress.org accepts any locale it knows — but not so loose that "screenshoot-1"
# passes as "screenshoot" plus a locale.
LOCALE_RE='(rtl|[a-z]{2,3}(_[A-Za-z]{2})?)'
BANNER_RE="^banner-(772x250|1544x500)(-${LOCALE_RE})?\.(png|jpg)$"
ICON_RE='^icon-(128x128|256x256)\.(png|jpg|gif)$'
SCREENSHOT_RE="^screenshot-([0-9]+)(-${LOCALE_RE})?\.(png|jpg)$"

BANNER_MAX=$((4 * 1024 * 1024))
ICON_MAX=$((1 * 1024 * 1024))
SCREENSHOT_MAX=$((10 * 1024 * 1024))

echo "${NC}🔎 Checking the file names..."
SCREENSHOT_INDEXES=()
HAS_ICON_SVG=0
HAS_ICON_RASTER=0
for NAME in "${LOCAL_FILES[@]}"; do
	if [[ "$NAME" =~ $SCREENSHOT_RE ]]; then
		# screenshot-1-de is the German rendering of screenshot 1, not a screenshot of
		# its own: it gets no index, so it needs no caption and closes no numbering gap.
		[[ -n "${BASH_REMATCH[2]}" ]] || SCREENSHOT_INDEXES+=("${BASH_REMATCH[1]}")
		LIMIT=$SCREENSHOT_MAX
	elif [[ "$NAME" =~ $BANNER_RE ]]; then
		LIMIT=$BANNER_MAX
	elif [[ "$NAME" =~ $ICON_RE ]]; then
		HAS_ICON_RASTER=1
		LIMIT=$ICON_MAX
	elif [[ "$NAME" == "icon.svg" ]]; then
		HAS_ICON_SVG=1
		LIMIT=$ICON_MAX
	else
		LOWER="${NAME,,}"
		if [[ "$LOWER" != "$NAME" ]] && { [[ "$LOWER" =~ $SCREENSHOT_RE ]] || [[ "$LOWER" =~ $BANNER_RE ]] || [[ "$LOWER" =~ $ICON_RE ]] || [[ "$LOWER" == "icon.svg" ]]; }; then
			echo "${RED}Error: '$NAME' has uppercase letters in its name.${NC}"
			echo "WordPress.org requires lowercase filenames; uppercase names won't work, and the"
			echo "file is ignored without an error. Rename it to '$LOWER'."
		else
			echo "${RED}Error: '$NAME' is not a WordPress.org listing asset name.${NC}"
			echo "Allowed: banner-772x250, banner-1544x500 (.png or .jpg), icon-128x128,"
			echo "         icon-256x256 (.png, .jpg or .gif), icon.svg, screenshot-N (.png or .jpg)."
			echo "Banners and screenshots also take a locale suffix, e.g. banner-772x250-rtl.png"
			echo "or screenshot-1-de.png."
			echo "WordPress.org ignores every other name without reporting an error, so it would be"
			echo "committed and never shown. Rename or remove it."
		fi
		exit 1
	fi

	SIZE=$(wc -c < "$ASSETS_DIR/$NAME")
	if [[ $SIZE -gt $LIMIT ]]; then
		echo "${RED}Error: '$NAME' is $(awk -v b="$SIZE" 'BEGIN { printf "%.2f", b / 1048576 }') MB ($SIZE bytes), over the $((LIMIT / 1024 / 1024)) MB limit WordPress.org sets for this kind of asset.${NC}"
		exit 1
	fi
done
echo "${GREEN}✅ All ${#LOCAL_FILES[@]} file names are valid and within their size limits.${NC}"

if [[ $HAS_ICON_SVG -eq 1 && $HAS_ICON_RASTER -eq 0 ]]; then
	echo "${YELLOW}⚠️  Warning: icon.svg has no PNG fallback (icon-128x128.* or icon-256x256.*).${NC}"
	echo "   WordPress.org requires the pair; without it the icon will not display properly in"
	echo "   older browsers or on Facebook."
fi

# Check 2: the pixel size has to match what the name claims.
if [[ $SKIP_DIMENSION_CHECK -eq 1 ]]; then
	echo "${YELLOW}⚠️  Skipping the dimension check (--skip-dimension-check).${NC}"
else
	echo "${NC}🔎 Checking the image dimensions..."
	for NAME in "${LOCAL_FILES[@]}"; do
		# Localized variants carry the same dimensions in the name, so they are checked
		# too; icon.svg has no pixel size and never matches this.
		if [[ "$NAME" =~ ^(banner|icon)-([0-9]+)x([0-9]+)(-[A-Za-z_]+)?\.(png|jpg|gif)$ ]]; then
			EXPECTED="${BASH_REMATCH[2]} ${BASH_REMATCH[3]}"
			ACTUAL=$(image_dimensions "$ASSETS_DIR/$NAME") || {
				echo "${RED}Error: Unable to determine the dimensions of '$NAME'.${NC}"
				echo "Install ImageMagick (identify) or python3, or re-run with --skip-dimension-check"
				echo "if you have verified the sizes yourself."
				exit 1
			}
			if [[ "$ACTUAL" != "$EXPECTED" ]]; then
				echo "${RED}Error: '$NAME' is ${ACTUAL// /x} pixels, but its name claims ${EXPECTED// /x}.${NC}"
				exit 1
			fi
		fi
	done
	echo "${GREEN}✅ Banner and icon dimensions match their names.${NC}"
fi

# Checks 3 and 4: the screenshot series must run 1..N with one file per index.
SCREENSHOT_COUNT=${#SCREENSHOT_INDEXES[@]}
if [[ $SCREENSHOT_COUNT -gt 0 ]]; then
	echo "${NC}🔎 Checking the screenshot numbering..."
	DUPLICATE=$(printf '%s\n' "${SCREENSHOT_INDEXES[@]}" | sort -n | uniq -d | head -n 1)
	if [[ -n "$DUPLICATE" ]]; then
		echo "${RED}Error: screenshot-$DUPLICATE exists with more than one extension.${NC}"
		echo "WordPress.org cannot tell which one to show. Keep a single file per index."
		exit 1
	fi
	INDEX=1
	while read -r NUMBER; do
		if [[ "$NUMBER" != "$INDEX" ]]; then
			echo "${RED}Error: The screenshots are not numbered contiguously from 1 (expected screenshot-$INDEX, found screenshot-$NUMBER).${NC}"
			echo "WordPress.org stops the series at the first gap."
			exit 1
		fi
		INDEX=$((INDEX + 1))
	done < <(printf '%s\n' "${SCREENSHOT_INDEXES[@]}" | sort -n)
	echo "${GREEN}✅ Screenshots 1..$SCREENSHOT_COUNT are contiguous.${NC}"
fi

# Check 5: the captions live in the readme that is already PUBLISHED, which is
# the one the listing renders — not the working tree's copy.
echo "${NC}🔎 Reading the published readme.txt from SVN..."
SVN_README=$(svn cat "${SVN_REPO}trunk/readme.txt" 2> /dev/null) || SVN_README=""
if [[ -z "$SVN_README" ]]; then
	echo "${YELLOW}⚠️  Warning: Unable to read ${SVN_REPO}trunk/readme.txt, skipping the caption check.${NC}"
else
	CAPTION_COUNT=$(printf '%s\n' "$SVN_README" | awk '
		/^==[[:space:]]*Screenshots[[:space:]]*==/ { inside = 1; next }
		/^==/ { inside = 0 }
		inside && /^[0-9]+\./ { count++ }
		END { print count + 0 }
	')
	if [[ "$CAPTION_COUNT" != "$SCREENSHOT_COUNT" ]]; then
		echo "${RED}Error: The published readme.txt lists $CAPTION_COUNT screenshot caption(s), but $ASSETS_DIR holds $SCREENSHOT_COUNT screenshot file(s).${NC}"
		if [[ $SCREENSHOT_COUNT -lt $CAPTION_COUNT ]]; then
			echo "Captions without an image render as a broken entry."
		else
			echo "Images without a caption are orphans nobody links to."
		fi
		echo "Fix it either by adding the missing image to .wordpress-org/, or by publishing a"
		echo "release whose readme.txt == Screenshots == section matches these files."
		exit 1
	fi
	echo "${GREEN}✅ $CAPTION_COUNT published caption(s) match $SCREENSHOT_COUNT screenshot file(s).${NC}"
fi
echo ""

# Get the assets/ directory out of SVN. Everything else stays unfetched.
echo "${NC}⬇️  Getting the WordPress.org plugin repository..."
rm -Rf "$TEMP_SVN_REPO"
svn checkout --depth immediates "$SVN_REPO" "$TEMP_SVN_REPO" -q || {
	echo "${RED}Error: Unable to checkout repo $SVN_REPO${NC}"
	exit 1
}
cd "$TEMP_SVN_REPO"

ASSETS_ARE_NEW=0
if [[ -d assets ]]; then
	svn update --set-depth infinity assets -q || {
		echo "${RED}Error: Unable to update assets/.${NC}"
		exit 1
	}
else
	echo "${YELLOW}ℹ️  There is no assets/ directory in SVN yet, it will be created.${NC}"
	mkdir assets
	ASSETS_ARE_NEW=1
fi
echo ""

# Compare by content, so an untouched file is never committed again
echo "${NC}🔎 Comparing the local files with SVN..."
NEW_FILES=()
CHANGED_FILES=()
UNCHANGED_FILES=()
SVN_ONLY_FILES=()

for NAME in "${LOCAL_FILES[@]}"; do
	if [[ ! -f "assets/$NAME" ]]; then
		NEW_FILES+=("$NAME")
	# cmp rather than hashing: a missing hash tool compares empty to empty, so every file
	# would look unchanged and the run would publish nothing while reporting success.
	elif cmp -s "$ASSETS_DIR/$NAME" "assets/$NAME"; then
		UNCHANGED_FILES+=("$NAME")
	else
		CHANGED_FILES+=("$NAME")
	fi
done

while IFS= read -r -d '' ENTRY; do
	NAME="$(basename "$ENTRY")"
	[[ -f "$ASSETS_DIR/$NAME" ]] || SVN_ONLY_FILES+=("$NAME")
done < <(find assets -mindepth 1 -maxdepth 1 -type f -print0 | sort -z)

echo "  ${#UNCHANGED_FILES[@]} unchanged, ${#CHANGED_FILES[@]} changed, ${#NEW_FILES[@]} new, ${#SVN_ONLY_FILES[@]} only in SVN."
echo ""

if [[ ${#SVN_ONLY_FILES[@]} -gt 0 && $PRUNE -eq 0 ]]; then
	echo "${YELLOW}⚠️  These files exist in SVN but not in .wordpress-org/ and will be LEFT ALONE:${NC}"
	printf '   %s\n' "${SVN_ONLY_FILES[@]}"
	echo "   Re-run with --prune to remove them."
	echo ""
fi

# Nothing to do
if [[ ${#NEW_FILES[@]} -eq 0 && ${#CHANGED_FILES[@]} -eq 0 ]] && [[ ${#SVN_ONLY_FILES[@]} -eq 0 || $PRUNE -eq 0 ]]; then
	echo "${GREEN}✅ The WordPress.org listing assets are already up to date. Nothing to do.${NC}"
	cd "$REPO_ROOT"
	rm -Rf "$TEMP_SVN_REPO"
	exit 0
fi

# Show the plan
echo "${NC}📋 Plan:"
for NAME in "${NEW_FILES[@]}"; do
	echo "   ${GREEN}add${NC}     $NAME"
done
for NAME in "${CHANGED_FILES[@]}"; do
	echo "   ${YELLOW}update${NC}  $NAME"
done
if [[ $PRUNE -eq 1 ]]; then
	for NAME in "${SVN_ONLY_FILES[@]}"; do
		echo "   ${RED}remove${NC}  $NAME"
	done
fi
echo ""

# Soft gate: screenshots describe a specific UI, the banner and the icon do not.
CHANGED_SCREENSHOTS=()
for NAME in "${NEW_FILES[@]}" "${CHANGED_FILES[@]}"; do
	if [[ "$NAME" == screenshot-* ]]; then CHANGED_SCREENSHOTS+=("$NAME"); fi
done
if [[ $PRUNE -eq 1 ]]; then
	for NAME in "${SVN_ONLY_FILES[@]}"; do
		if [[ "$NAME" == screenshot-* ]]; then CHANGED_SCREENSHOTS+=("$NAME"); fi
	done
fi

if [[ ${#CHANGED_SCREENSHOTS[@]} -gt 0 ]]; then
	echo "${YELLOW}⚠️  This changes ${#CHANGED_SCREENSHOTS[@]} screenshot(s).${NC}"
	echo "   Screenshots show a specific UI, and this publishes them without publishing code,"
	echo "   so the listing may show a UI the released version does not have."
	if [[ $FORCE -eq 1 ]]; then
		echo "   ${YELLOW}Confirmed by --force.${NC}"
	else
		read -r -p "${YELLOW}Publish screenshots that may not match the released version? [y/N]: ${NC}" SCREENSHOTS_OK
		if [[ "$SCREENSHOTS_OK" != "y" && "$SCREENSHOTS_OK" != "Y" ]]; then
			echo "${RED}Aborting: Screenshot publication cancelled.${NC}"
			exit 1
		fi
	fi
	echo ""
fi

if [[ $DRY_RUN -eq 1 ]]; then
	echo "${YELLOW}🚫 --dry-run: nothing was added, removed or committed.${NC}"
	cd "$REPO_ROOT"
	rm -Rf "$TEMP_SVN_REPO"
	exit 0
fi

if [[ -z "$SVN_USERNAME" ]]; then
	SVN_USERNAME=$(read_input "${YELLOW}WordPress.org username: ${NC}")
fi

read -r -p "${YELLOW}Ready to publish the listing assets to WordPress.org as $SVN_USERNAME? [y/N]: ${NC}" CONTINUE
if [[ "$CONTINUE" != "y" && "$CONTINUE" != "Y" ]]; then
	echo "${RED}Aborting: Publication cancelled.${NC}"
	exit 1
fi
echo ""

# Copy and stage
echo "${NC}🖼️  Copying the listing assets..."
for NAME in "${NEW_FILES[@]}" "${CHANGED_FILES[@]}"; do
	cp "$ASSETS_DIR/$NAME" "assets/$NAME"
done

if [[ $ASSETS_ARE_NEW -eq 1 ]]; then
	svn add assets --auto-props --parents --depth infinity -q
else
	for NAME in "${NEW_FILES[@]}"; do
		svn add "assets/$NAME" --auto-props --parents -q
	done
fi

if [[ $PRUNE -eq 1 ]]; then
	for NAME in "${SVN_ONLY_FILES[@]}"; do
		svn rm --force "assets/$NAME" -q
	done
fi

echo "${NC}📋 Showing SVN status"
svn status
echo ""

echo "⬆️  Committing to WordPress.org...this may take a while..."
svn --username "$SVN_USERNAME" commit -m "Update the plugin listing assets: ${#NEW_FILES[@]} added, ${#CHANGED_FILES[@]} updated, $([[ $PRUNE -eq 1 ]] && echo "${#SVN_ONLY_FILES[@]}" || echo 0) removed." || {
	echo "${RED}Error: Unable to commit.${NC}"
	exit 1
}

echo ""
echo "${GREEN}✅ Listing assets successfully committed to WordPress.org!${NC}"
echo "They can take a few minutes to appear on the plugin page."
echo "Cleaning up temporary directories..."
cd "$REPO_ROOT"
rm -Rf "$TEMP_SVN_REPO"
echo "${GREEN}✅ Temporary directories cleaned up.${NC}"
echo ""
echo "${GREEN}✅ DONE!${NC}"
