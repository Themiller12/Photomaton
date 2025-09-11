#!/bin/bash

# Script de diagnostic pour le mode kiosk - utilisateur volt
# Photomaton - Diagnostic Kiosk Mode

echo "=== Diagnostic du mode kiosk pour l'utilisateur volt ==="
echo ""

USER="volt"
HOME_DIR="/home/$USER"
DESKTOP_DIR="$HOME_DIR/.config/autostart"
SCRIPT_DIR="/home/$USER/Photomaton/scripts"
KIOSK_SCRIPT="$SCRIPT_DIR/start_kiosk.sh"

# 1. Vérifier l'utilisateur
echo "1. Vérification de l'utilisateur:"
echo "   Utilisateur actuel: $(whoami)"
echo "   Utilisateur cible: $USER"
if id "$USER" >/dev/null 2>&1; then
    echo "   ✓ L'utilisateur $USER existe"
else
    echo "   ✗ L'utilisateur $USER n'existe pas"
fi
echo ""

# 2. Vérifier les répertoires
echo "2. Vérification des répertoires:"
if [ -d "$HOME_DIR" ]; then
    echo "   ✓ Répertoire home: $HOME_DIR"
else
    echo "   ✗ Répertoire home manquant: $HOME_DIR"
fi

if [ -d "$DESKTOP_DIR" ]; then
    echo "   ✓ Répertoire autostart: $DESKTOP_DIR"
else
    echo "   ✗ Répertoire autostart manquant: $DESKTOP_DIR"
fi

if [ -d "$SCRIPT_DIR" ]; then
    echo "   ✓ Répertoire scripts: $SCRIPT_DIR"
else
    echo "   ✗ Répertoire scripts manquant: $SCRIPT_DIR"
fi
echo ""

# 3. Vérifier les fichiers
echo "3. Vérification des fichiers:"
if [ -f "$KIOSK_SCRIPT" ]; then
    echo "   ✓ Script kiosk: $KIOSK_SCRIPT"
    if [ -x "$KIOSK_SCRIPT" ]; then
        echo "   ✓ Script exécutable"
    else
        echo "   ✗ Script non exécutable"
    fi
else
    echo "   ✗ Script kiosk manquant: $KIOSK_SCRIPT"
fi

if [ -f "$DESKTOP_DIR/photomaton-kiosk.desktop" ]; then
    echo "   ✓ Fichier autostart: $DESKTOP_DIR/photomaton-kiosk.desktop"
    echo "   Contenu du fichier:"
    cat "$DESKTOP_DIR/photomaton-kiosk.desktop" | sed 's/^/      /'
else
    echo "   ✗ Fichier autostart manquant"
fi
echo ""

# 4. Vérifier les permissions
echo "4. Vérification des permissions:"
ls -la "$HOME_DIR" | grep -E "(\.config|autostart)" | sed 's/^/   /'
if [ -f "$DESKTOP_DIR/photomaton-kiosk.desktop" ]; then
    ls -la "$DESKTOP_DIR/photomaton-kiosk.desktop" | sed 's/^/   /'
fi
ls -la "$KIOSK_SCRIPT" 2>/dev/null | sed 's/^/   /'
echo ""

# 5. Vérifier crontab
echo "5. Vérification du crontab pour $USER:"
if sudo -u $USER crontab -l 2>/dev/null | grep -q "start_kiosk"; then
    echo "   ✓ Entrée crontab trouvée:"
    sudo -u $USER crontab -l 2>/dev/null | grep "start_kiosk" | sed 's/^/      /'
else
    echo "   ✗ Aucune entrée crontab"
fi
echo ""

# 6. Vérifier les services système
echo "6. Vérification des services:"
echo "   Apache2:"
if systemctl is-active --quiet apache2; then
    echo "      ✓ Apache2 actif"
else
    echo "      ✗ Apache2 inactif"
fi

echo "   X11:"
if pgrep -x "Xorg" > /dev/null; then
    echo "      ✓ X11 en cours d'exécution"
else
    echo "      ✗ X11 non démarré"
fi

echo "   Chromium:"
if pgrep -f "chromium.*kiosk" > /dev/null; then
    echo "      ✓ Chromium en mode kiosk actif"
else
    echo "      ✗ Chromium kiosk non actif"
fi
echo ""

# 7. Tester l'URL
echo "7. Test de l'URL locale:"
if curl -s http://localhost/Photomaton/ | grep -q "html"; then
    echo "   ✓ http://localhost/Photomaton/ accessible"
else
    echo "   ✗ http://localhost/Photomaton/ non accessible"
fi
echo ""

# 8. Environnement de bureau
echo "8. Environnement de bureau:"
echo "   DESKTOP_SESSION: ${DESKTOP_SESSION:-'non défini'}"
echo "   XDG_CURRENT_DESKTOP: ${XDG_CURRENT_DESKTOP:-'non défini'}"
echo "   DISPLAY: ${DISPLAY:-'non défini'}"
echo ""

echo "=== Fin du diagnostic ==="
echo ""
echo "Pour corriger les problèmes détectés:"
echo "1. Exécutez: ./install_kiosk_volt.sh"
echo "2. Ou utilisez: ./install_cron_kiosk_volt.sh pour crontab uniquement"
echo "3. Redémarrez: sudo reboot"
