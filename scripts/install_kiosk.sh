#!/bin/bash

# ============================================================================
# PHOTOMATON RASPBERRY PI - Installation du mode kiosque
# Configure le démarrage automatique du photomaton
# ============================================================================

set -e  # Arrêter en cas d'erreur

# Configuration
SCRIPT_DIR="/var/www/html/Photomaton/scripts"
KIOSK_SCRIPT="$SCRIPT_DIR/start_kiosk.sh"
AUTOSTART_DIR="/home/pi/.config/autostart"
DESKTOP_FILE="$AUTOSTART_DIR/photomaton-kiosk.desktop"

echo "Installation du mode kiosque Photomaton..."

# Vérifier les permissions
if [ "$EUID" -eq 0 ]; then
    echo "ERREUR: Ne pas exécuter ce script en tant que root"
    echo "        Utilisez: ./install_kiosk.sh"
    exit 1
fi

# Créer le dossier autostart s'il n'existe pas
echo "Creation du dossier autostart..."
mkdir -p "$AUTOSTART_DIR"

# Rendre le script kiosque exécutable
echo "Configuration des permissions..."
chmod +x "$KIOSK_SCRIPT"

# Créer le fichier .desktop pour l'autostart
echo "Creation du fichier autostart..."
cat > "$DESKTOP_FILE" << 'EOF'
[Desktop Entry]
Type=Application
Name=Photomaton Kiosk
Comment=Démarre le photomaton en mode kiosque
Exec=/var/www/html/Photomaton/scripts/start_kiosk.sh
Icon=chromium-browser
Terminal=false
NoDisplay=false
Hidden=false
X-GNOME-Autostart-enabled=true
X-GNOME-Autostart-Delay=10
EOF

# Installer les dépendances nécessaires
echo "Installation des dependances..."
sudo apt update
sudo apt install -y chromium-browser unclutter

# Configurer l'auto-login (optionnel)
echo "Voulez-vous activer la connexion automatique ? (y/n): "
read REPLY
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Configuration de l'auto-login..."
    sudo raspi-config nonint do_boot_behaviour B4  # Desktop auto-login
fi

# Désactiver l'économiseur d'écran par défaut
echo "Configuration de l'affichage..."
cat >> ~/.bashrc << 'EOF'

# Configuration Photomaton Kiosk
export DISPLAY=:0
xset s off 2>/dev/null || true
xset -dpms 2>/dev/null || true
xset s noblank 2>/dev/null || true
EOF

# Créer un script de test
echo "Creation du script de test..."
cat > "$SCRIPT_DIR/test_kiosk.sh" << 'EOF'
#!/bin/bash
echo "Test du mode kiosque..."
echo "URL testee: http://localhost/Photomaton/"

if curl -f -s "http://localhost/Photomaton/" >/dev/null; then
    echo "OK: Photomaton accessible"
    echo "Lancement du test kiosque..."
    /var/www/html/Photomaton/scripts/start_kiosk.sh &
    echo "Consultez les logs: tail -f /var/log/photomaton_kiosk.log"
else
    echo "ERREUR: Photomaton non accessible"
    echo "        Verifiez qu'Apache fonctionne: sudo systemctl status apache2"
fi
EOF

chmod +x "$SCRIPT_DIR/test_kiosk.sh"

# Résumé de l'installation
echo ""
echo "Installation terminee !"
echo ""
echo "Resume:"
echo "  * Script kiosque: $KIOSK_SCRIPT"
echo "  * Autostart: $DESKTOP_FILE"
echo "  * Logs: /var/log/photomaton_kiosk.log"
echo "  * Test: $SCRIPT_DIR/test_kiosk.sh"
echo ""
echo "Commandes utiles:"
echo "  * Tester maintenant: $SCRIPT_DIR/test_kiosk.sh"
echo "  * Voir les logs: tail -f /var/log/photomaton_kiosk.log"
echo "  * Arreter kiosque: pkill chromium-browser"
echo "  * Redemarrer Pi: sudo reboot"
echo ""
echo "Redemarrez le Raspberry Pi pour activer le mode kiosque automatique"
