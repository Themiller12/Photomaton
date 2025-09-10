#!/bin/bash
# Script de correction rapide pour les problèmes de scripts Linux
# Usage: sudo ./fix_scripts.sh

echo "🔧 CORRECTION DES SCRIPTS LINUX"
echo "==============================="

# Se placer dans le bon répertoire
if [ -d "/var/www/html/Photomaton" ]; then
    cd /var/www/html/Photomaton
    echo "📁 Répertoire: $(pwd)"
else
    echo "❌ Répertoire Photomaton non trouvé"
    exit 1
fi

# Vérifier et créer le dossier scripts si nécessaire
if [ ! -d "scripts" ]; then
    echo "📁 Création du dossier scripts..."
    mkdir -p scripts
fi

# Copier les scripts depuis le répertoire source si nécessaire
source_scripts="/home/pi/Photomaton/scripts"
if [ -d "$source_scripts" ] && [ ! -f "scripts/linux_capture.sh" ]; then
    echo "📋 Copie des scripts depuis $source_scripts..."
    cp -r "$source_scripts"/* scripts/
fi

# Rendre tous les scripts exécutables
echo "🔒 Configuration des permissions..."
find scripts -name "*.sh" -exec chmod +x {} \;
chown -R www-data:www-data scripts
chmod -R 755 scripts

# Vérifier chaque script critique
for script in linux_capture.sh linux_print.sh; do
    script_path="scripts/$script"
    if [ -f "$script_path" ]; then
        echo "✅ $script: OK ($(ls -l "$script_path" | cut -d' ' -f1))"
    else
        echo "❌ $script: MANQUANT"
    fi
done

# Vérifier et créer le dossier logs
if [ ! -d "logs" ]; then
    echo "📝 Création du dossier logs..."
    mkdir -p logs
    chown www-data:www-data logs
    chmod 777 logs
fi

# Vérifier gPhoto2
if ! command -v gphoto2 &> /dev/null; then
    echo "📷 Installation de gPhoto2..."
    apt update
    apt install -y gphoto2 libgphoto2-dev
fi

# Test de fonctionnement
echo ""
echo "🧪 Test de fonctionnement:"
if [ -x "scripts/linux_capture.sh" ]; then
    echo "📜 Test du script de capture..."
    if ./scripts/linux_capture.sh test 2>/dev/null; then
        echo "✅ Script de capture: OK"
    else
        echo "⚠️ Script de capture: Problème de configuration"
    fi
else
    echo "❌ Script de capture non exécutable"
fi

# Redémarrer Apache pour appliquer les changements
echo ""
echo "🔄 Redémarrage d'Apache..."
systemctl restart apache2

echo ""
echo "✅ Correction terminée !"
echo "🌐 Testez maintenant: http://$(hostname -I | awk '{print $1}')/Photomaton/"
