#!/bin/bash

# Script de diagnostic du mode kiosque
echo "Diagnostic du mode kiosque Photomaton"
echo "====================================="

# 1. Vérifier le fichier autostart
AUTOSTART_FILE="/home/pi/.config/autostart/photomaton-kiosk.desktop"
echo "1. Verification du fichier autostart:"
if [ -f "$AUTOSTART_FILE" ]; then
    echo "   OK: Fichier autostart existe"
    echo "   Contenu:"
    cat "$AUTOSTART_FILE" | sed 's/^/   /'
else
    echo "   ERREUR: Fichier autostart manquant"
    echo "   Chemin: $AUTOSTART_FILE"
fi

echo ""

# 2. Vérifier le script principal
KIOSK_SCRIPT="/var/www/html/Photomaton/scripts/start_kiosk.sh"
echo "2. Verification du script principal:"
if [ -f "$KIOSK_SCRIPT" ]; then
    echo "   OK: Script existe"
    if [ -x "$KIOSK_SCRIPT" ]; then
        echo "   OK: Script executable"
    else
        echo "   ERREUR: Script non executable"
        echo "   Correction: chmod +x $KIOSK_SCRIPT"
    fi
else
    echo "   ERREUR: Script manquant"
    echo "   Chemin: $KIOSK_SCRIPT"
fi

echo ""

# 3. Vérifier l'environnement de bureau
echo "3. Verification de l'environnement:"
echo "   Session actuelle: $XDG_CURRENT_DESKTOP"
echo "   Utilisateur: $(whoami)"
echo "   Groupe: $(groups)"

# 4. Vérifier les logs
LOG_FILE="/var/log/photomaton_kiosk.log"
echo ""
echo "4. Verification des logs:"
if [ -f "$LOG_FILE" ]; then
    echo "   Dernieres lignes du log:"
    tail -10 "$LOG_FILE" | sed 's/^/   /'
else
    echo "   Aucun log trouve (normal si jamais lance)"
fi

echo ""

# 5. Test manuel
echo "5. Test manuel du script:"
echo "   Commande: $KIOSK_SCRIPT"
echo "   Voulez-vous tester maintenant ? (y/n)"
read -r REPLY
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "   Lancement du test..."
    "$KIOSK_SCRIPT" &
    sleep 5
    echo "   Script lance en arriere-plan"
    echo "   PID Chromium: $(pgrep chromium-browser || echo 'Aucun')"
fi

echo ""
echo "6. Solutions possibles:"
echo "   - Recreer autostart: ./scripts/install_kiosk.sh"
echo "   - Tester manuellement: $KIOSK_SCRIPT"
echo "   - Voir logs: tail -f $LOG_FILE"
echo "   - Redemarrer: sudo reboot"
