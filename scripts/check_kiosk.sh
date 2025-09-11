#!/bin/bash

# Test simple du mode kiosque
echo "Test du mode kiosque Photomaton..."
echo "=================================="

# Vérifier que les scripts existent
if [ ! -f "/var/www/html/Photomaton/scripts/start_kiosk.sh" ]; then
    echo "ERREUR: Script start_kiosk.sh non trouve"
    exit 1
fi

# Vérifier Apache
echo "Verification d'Apache..."
if systemctl is-active --quiet apache2; then
    echo "OK: Apache fonctionne"
else
    echo "ERREUR: Apache non demarre"
    echo "Demarrez avec: sudo systemctl start apache2"
    exit 1
fi

# Vérifier l'URL
echo "Test de l'URL Photomaton..."
if curl -f -s "http://localhost/Photomaton/" > /dev/null; then
    echo "OK: Photomaton accessible"
else
    echo "ERREUR: Photomaton non accessible"
    exit 1
fi

# Vérifier X11 (si en mode graphique)
if [ -n "$DISPLAY" ]; then
    echo "Verification de X11..."
    if xset q > /dev/null 2>&1; then
        echo "OK: X11 disponible"
    else
        echo "ATTENTION: X11 non disponible"
    fi
fi

echo ""
echo "Tous les tests sont passes !"
echo "Vous pouvez maintenant:"
echo "  1. Installer: ./install_kiosk.sh"
echo "  2. Tester: ./test_kiosk.sh"
echo "  3. Demarrer: ./start_kiosk.sh"
