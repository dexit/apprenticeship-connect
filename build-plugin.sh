#!/bin/bash
#
# Build WordPress Plugin for Distribution
#
# This script creates a deployable zip file for WordPress plugin upload.
# It includes built assets and production dependencies only.
#

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  WordPress Plugin Build Script${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Get plugin details
PLUGIN_SLUG="apprenticeship-connect"
PLUGIN_VERSION=$(grep "Version:" apprenticeship-connect.php | awk '{print $3}')
BUILD_DIR="build"
DIST_DIR="dist"
ZIP_NAME="${PLUGIN_SLUG}.zip"

echo -e "${YELLOW}Plugin:${NC} ${PLUGIN_SLUG}"
echo -e "${YELLOW}Version:${NC} ${PLUGIN_VERSION}"
echo ""

# Clean previous builds
echo -e "${BLUE}→ Cleaning previous builds...${NC}"
rm -rf "${BUILD_DIR}"
rm -rf "${DIST_DIR}"
rm -f "${ZIP_NAME}"
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}"
mkdir -p "${DIST_DIR}"

# Prepare assets directory (no build needed for traditional PHP forms)
echo -e "${BLUE}→ Preparing assets directory...${NC}"
mkdir -p assets/build
echo "/* Apprenticeship Connect - Import Tasks Plugin */" > assets/build/.keep

# No external dependencies needed for lean version

# Copy plugin files to build directory
echo -e "${BLUE}→ Copying plugin files...${NC}"

# Function to check if file/dir should be excluded
should_exclude() {
    local path=$1
    # Read .distignore and check if path matches
    while IFS= read -r pattern; do
        # Skip comments and empty lines
        [[ "$pattern" =~ ^#.*$ ]] && continue
        [[ -z "$pattern" ]] && continue

        # Remove leading/trailing whitespace
        pattern=$(echo "$pattern" | xargs)

        # Check if path matches pattern
        if [[ "$path" == $pattern* ]] || [[ "$path" == *"$pattern"* ]]; then
            return 0  # Should exclude
        fi
    done < .distignore
    return 1  # Should include
}

# Copy all files (exclude build/dist directories to avoid copying into itself)
echo -e "  Copying files..."
find . -mindepth 1 -maxdepth 1 ! -name "${BUILD_DIR}" ! -name "${DIST_DIR}" ! -name "*.zip" -exec cp -r {} "${BUILD_DIR}/${PLUGIN_SLUG}/" \;

# Remove files matching .distignore patterns
echo -e "  Removing excluded files..."
while IFS= read -r pattern; do
    # Skip comments and empty lines
    [[ "$pattern" =~ ^#.*$ ]] && continue
    [[ -z "$pattern" ]] && continue

    # Remove leading/trailing whitespace and slashes
    pattern=$(echo "$pattern" | xargs | sed 's:^/::')

    # Remove matching files/directories
    if [ -e "${BUILD_DIR}/${PLUGIN_SLUG}/${pattern}" ]; then
        rm -rf "${BUILD_DIR}/${PLUGIN_SLUG}/${pattern}"
        echo -e "    Removed: ${pattern}"
    fi
done < .distignore

# Also remove build directories
rm -rf "${BUILD_DIR}/${PLUGIN_SLUG}/${BUILD_DIR}"
rm -rf "${BUILD_DIR}/${PLUGIN_SLUG}/${DIST_DIR}"
find "${BUILD_DIR}/${PLUGIN_SLUG}" -name "*.zip" -delete 2>/dev/null || true

# Verify critical files are present
echo -e "${BLUE}→ Verifying build...${NC}"

REQUIRED_FILES=(
    "${BUILD_DIR}/${PLUGIN_SLUG}/apprenticeship-connect.php"
    "${BUILD_DIR}/${PLUGIN_SLUG}/includes"
    "${BUILD_DIR}/${PLUGIN_SLUG}/assets/build"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [ ! -e "$file" ]; then
        echo -e "${RED}✗ Required file/directory missing: ${file}${NC}"
        exit 1
    fi
done

# Verify at least some built assets exist (if any)
if [ -d "${BUILD_DIR}/${PLUGIN_SLUG}/assets/build" ] && [ ! -z "$(find ${BUILD_DIR}/${PLUGIN_SLUG}/assets/build -type f)" ]; then
    echo -e "${GREEN}✓ Built assets found${NC}"
fi

echo -e "${GREEN}✓ All required files present${NC}"

# Show what's included
echo -e "${BLUE}→ Build contents:${NC}"
echo ""
du -sh "${BUILD_DIR}/${PLUGIN_SLUG}"/* | sort -h | sed 's/^/  /'
echo ""

# Create ZIP file
echo -e "${BLUE}→ Creating ZIP file...${NC}"
cd "${BUILD_DIR}"
zip -r "../${DIST_DIR}/${ZIP_NAME}" "${PLUGIN_SLUG}" -q
cd ..

# Verify ZIP
if [ ! -f "${DIST_DIR}/${ZIP_NAME}" ]; then
    echo -e "${RED}✗ Failed to create ZIP file${NC}"
    exit 1
fi

# Show ZIP details
ZIP_SIZE=$(du -h "${DIST_DIR}/${ZIP_NAME}" | cut -f1)
echo -e "${GREEN}✓ ZIP created: ${DIST_DIR}/${ZIP_NAME} (${ZIP_SIZE})${NC}"

# List what's in the ZIP
echo ""
echo -e "${BLUE}→ ZIP contents summary:${NC}"
unzip -l "${DIST_DIR}/${ZIP_NAME}" | head -20
echo "..."
echo ""

# Cleanup build directory (keep dist)
echo -e "${BLUE}→ Cleaning up...${NC}"
rm -rf "${BUILD_DIR}"

# Final summary
echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  Build Complete! ✓${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${YELLOW}Plugin ZIP:${NC} ${DIST_DIR}/${ZIP_NAME}"
echo -e "${YELLOW}Size:${NC} ${ZIP_SIZE}"
echo -e "${YELLOW}Version:${NC} ${PLUGIN_VERSION}"
echo ""
echo -e "${GREEN}→ Ready to upload to WordPress!${NC}"
echo ""
echo -e "${BLUE}Upload locations:${NC}"
echo "  • WordPress Admin → Plugins → Add New → Upload Plugin"
echo "  • /wp-content/plugins/ (extract zip manually)"
echo ""
