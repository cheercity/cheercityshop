#!/bin/bash

# Script zum Entfernen aller Wunschliste-CSS-Regeln aus SASS-Dateien

echo "🗑️  Entferne Wunschliste-CSS aus SASS-Dateien..."

# Entferne wishlist.scss Import aus main.scss
sed -i "/layout\/wishlist/d" public/assets/sass/main.scss

# Lösche wishlist.scss komplett
rm -f public/assets/sass/layout/wishlist.scss

# Entferne wishlist-btn Regeln aus _base.scss
sed -i '/\.wishlist-btn/,+10d' public/assets/sass/base/_base.scss

# Entferne cta-wishlist Regeln aus verschiedenen SCSS-Dateien
find public/assets/sass -name "*.scss" -exec sed -i '/cta-wishlist/,+10d' {} \;
find public/assets/sass -name "*.scss" -exec sed -i '/&\.cta-wishlist/,+5d' {} \;
find public/assets/sass -name "*.scss" -exec sed -i '/wishlist-btn/,+8d' {} \;
find public/assets/sass -name "*.scss" -exec sed -i '/\.wishlist/,+8d' {} \;
find public/assets/sass -name "*.scss" -exec sed -i '/wishlist-social/,+10d' {} \;

echo "✅ Wunschliste-CSS aus SASS-Dateien entfernt!"
echo "🔄 Kompiliere SASS neu..."