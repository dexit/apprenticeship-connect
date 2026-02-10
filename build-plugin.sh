#!/bin/bash
#
# Robust Build Script for Apprenticeship Connect
#
# Creates a full production export in /build/ and a corresponding ZIP.
#

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  Apprenticeship Connect Production Build${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

PLUGIN_SLUG="apprenticeship-connect"
BUILD_DIR="build"
DIST_DIR="dist"
EXPORT_PATH="${BUILD_DIR}/${PLUGIN_SLUG}"

# 1. Clean up
echo -e "${BLUE}→ Cleaning previous builds...${NC}"
rm -rf "${BUILD_DIR}"
rm -rf "${DIST_DIR}"
mkdir -p "${EXPORT_PATH}"
mkdir -p "${DIST_DIR}"

# 2. Build Assets
echo -e "${BLUE}→ Building JavaScript and CSS assets...${NC}"
npm install
npm run build

if [ ! -d "assets/build" ]; then
    echo -e "${RED}✗ Asset build failed - assets/build directory missing${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Assets built successfully${NC}"

# 3. Production Dependencies
echo -e "${BLUE}→ Installing production PHP dependencies...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Copy Files
echo -e "${BLUE}→ Exporting files to ${EXPORT_PATH}...${NC}"

# Files and directories to include
INCLUDES=(
    "apprenticeship-connect.php"
    "readme.txt"
    "README.md"
    "uninstall.php"
    "LICENSE"
    "includes"
    "languages"
    "assets/build"
    "assets/images"
    "assets/css"
    "vendor"
)

for ITEM in "${INCLUDES[@]}"; do
    if [ -e "$ITEM" ]; then
        # Create parent directory if needed
        PARENT=$(dirname "${EXPORT_PATH}/${ITEM}")
        mkdir -p "${PARENT}"

        cp -R "$ITEM" "${EXPORT_PATH}/${ITEM}"
        echo -e "  Copied: ${ITEM}"
    else
        echo -e "${YELLOW}  ⚠ Warning: ${ITEM} not found, skipping${NC}"
    fi
done

# 5. Verify Build
echo -e "${BLUE}→ Verifying export...${NC}"
if [ -f "${EXPORT_PATH}/assets/build/admin.js" ]; then
    echo -e "${GREEN}✓ Production assets verified in build folder${NC}"
else
    echo -e "${RED}✗ Production assets missing from build folder!${NC}"
    exit 1
fi

# 6. Create ZIP
echo -e "${BLUE}→ Creating ZIP archive...${NC}"
ZIP_NAME="${PLUGIN_SLUG}-v$(grep "Version:" apprenticeship-connect.php | awk '{print $3}').zip"
cd "${BUILD_DIR}"
zip -r "../${DIST_DIR}/${ZIP_NAME}" "${PLUGIN_SLUG}" -q
cd ..

# Copy zip to build folder as well for convenience
cp "${DIST_DIR}/${ZIP_NAME}" "${BUILD_DIR}/"

echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  Build Complete!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}Full Export:${NC} ./${EXPORT_PATH}/"
echo -e "${YELLOW}ZIP Archive:${NC} ./${DIST_DIR}/${ZIP_NAME}"
echo -e "${YELLOW}ZIP Copy:   ${NC} ./${BUILD_DIR}/${ZIP_NAME}"
echo ""
echo -e "The ${BLUE}/build/${NC} directory has been kept for your inspection."
