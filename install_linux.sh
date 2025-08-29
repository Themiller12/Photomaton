#!/bin/bash

# ============================================================================
# INSTALLATION AUTOMATIQUE PHOTOMATON LINUX
# Script d'installation complète pour Ubuntu/Debian
# ============================================================================

set -e  # Arrêter en cas d'erreur

# Configuration
INSTALL_DIR="/var/www/html/Photomaton"
USER_WEB="www-data"
PRINTER_NAME="Canon_SELPHY_CP1500"

echo "🚀 Installation du Photomaton Linux"
echo "====================================="

# Vérifier les privilèges root
if [[ $EUID -ne 0 ]]; then
   echo "❌ Ce script doit être exécuté en tant que root"
   echo "Utilisez: sudo $0"
   exit 1
fi

echo "📦 Mise à jour des paquets..."
apt update

echo "📦 Installation des dépendances..."
apt install -y \
    apache2 \
    php \
    php-json \
    php-cli \
    cups \
    cups-client \
    printer-driver-postscript \
    gphoto2 \
    libgphoto2-dev \
    imagemagick \
    git \
    curl \
    wget

echo "🔧 Configuration d'Apache..."
systemctl enable apache2
systemctl start apache2

# Activer les modules PHP nécessaires
a2enmod php8.* 2>/dev/null || a2enmod php7.* 2>/dev/null || echo "Module PHP déjà activé"

echo "🖨️ Configuration de CUPS..."
systemctl enable cups
systemctl start cups

# Ajouter l'utilisateur web au groupe d'impression
usermod -a -G lpadmin "$USER_WEB"

echo "📁 Configuration des permissions..."
if [[ -d "$INSTALL_DIR" ]]; then
    # Définir les permissions appropriées
    chown -R "$USER_WEB:$USER_WEB" "$INSTALL_DIR"
    find "$INSTALL_DIR" -type d -exec chmod 755 {} \;
    find "$INSTALL_DIR" -type f -exec chmod 644 {} \;
    
    # Rendre les scripts exécutables
    chmod +x "$INSTALL_DIR/linux_capture.sh" 2>/dev/null || true
    chmod +x "$INSTALL_DIR/linux_print.sh" 2>/dev/null || true
    
    # Créer les dossiers nécessaires
    mkdir -p "$INSTALL_DIR/captures"
    mkdir -p "$INSTALL_DIR/temp"
    chmod 755 "$INSTALL_DIR/captures"
    chmod 755 "$INSTALL_DIR/temp"
    chown "$USER_WEB:$USER_WEB" "$INSTALL_DIR/captures"
    chown "$USER_WEB:$USER_WEB" "$INSTALL_DIR/temp"
fi

echo "📷 Configuration gPhoto2..."
# Créer les règles udev pour gPhoto2
cat > /etc/udev/rules.d/90-libgphoto2.rules << 'EOF'
# Règles udev pour gPhoto2 - accès aux appareils photo
ACTION!="add", GOTO="libgphoto2_rules_end"
SUBSYSTEM!="usb", GOTO="libgphoto2_rules_end"

# Canon
ATTR{idVendor}=="04a9", MODE="0664", GROUP="plugdev"

# Nikon
ATTR{idVendor}=="04b0", MODE="0664", GROUP="plugdev"

# Sony
ATTR{idVendor}=="054c", MODE="0664", GROUP="plugdev"

LABEL="libgphoto2_rules_end"
EOF

# Ajouter l'utilisateur web au groupe plugdev
usermod -a -G plugdev "$USER_WEB"

# Recharger les règles udev
udevadm control --reload-rules
udevadm trigger

echo "🖨️ Détection automatique d'imprimantes..."
# Rechercher les imprimantes USB
USB_PRINTERS=$(lpinfo -v | grep usb | grep -i "canon\|selphy" || true)

if [[ -n "$USB_PRINTERS" ]]; then
    echo "🎯 Imprimante détectée:"
    echo "$USB_PRINTERS"
    
    # Extraire l'URI USB
    USB_URI=$(echo "$USB_PRINTERS" | head -n1 | awk '{print $2}')
    
    # Configurer l'imprimante avec le fichier PPD si disponible
    echo "⚙️ Configuration de l'imprimante $PRINTER_NAME..."
    if [[ -f "$INSTALL_DIR/Canon_SELPHY_CP1500.ppd" ]]; then
        echo "📄 Utilisation du fichier PPD pour configuration optimale"
        lpadmin -p "$PRINTER_NAME" -v "$USB_URI" -P "$INSTALL_DIR/Canon_SELPHY_CP1500.ppd" -E
    else
        echo "⚠️ Fichier PPD non trouvé, utilisation du driver par défaut"
        lpadmin -p "$PRINTER_NAME" -v "$USB_URI" -E -m everywhere 2>/dev/null || \
        lpadmin -p "$PRINTER_NAME" -v "$USB_URI" -E -m raw
    fi
    
    # Définir par défaut
    lpadmin -d "$PRINTER_NAME"
    
    echo "✅ Imprimante configurée: $PRINTER_NAME"
else
    echo "⚠️ Aucune imprimante Canon USB détectée"
    echo "   Branchez votre imprimante et relancez: sudo lpadmin -p $PRINTER_NAME -v \$(lpinfo -v | grep usb | head -n1 | awk '{print \$2}') -E"
fi

echo "🔧 Configuration finale..."
# Redémarrer les services
systemctl restart apache2
systemctl restart cups

# Test des composants
echo "🧪 Test des composants..."
echo -n "Apache: "
if systemctl is-active --quiet apache2; then echo "✅ OK"; else echo "❌ ERREUR"; fi

echo -n "CUPS: "
if systemctl is-active --quiet cups; then echo "✅ OK"; else echo "❌ ERREUR"; fi

echo -n "gPhoto2: "
if command -v gphoto2 &> /dev/null; then echo "✅ OK"; else echo "❌ ERREUR"; fi

echo -n "ImageMagick: "
if command -v identify &> /dev/null; then echo "✅ OK"; else echo "❌ ERREUR"; fi

echo -n "PHP: "
if command -v php &> /dev/null; then echo "✅ OK"; else echo "❌ ERREUR"; fi

echo ""
echo "🎉 Installation terminée!"
echo ""
echo "📋 Prochaines étapes:"
echo "  1. Connectez votre appareil photo Canon via USB"
echo "  2. Connectez votre imprimante Canon SELPHY CP1500 via USB"
echo "  3. Modifiez config.js pour activer le mode Linux:"
echo "     - operatingSystem: 'linux' (auto-détecté)"
echo "     - cameraMode: 'dslr_linux'"
echo "     - printerType: 'linux_cups'"
echo "  4. Accédez à: http://localhost/Photomaton"
echo ""
echo "🔧 Commandes utiles:"
echo "  - Test caméra: cd $INSTALL_DIR && sudo -u $USER_WEB ./linux_capture.sh test"
echo "  - Test impression: cd $INSTALL_DIR && sudo -u $USER_WEB ./linux_print.sh test"
echo "  - Interface CUPS: http://localhost:631"
echo "  - Logs Apache: tail -f /var/log/apache2/error.log"
echo ""

# Créer un script de test rapide
cat > "$INSTALL_DIR/test_linux.sh" << 'EOF'
#!/bin/bash
echo "🧪 Test du système Photomaton Linux"
echo "===================================="

echo "📷 Test appareil photo..."
./linux_capture.sh test

echo ""
echo "🖨️ Test impression..."
./linux_print.sh test

echo ""
echo "🌐 Test serveur web..."
curl -s http://localhost/Photomaton/ > /dev/null && echo "✅ Serveur web OK" || echo "❌ Serveur web ERREUR"

echo ""
echo "📋 Résumé:"
ps aux | grep -E "(apache2|cups)" | grep -v grep
EOF

chmod +x "$INSTALL_DIR/test_linux.sh" 2>/dev/null || true
chown "$USER_WEB:$USER_WEB" "$INSTALL_DIR/test_linux.sh" 2>/dev/null || true

echo "✅ Installation complète! Testez avec: cd $INSTALL_DIR && ./test_linux.sh"
