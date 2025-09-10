#!/bin/bash
# Test rapide impression double
# Usage: ./test_print_double.sh

echo "🖨️  TEST IMPRESSION DOUBLE"
echo "========================"

# Vérifier si on est sur Linux
if [ "$(uname)" != "Linux" ]; then
    echo "❌ Ce test est pour Linux uniquement"
    exit 1
fi

# Aller dans le répertoire Photomaton
cd /var/www/html/Photomaton 2>/dev/null || cd /home/pi/Photomaton 2>/dev/null || {
    echo "❌ Répertoire Photomaton non trouvé"
    exit 1
}

echo "📁 Répertoire: $(pwd)"

# Vérifier qu'il y a des photos de test
if [ ! -d "captures" ] || [ -z "$(ls captures/*.jpg 2>/dev/null)" ]; then
    echo "❌ Aucune photo dans le dossier captures/"
    echo "💡 Créer une photo de test..."
    mkdir -p captures
    # Créer une image de test simple si ImageMagick est disponible
    if command -v convert &>/dev/null; then
        convert -size 800x600 xc:lightblue -gravity center -pointsize 48 -annotate 0 "TEST PHOTO" captures/test_photo.jpg
        echo "✅ Photo de test créée: captures/test_photo.jpg"
    else
        echo "❌ Pas de photos et ImageMagick non disponible pour créer une photo de test"
        exit 1
    fi
fi

# Prendre la première photo disponible
TEST_PHOTO=$(ls captures/*.jpg | head -1)
echo "📷 Photo de test: $(basename "$TEST_PHOTO")"

# Test 1: Vérifier que le script PHP existe et est accessible
echo ""
echo "🔍 Test 1: Accessibilité linux_print.php"
if [ -f "linux_print.php" ]; then
    echo "✅ linux_print.php existe"
else
    echo "❌ linux_print.php manquant"
    exit 1
fi

# Test 2: Test direct via cURL (impression double)
echo ""
echo "🔍 Test 2: Test impression double via cURL"
TEST_DATA='{
    "imagePath": "'$(basename "$TEST_PHOTO")'",
    "file": "captures/'$(basename "$TEST_PHOTO")'",
    "copies": 1,
    "layout": "2up",
    "doublePhoto": true,
    "media": "4x6"
}'

echo "📡 Données envoyées:"
echo "$TEST_DATA" | jq . 2>/dev/null || echo "$TEST_DATA"

echo ""
echo "📤 Envoi de la requête..."
RESPONSE=$(curl -s -X POST \
    -H "Content-Type: application/json" \
    -d "$TEST_DATA" \
    http://localhost/Photomaton/linux_print.php)

echo "📨 Réponse reçue:"
echo "$RESPONSE" | jq . 2>/dev/null || echo "$RESPONSE"

# Analyser la réponse
if echo "$RESPONSE" | grep -q '"success":true'; then
    echo "✅ Test d'impression double réussi !"
elif echo "$RESPONSE" | grep -q 'error'; then
    echo "❌ Erreur dans la réponse: $RESPONSE"
else
    echo "⚠️  Réponse inattendue: $RESPONSE"
fi

# Test 3: Vérifier les logs
echo ""
echo "🔍 Test 3: Logs d'impression"
if [ -f "logs/print_log.txt" ]; then
    echo "📋 Dernières entrées du log d'impression:"
    tail -10 logs/print_log.txt | sed 's/^/    /'
else
    echo "❌ Fichier de log d'impression non trouvé"
fi

echo ""
echo "✅ Test terminé"
echo "💡 Si des erreurs persistent, vérifiez les logs dans logs/print_log.txt"
