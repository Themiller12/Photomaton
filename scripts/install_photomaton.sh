#!/bin/bash
# Installation automatique Photomaton sur Debian/Raspberry Pi
# Usage: sudo ./install_photomaton.sh

set -e

# Couleurs pour affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Variables
PROJECT_NAME="Photomaton"
GITHUB_URL="https://github.com/Themiller12/Photomaton.git"
WEB_ROOT="/var/www/html"
PROJECT_DIR="$WEB_ROOT/$PROJECT_NAME"
APACHE_USER="www-data"
CUPS_CONFIG="/etc/cups"
PPD_FILE="ppd/Canon_SELPHY_CP1500.ppd"

log() {
    echo -e "${GREEN}[$(date '+%H:%M:%S')]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

# Vérifier que le script est exécuté en root
if [ "$EUID" -ne 0 ]; then
    error "Ce script doit être exécuté avec sudo"
fi

# Récupérer l'utilisateur qui a lancé sudo
REAL_USER=${SUDO_USER:-$(logname)}
REAL_HOME=$(eval echo ~$REAL_USER)

log "Début de l'installation Photomaton pour l'utilisateur: $REAL_USER"

# 1. Mise à jour du système
log "Mise à jour du système..."
apt update && apt upgrade -y

# 2. Installation des paquets de base
log "Installation des paquets de base..."
apt install -y \
    apache2 \
    php \
    php-gd \
    php-curl \
    php-json \
    php-mbstring \
    git \
    curl \
    wget \
    unzip \
    build-essential \
    autoconf \
    automake \
    libtool \
    pkg-config \
    gettext \
    autopoint \
    intltool \
    libusb-1.0-0-dev \
    libpopt-dev \
    libexif-dev \
    libltdl-dev \
    usbutils

# 3. Installation de CUPS
log "Installation et configuration de CUPS..."

# Installer les paquets CUPS de base (obligatoires)
apt install -y cups cups-client

# Installer les paquets additionnels (optionnels)
for pkg in cups-filters ghostscript printer-driver-all hplip; do
    if apt install -y "$pkg" 2>/dev/null; then
        log "✓ $pkg installé"
    else
        warn "⚠ $pkg non disponible (ignoré)"
    fi
done

# Démarrer et activer CUPS
systemctl enable cups
systemctl start cups

# Ajouter www-data au groupe lp pour l'impression
usermod -a -G lp $APACHE_USER
usermod -a -G lp $REAL_USER

# 4. Installation de gPhoto2 (dernière version)
log "Installation de gPhoto2 dernière version..."

# Fonction de fallback pour installer depuis les dépôts
install_gphoto2_from_repos() {
    warn "Installation depuis les dépôts comme alternative..."
    apt install -y gphoto2 libgphoto2-dev
    if command -v gphoto2 >/dev/null; then
        log "gPhoto2 installé depuis les dépôts avec succès"
        return 0
    else
        return 1
    fi
}

# Supprimer l'ancienne version si présente
apt remove -y gphoto2 libgphoto2-dev || true

# Vérifier les dépendances critiques
log "Vérification des dépendances pour gPhoto2..."
missing_deps=()

for dep in libpopt-dev libusb-1.0-0-dev libexif-dev; do
    if ! dpkg -l | grep -q "^ii.*$dep"; then
        missing_deps+=("$dep")
    fi
done

if [ ${#missing_deps[@]} -gt 0 ]; then
    warn "Dépendances manquantes détectées, installation..."
    apt install -y "${missing_deps[@]}" || warn "Certaines dépendances n'ont pas pu être installées"
fi

# Vérifier que pkg-config peut trouver les bibliothèques critiques
log "Vérification de pkg-config..."
if ! pkg-config --exists libusb-1.0; then
    warn "libusb-1.0 non détectable par pkg-config"
fi

if ! pkg-config --atleast-version=1.6.1 popt 2>/dev/null; then
    warn "popt >= 1.6.1 non détectable par pkg-config, tentative d'installation alternative..."
    apt install -y libpopt0 popt-devel 2>/dev/null || true
fi

# Créer répertoire de build temporaire
BUILD_DIR="/tmp/gphoto2_build"
rm -rf $BUILD_DIR
mkdir -p $BUILD_DIR
cd $BUILD_DIR

# Télécharger et compiler libgphoto2
log "Compilation de libgphoto2..."
if ! git clone https://github.com/gphoto/libgphoto2.git; then
    error "Échec du téléchargement de libgphoto2"
fi

cd libgphoto2
if ! autoreconf -is; then
    error "Échec d'autoreconf pour libgphoto2. Vérifiez que tous les outils de build sont installés."
fi

if ! ./configure --prefix=/usr; then
    error "Échec de configuration de libgphoto2"
fi

if ! make -j$(nproc); then
    warn "Compilation parallèle échouée, tentative séquentielle..."
    make clean
    if ! make; then
        error "Échec de compilation de libgphoto2"
    fi
fi

make install
ldconfig

# Télécharger et compiler gphoto2
cd $BUILD_DIR
log "Compilation de gphoto2..."
if ! git clone https://github.com/gphoto/gphoto2.git; then
    error "Échec du téléchargement de gphoto2"
fi

cd gphoto2
if ! autoreconf -is; then
    error "Échec d'autoreconf pour gphoto2"
fi

if ! ./configure --prefix=/usr; then
    error "Échec de configuration de gphoto2"
fi

if ! make -j$(nproc); then
    warn "Compilation parallèle échouée, tentative séquentielle..."
    make clean
    if ! make; then
        error "Échec de compilation de gphoto2"
    fi
fi

make install

# Vérifier l'installation
if ! gphoto2 --version; then
    warn "Compilation de gPhoto2 échouée, tentative d'installation depuis les dépôts..."
    cd /
    rm -rf $BUILD_DIR
    if ! install_gphoto2_from_repos; then
        error "Impossible d'installer gPhoto2"
    fi
else
    log "✅ gPhoto2 compilé avec succès"
fi

# 5. Configuration des règles udev pour gphoto2
log "Configuration des règles udev..."
groupadd plugdev 2>/dev/null || true
usermod -a -G plugdev $REAL_USER
usermod -a -G plugdev $APACHE_USER

cat > /etc/udev/rules.d/40-gphoto.rules << 'EOF'
# Règles udev pour gphoto2
SUBSYSTEM=="usb", ENV{ID_GPHOTO2}=="?*", MODE="0664", GROUP="plugdev"

# Canon EOS spécifique
SUBSYSTEM=="usb", ATTR{idVendor}=="04a9", ATTR{idProduct}=="*", MODE="0664", GROUP="plugdev"
EOF

udevadm control --reload-rules
udevadm trigger

# 6. Activer Apache et PHP
log "Configuration d'Apache..."
systemctl enable apache2
systemctl start apache2

# Activer les modules Apache nécessaires
a2enmod rewrite
a2enmod headers

# Activer les extensions PHP nécessaires
log "Activation des extensions PHP..."
phpenmod gd
phpenmod curl
phpenmod json
phpenmod mbstring

# Vérifier que les extensions sont bien chargées
php -m | grep -E "(gd|curl|json)" > /dev/null || warn "Certaines extensions PHP peuvent ne pas être chargées"

# Redémarrer Apache pour prendre en compte les changements
systemctl restart apache2

# 7. Cloner le projet depuis GitHub
log "Récupération du projet depuis GitHub..."

# Supprimer l'ancien projet s'il existe
if [ -d "$PROJECT_DIR" ]; then
    warn "Projet existant détecté, sauvegarde en cours..."
    mv "$PROJECT_DIR" "${PROJECT_DIR}.backup.$(date +%Y%m%d_%H%M%S)"
fi

# Cloner le projet
cd $WEB_ROOT
git clone $GITHUB_URL $PROJECT_NAME

# 8. Configuration des permissions
log "Configuration des permissions..."

# Créer les dossiers nécessaires s'ils n'existent pas
mkdir -p "$PROJECT_DIR/captures"
mkdir -p "$PROJECT_DIR/logs" 
mkdir -p "$PROJECT_DIR/ppd"
mkdir -p "$PROJECT_DIR/scripts"

# Propriétaire : utilisateur réel, Groupe : www-data
chown -R $REAL_USER:$APACHE_USER "$PROJECT_DIR"

# Permissions : 775 pour les dossiers, 664 pour les fichiers
find "$PROJECT_DIR" -type d -exec chmod 775 {} \;
find "$PROJECT_DIR" -type f -exec chmod 664 {} \;

# Scripts exécutables
find "$PROJECT_DIR" -name "*.sh" -exec chmod +x {} \;

# Vérification spécifique des scripts critiques
log "Vérification des permissions des scripts Linux..."
for script in "$PROJECT_DIR/scripts/linux_capture.sh" "$PROJECT_DIR/scripts/linux_print.sh"; do
    if [ -f "$script" ]; then
        chmod +x "$script"
        if [ -x "$script" ]; then
            log "✅ $script est exécutable"
        else
            warn "⚠️ $script n'est pas exécutable"
        fi
    else
        warn "⚠️ $script non trouvé"
    fi
done

# Dossier captures avec permissions complètes
mkdir -p "$PROJECT_DIR/captures"
chmod 777 "$PROJECT_DIR/captures"

# 9. Configuration du fichier PPD
log "Configuration de l'imprimante Canon SELPHY CP1500..."

# Copier le fichier PPD s'il existe dans le projet
if [ -f "$PROJECT_DIR/$PPD_FILE" ]; then
    cp "$PROJECT_DIR/$PPD_FILE" /usr/share/cups/model/
    log "Fichier PPD copié vers CUPS"
else
    warn "Fichier PPD non trouvé dans le projet"
fi

# 10. Désactiver les services qui peuvent interférer avec gphoto2
log "Désactivation des services interférents..."
systemctl disable gvfs-gphoto2-volume-monitor 2>/dev/null || true
systemctl stop gvfs-gphoto2-volume-monitor 2>/dev/null || true

# Créer un script pour tuer les processus gvfs au démarrage
cat > /usr/local/bin/kill-gvfs-gphoto2 << 'EOF'
#!/bin/bash
pkill -f gvfsd-gphoto2 2>/dev/null || true
pkill -f gvfs-gphoto2-volume-monitor 2>/dev/null || true
EOF
chmod +x /usr/local/bin/kill-gvfs-gphoto2

# 11. Redémarrer les services
log "Redémarrage des services..."
systemctl restart apache2
systemctl restart cups

# 12. Créer un script de test
log "Création du script de test..."
cat > "$PROJECT_DIR/test_installation.sh" << 'EOF'
#!/bin/bash
echo "=== Test de l'installation Photomaton ==="

# Test Apache
if curl -s http://localhost/Photomaton/ > /dev/null; then
    echo "✅ Apache et projet accessibles"
else
    echo "❌ Problème d'accès au projet"
fi

# Test gphoto2
if command -v gphoto2 >/dev/null; then
    echo "✅ gphoto2 installé ($(gphoto2 --version | head -n1))"
    if gphoto2 --auto-detect | grep -q "Camera"; then
        echo "✅ Caméra détectée"
    else
        echo "⚠️  Aucune caméra détectée (normal si pas branchée)"
    fi
else
    echo "❌ gphoto2 non installé"
fi

# Test CUPS
if systemctl is-active cups >/dev/null; then
    echo "✅ CUPS actif"
    lpstat -p 2>/dev/null | head -n5
else
    echo "❌ CUPS non actif"
fi

# Test permissions
if [ -w "/var/www/html/Photomaton/captures" ]; then
    echo "✅ Permissions captures OK"
else
    echo "❌ Problème permissions captures"
fi

# Test extensions PHP
echo ""
echo "📋 Extensions PHP :"
for ext in gd curl json mbstring; do
    if php -m | grep -q "^$ext$"; then
        echo "  ✅ $ext"
    else
        echo "  ❌ $ext manquante"
    fi
done

echo ""
echo "🌐 Accès web : http://$(hostname -I | awk '{print $1}')/Photomaton/"
echo "📁 Répertoire projet : /var/www/html/Photomaton/"
echo ""
echo "Pour configurer l'imprimante :"
echo "  sudo lpadmin -p Canon_SELPHY_CP1500 -v usb://Canon/CP1500 -P /usr/share/cups/model/Canon_SELPHY_CP1500.ppd -E"
EOF

chmod +x "$PROJECT_DIR/test_installation.sh"

# 13. Créer un script de mise à jour
cat > "$PROJECT_DIR/update.sh" << 'EOF'
#!/bin/bash
echo "🔄 Mise à jour du projet Photomaton..."
cd /var/www/html/Photomaton

# Sauvegarder les modifications locales si elles existent
if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "⚠️  Modifications locales détectées, sauvegarde en cours..."
    
    # Créer une sauvegarde avec timestamp
    backup_branch="local-backup-$(date +%Y%m%d_%H%M%S)"
    git add -A
    git commit -m "Sauvegarde automatique avant mise à jour - $backup_branch" || true
    git branch "$backup_branch" || true
    echo "✅ Sauvegarde créée dans la branche: $backup_branch"
fi

# Vérifier le statut du dépôt
if git status --porcelain | grep -q '^??'; then
    echo "📁 Fichiers non trackés détectés, ajout automatique..."
    git add -A
    git commit -m "Auto-commit des nouveaux fichiers avant mise à jour" || true
fi

# Tenter la mise à jour
echo "📥 Téléchargement des dernières modifications..."
if git pull origin main; then
    echo "✅ Mise à jour Git réussie"
else
    echo "❌ Échec du git pull, tentative de résolution..."
    
    # Reset dur en sauvegardant d'abord
    echo "🔄 Reset vers la version distante..."
    git fetch origin
    git reset --hard origin/main
    echo "✅ Réinitialisation forcée effectuée"
fi

# Restaurer les permissions
echo "🔧 Restauration des permissions..."
sudo chown -R $USER:www-data .
sudo chmod -R 775 .
sudo chmod 777 captures logs
sudo find . -name "*.sh" -exec chmod +x {} \;

# Vérifier les scripts critiques
for script in scripts/linux_capture.sh scripts/linux_print.sh; do
    if [ -f "$script" ]; then
        chmod +x "$script"
        echo "✅ $script rendu exécutable"
    fi
done

echo ""
echo "🎉 Mise à jour terminée !"
echo "📋 Si vous aviez des modifications locales, elles sont sauvegardées dans une branche."
echo "   Pour les voir : git branch | grep local-backup"
echo "   Pour restaurer : git checkout nom-de-la-branche-backup"
EOF

chmod +x "$PROJECT_DIR/update.sh"
chown $REAL_USER:$APACHE_USER "$PROJECT_DIR/update.sh"

# 14. Créer un fichier de configuration automatique
cat > "$PROJECT_DIR/auto_config.js" << 'EOF'
// Configuration automatique pour Raspberry Pi
if (typeof window !== 'undefined' && window.PHOTOMATON_CONFIG) {
    // Auto-détection OS Linux
    window.PHOTOMATON_CONFIG.operatingSystem = 'linux';
    window.PHOTOMATON_CONFIG.cameraMode = 'dslr_linux';
    window.PHOTOMATON_CONFIG.printerType = 'linux_cups';
    
    console.log('🐧 Configuration Linux automatique appliquée');
}
EOF

# 15. Nettoyage
log "Nettoyage..."
rm -rf $BUILD_DIR

# 16. Messages finaux
log "Installation terminée avec succès !"
echo ""
echo -e "${GREEN}🎉 INSTALLATION RÉUSSIE ! 🎉${NC}"
echo ""
echo -e "${BLUE}Accès web :${NC} http://$(hostname -I | awk '{print $1}')/Photomaton/"
echo -e "${BLUE}Répertoire :${NC} $PROJECT_DIR"
echo ""
echo -e "${YELLOW}Prochaines étapes :${NC}"
echo "1. Brancher votre Canon EOS 700D en USB"
echo "2. Brancher votre Canon SELPHY CP1500 en USB"
echo "3. Configurer l'imprimante :"
echo "   sudo lpadmin -p Canon_SELPHY_CP1500 -v usb://Canon/CP1500 -P /usr/share/cups/model/Canon_SELPHY_CP1500.ppd -E"
echo "4. Tester l'installation :"
echo "   cd $PROJECT_DIR && ./test_installation.sh"
echo ""
echo -e "${GREEN}Redémarrage recommandé pour finaliser l'installation${NC}"
echo ""
read -p "Voulez-vous redémarrer maintenant ? (y/n) : " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    log "Redémarrage en cours..."
    reboot
fi