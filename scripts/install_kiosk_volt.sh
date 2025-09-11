#!/bin/bash

# Script d'installation du mode kiosk pour l'utilisateur volt
# Photomaton - Mode Kiosk Auto-Start Setup

echo "=== Installation du mode kiosk pour l'utilisateur volt ==="

# Variables
USER="volt"
HOME_DIR="/home/$USER"
DESKTOP_DIR="$HOME_DIR/.config/autostart"
SCRIPT_DIR="/home/$USER/Photomaton/scripts"
KIOSK_SCRIPT="$SCRIPT_DIR/start_kiosk.sh"

# Vérifier que nous sommes sur Raspberry Pi
if ! grep -q "Raspberry Pi" /proc/cpuinfo 2>/dev/null; then
    echo "Attention: Ce script est conçu pour Raspberry Pi"
fi

# Créer le répertoire autostart s'il n'existe pas
echo "Création du répertoire autostart pour $USER..."
sudo -u $USER mkdir -p "$DESKTOP_DIR"

# Vérifier que le script start_kiosk.sh existe
if [ ! -f "$KIOSK_SCRIPT" ]; then
    echo "Erreur: Le script $KIOSK_SCRIPT n'existe pas"
    echo "Veuillez d'abord copier les scripts dans $SCRIPT_DIR"
    exit 1
fi

# Rendre le script start_kiosk.sh exécutable
chmod +x "$KIOSK_SCRIPT"

# Créer le fichier .desktop pour autostart
echo "Création du fichier autostart..."
sudo -u $USER tee "$DESKTOP_DIR/photomaton-kiosk.desktop" > /dev/null << EOF
[Desktop Entry]
Type=Application
Name=Photomaton Kiosk
Comment=Lance Photomaton en mode kiosk
Exec=$KIOSK_SCRIPT
Hidden=false
NoDisplay=false
X-GNOME-Autostart-enabled=true
StartupNotify=false
EOF

# Rendre le fichier .desktop exécutable
chmod +x "$DESKTOP_DIR/photomaton-kiosk.desktop"

# Vérifier les permissions
echo "Vérification des permissions..."
ls -la "$DESKTOP_DIR/photomaton-kiosk.desktop"
ls -la "$KIOSK_SCRIPT"

# Créer aussi une entrée crontab de sauvegarde
echo "Ajout d'une entrée crontab de sauvegarde..."
(sudo -u $USER crontab -l 2>/dev/null; echo "@reboot sleep 30 && $KIOSK_SCRIPT") | sudo -u $USER crontab -

echo ""
echo "=== Installation terminée ==="
echo "Le mode kiosk démarrera automatiquement après redémarrage."
echo "Utilisateur configuré: $USER"
echo "Fichier autostart: $DESKTOP_DIR/photomaton-kiosk.desktop"
echo "Script kiosk: $KIOSK_SCRIPT"
echo ""
echo "Pour tester immédiatement: sudo -u $USER $KIOSK_SCRIPT"
echo "Pour redémarrer: sudo reboot"
