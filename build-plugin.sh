#!/bin/bash

# Apprenticeship Connect Build Script
# Ensures a clean production-ready build

set -e

echo "Starting build process for Apprenticeship Connect..."

# 1. Install dependencies
echo "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

echo "Installing NPM dependencies..."
npm install

# 2. Build frontend assets
echo "Building assets..."
npm run build

# 3. Create distribution directory
echo "Creating dist directory..."
rm -rf dist
mkdir -p dist/apprenticeship-connect

# 4. Copy files to dist
echo "Copying files to dist..."
RSYNC_OPTS="-av --progress --exclude-from=.distignore"
rsync $RSYNC_OPTS ./ dist/apprenticeship-connect/

# 5. Create ZIP
echo "Creating plugin ZIP..."
cd dist
zip -r apprenticeship-connect.zip apprenticeship-connect/
cd ..

echo "Build complete! ZIP file located at dist/apprenticeship-connect.zip"
