#!/bin/bash

# ============================================================================
# ANALYSEUR PPD - Canon SELPHY CP1500
# Extrait les informations du fichier PPD pour optimiser l'impression
# ============================================================================

PPD_FILE="/var/www/html/Photomaton/Canon_SELPHY_CP1500.ppd"

echo "📄 Analyse du fichier PPD Canon SELPHY CP1500"
echo "=============================================="

if [[ ! -f "$PPD_FILE" ]]; then
    echo "❌ Fichier PPD non trouvé: $PPD_FILE"
    exit 1
fi

echo "📋 Informations générales:"
grep -E "^\*ModelName|^\*Manufacturer|^\*ShortNickName|^\*NickName" "$PPD_FILE" | sed 's/^\*/  /'

echo ""
echo "🎨 Modes couleur supportés:"
grep -A5 "^\*OpenUI \*ColorModel" "$PPD_FILE" | grep "^\*ColorModel" | sed 's/^\*ColorModel /  /'

echo ""
echo "📐 Formats papier supportés:"
grep -E "^\*PageSize" "$PPD_FILE" | sed 's/^\*PageSize /  /' | cut -d: -f1

echo ""
echo "🖨️ Qualités d'impression:"
grep -A5 "^\*OpenUI \*cupsPrintQuality" "$PPD_FILE" | grep "^\*cupsPrintQuality" | sed 's/^\*cupsPrintQuality /  /'

echo ""
echo "⚙️ Format par défaut:"
grep "^\*DefaultPageSize" "$PPD_FILE" | sed 's/^\*DefaultPageSize: /  /'

echo ""
echo "📊 Résolution par défaut:"
grep "^\*DefaultResolution" "$PPD_FILE" | sed 's/^\*DefaultResolution: /  /'

echo ""
echo "🔢 Copies maximales:"
grep "^\*cupsMaxCopies" "$PPD_FILE" | sed 's/^\*cupsMaxCopies: /  /'

echo ""
echo "🎯 Commandes d'impression optimales:"
echo "  Format standard (10x15cm avec bordure):"
echo "    lp -d Canon_SELPHY_CP1500 -o PageSize=Postcard -o ColorModel=RGB photo.jpg"
echo ""
echo "  Format sans bordure (10x15cm pleine page):"
echo "    lp -d Canon_SELPHY_CP1500 -o PageSize=Postcard.Fullbleed -o ColorModel=RGB photo.jpg"
echo ""
echo "  Carte de crédit (54x86mm):"
echo "    lp -d Canon_SELPHY_CP1500 -o PageSize=54x86mm.Fullbleed -o ColorModel=RGB photo.jpg"
echo ""
echo "  Format L (89x119mm):"
echo "    lp -d Canon_SELPHY_CP1500 -o PageSize=89x119mm.Fullbleed -o ColorModel=RGB photo.jpg"

echo ""
echo "✅ Analyse terminée"
