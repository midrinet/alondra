#!/bin/bash

# Publishes a GitHub release ZIP to the WordPress.org plugin repository.
#
# The ZIP is the one build-release.yml uploaded to the GitHub release
# (alondra-<version>.zip, containing a single alondra/ folder), so what reaches
# WordPress.org is exactly what scripts/release built — this script never rebuilds.
#
# This publishes trunk/ and tags/ only. The listing images (banner, icon, screenshots) live
# in assets/ at the root of the SVN repository, are independent of the published version, and
# are published separately by scripts/publish_assets_to_wordpress.sh.
#
# Requires: gh (authenticated), svn, unzip.
# Set SVN_USERNAME to skip the WordPress.org username prompt.
# Non-interactive (build-release.yml): VERSION, ZIP_FILE (a local ZIP instead of the GitHub
# release download), SVN_PASSWORD and ASSUME_YES=1, which answers yes to every confirmation.
# Set SVN_REPO to point at another repository (used by the tests).

# Colors for the output
GREEN=$(tput setaf 2)
RED=$(tput setaf 1)
YELLOW=$(tput setaf 3)
NC=$(tput sgr0) # No color

PLUGIN_SLUG="alondra"
GITHUB_REPO="midrinet/alondra"

ROOT_PATH="${TMPDIR:-/tmp}"
TEMP_GITHUB_REPO="$ROOT_PATH/${PLUGIN_SLUG}-git"
TEMP_SVN_REPO="$ROOT_PATH/${PLUGIN_SLUG}-svn"

SVN_REPO="${SVN_REPO:-https://plugins.svn.wordpress.org/${PLUGIN_SLUG}/}"

read_input() {
	local message="$1" # Message to show to the user
	read -e -p "$message" input_value
	echo "$input_value"
}

# Validates the version format (X.Y.Z)
validate_version() {
	local version=$1
	if [[ $version =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
		return 0
	else
		return 1
	fi
}

set -e
if [ -t 1 ]; then clear; fi

echo "${NC}--------------------------------"
echo "Github to WordPress.org RELEASER"
echo "--------------------------------"
VERSION="${VERSION:-$(read_input "${YELLOW}Write the version to release: ${NC}")}"
until validate_version "$VERSION"; do
	if [[ -n "$ASSUME_YES" ]]; then
		echo "${RED}Error: Invalid version $VERSION. Use X.Y.Z (e.g., 1.0.0)."
		exit 1
	fi
    VERSION=$(read_input "${RED}Invalid version format. Use X.Y.Z (e.g., 1.0.0): ${YELLOW}")
done

echo ""

# Delete old temporary directories
echo "🗑️ Removing the temporary directories if they exist:"
rm -Rf "$TEMP_GITHUB_REPO"
echo "$TEMP_GITHUB_REPO"
rm -Rf "$TEMP_SVN_REPO"
echo "$TEMP_SVN_REPO"
mkdir -p "$TEMP_GITHUB_REPO"
echo ""

# Download the release ZIP file
if [[ -n "$ZIP_FILE" ]]; then
	cp "$ZIP_FILE" "$TEMP_GITHUB_REPO/"
else
	echo "${NC}⬇️  Downloading the release ZIP file from GitHub..."
	gh release download "$VERSION" --repo "$GITHUB_REPO" --pattern "${PLUGIN_SLUG}-*.zip" --dir "$TEMP_GITHUB_REPO" || {
		echo "${RED}Error: Unable to download the release ZIP file for version $VERSION."
		exit 1
	}
fi
echo ""

# Unzip the downloaded file
echo "${NC}📦 Unzipping the release ZIP file..."
cd "$TEMP_GITHUB_REPO"
unzip -q "${PLUGIN_SLUG}-"*.zip || {
	echo "${RED}Error: Unable to unzip the release ZIP file."
	exit 1
}
rm "${PLUGIN_SLUG}-"*.zip
PLUGIN_DIR="$TEMP_GITHUB_REPO/$PLUGIN_SLUG"
cd "$PLUGIN_DIR" || {
	echo "${RED}Error: The ZIP does not contain a $PLUGIN_SLUG/ folder."
	exit 1
}
echo ""

# Check the header version in alondra.php
VERSION_IN_FILE=$(grep -m1 "^ \* Version:" "$PLUGIN_SLUG.php" | awk '{print $3}')
if [[ "$VERSION_IN_FILE" != "$VERSION" ]]; then
	echo "${RED}Error: The header version in $PLUGIN_SLUG.php ($VERSION_IN_FILE) does not match the release version $VERSION."
	exit 1
fi
echo "${GREEN}✅ Header version in $PLUGIN_SLUG.php matches the release version $VERSION.${NC}"

# Check if the readme.txt file has the correct stable tag
VERSION_IN_FILE=$(grep -m1 "^Stable tag:" readme.txt | awk '{print $3}')
if [[ "$VERSION_IN_FILE" != "$VERSION" ]]; then
	echo "${RED}Error: The stable tag in readme.txt ($VERSION_IN_FILE) does not match the release version $VERSION."
	exit 1
fi
echo "${GREEN}✅ Stable tag in readme.txt matches the release version $VERSION.${NC}"

# Check if readme.txt has the changelog for the version
if ! grep -q "=\s*$VERSION\s*=" readme.txt; then
	echo "${RED}Error: The changelog for version $VERSION is not found in readme.txt."
	exit 1
fi
echo "${GREEN}✅ Changelog for version $VERSION found in readme.txt.${NC}"

# Ask for confirmation on the POT readiness
POT_READY="${ASSUME_YES:+y}"
[[ -n "$POT_READY" ]] || read -r -p "${YELLOW}Is the POT file updated? [y/N]: ${NC}" POT_READY
if [[ "$POT_READY" != "y" && "$POT_READY" != "Y" ]]; then
	echo "${RED}Aborting: Please update the POT file before proceeding."
	exit 1
fi
echo "${GREEN}✅ POT file is up to date.${NC}"
echo ""

# Checkout and update the SVN repository
echo "${NC}⬇️  Getting the WordPress.org plugin repository..."
svn checkout "$SVN_REPO" "$TEMP_SVN_REPO" || {
	echo "${RED}Error: Unable to checkout repo $SVN_REPO"
	exit 1
}
cd "$TEMP_SVN_REPO"
svn update || {
	echo "${RED}Error: Unable to update SVN."
	exit 1
}

# Check if the current version already exists in SVN
if svn ls "tags/$VERSION" > /dev/null 2>&1; then
	echo "${RED}Aborting: The version $VERSION already exists in WordPress.org."
	exit 1
fi

# Check if there is a tag greater than the current version
LATEST_TAG=$(ls tags | grep -Eo '[0-9]+\.[0-9]+\.[0-9]+' | sort -V | tail -n 1)
if [[ -n "$LATEST_TAG" && "$(printf '%s\n' "$VERSION" "$LATEST_TAG" | sort -V | tail -n 1)" != "$VERSION" ]]; then
	echo "${RED}Aborting: The version $VERSION is less than the latest tag $LATEST_TAG."
	exit 1
fi
echo ""

if [[ -z "$SVN_USERNAME" ]]; then
	SVN_USERNAME=$(read_input "${YELLOW}WordPress.org username: ${NC}")
fi

# Ask for confirmation to proceed with the release
CONTINUE="${ASSUME_YES:+y}"
[[ -n "$CONTINUE" ]] || read -r -p "${YELLOW}Ready to release version $VERSION to WordPress.org as $SVN_USERNAME? [y/N]: ${NC}" CONTINUE
if [[ "$CONTINUE" != "y" && "$CONTINUE" != "Y" ]]; then
	echo "${RED}Aborting: Release cancelled."
	exit 1
fi
echo ""

# DELETE OLD TRUNK
echo "${NC}🗑️  Deleting old trunk..."
rm -rf trunk

# Copy the contents of the plugin folder to the SVN trunk
echo "${NC}📂 Copying plugin files to SVN trunk..."
cp -R "$PLUGIN_DIR" trunk

# DO THE ADD ALL NOT KNOWN FILES UNIX COMMAND
svn add --force * --auto-props --parents --depth infinity -q

# DO THE REMOVE ALL DELETED FILES UNIX COMMAND
MISSING_PATHS=$( svn status | sed -e '/^!/!d' -e 's/^!//' )
# iterate over filepaths
for MISSING_PATH in $MISSING_PATHS; do
    svn rm --force "$MISSING_PATH"
done

# COPY TRUNK TO TAGS/$VERSION
echo "${NC}📦 Copying trunk to new tag..."
svn copy trunk "tags/${VERSION}" || {
	echo "${RED}Error: Unable to create tag."
	exit 1
}

# DO SVN COMMIT
if [ -t 1 ]; then clear; fi
echo "${NC}📋 Showing SVN status"
svn status
echo ""

echo "⬆️  Committing to WordPress.org...this may take a while..."
SVN_AUTH=(--username "$SVN_USERNAME")
if [[ -n "$SVN_PASSWORD" ]]; then
	SVN_AUTH+=(--password "$SVN_PASSWORD" --non-interactive)
fi
svn "${SVN_AUTH[@]}" commit -m "Release ${VERSION}, see readme.txt for the changelog." || {
	echo "${RED}Error: Unable to commit."
	exit 1
}

echo ""
echo "${GREEN}✅ Release ${VERSION} successfully committed to WordPress.org!${NC}"
echo "Cleaning up temporary directories..."
# Clean up temporary directories
rm -rf "$TEMP_GITHUB_REPO"
rm -rf "$TEMP_SVN_REPO"
echo "${GREEN}✅ Temporary directories cleaned up.${NC}"
echo ""
echo "${YELLOW}ℹ️  Reminder: the listing images (banner, icon, screenshots) are NOT part of this"
echo "   release. If they changed, publish them with scripts/publish_assets_to_wordpress.sh.${NC}"
echo ""
echo "${GREEN}✅ DONE!${NC}"
