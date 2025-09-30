#!/bin/bash

# Script zum Entfernen aller Wunschliste-Elemente aus Templates

echo "🗑️  Entferne Wunschliste-Elemente aus allen Templates..."

# Alle Template-Dateien finden
TEMPLATE_FILES=$(find templates -name "*.twig" -type f)

for file in $TEMPLATE_FILES; do
    echo "🔍 Bearbeite: $file"
    
    # Entferne cta-wishlist Divs (4 Zeilen)
    sed -i '/cta-wishlist/,+3d' "$file"
    
    # Entferne wishlist-btn Links
    sed -i '/wishlist-btn/d' "$file"
    
    # Entferne wishlist action-item Divs
    sed -i '/class="wishlist action-item"/,+2d' "$file"
    
    # Entferne path('wishlist') Links
    sed -i '/path.*wishlist/d' "$file"
    
    # Entferne einzelne wishlist-dot Spans
    sed -i 's/wishlist-dot/cart-dot/g' "$file"
    
    # Entferne Heart-Icons die alleine stehen
    sed -i '/fa-heart/d' "$file"
    sed -i '/rt-heart/d' "$file"
    
    # Entferne wishlist-social Divs
    sed -i '/wishlist-social/,+10d' "$file"
    
done

echo "✅ Wunschliste-Elemente aus allen Templates entfernt!"
echo "📤 Änderungen werden automatisch hochgeladen..."