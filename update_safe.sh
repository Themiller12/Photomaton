#!/bin/bash
# Script de mise à jour sécurisé pour Photomaton
# Usage: sudo ./update_safe.sh

echo "🔄 Mise à jour sécurisée du projet Photomaton..."

# Se placer dans le bon répertoire
if [ -d "/var/www/html/Photomaton" ]; then
    cd /var/www/html/Photomaton
else
    echo "❌ Répertoire Photomaton non trouvé"
    exit 1
fi

# Sauvegarder les modifications locales
echo "💾 Sauvegarde des modifications locales..."
backup_dir="/tmp/photomaton_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$backup_dir"

# Copier les fichiers modifiés
if git status --porcelain | grep -q '^'; then
    echo "⚠️  Modifications détectées, sauvegarde en cours..."
    git status --porcelain | while read status file; do
        if [ -f "$file" ]; then
            cp "$file" "$backup_dir/" 2>/dev/null || true
        fi
    done
    echo "✅ Modifications sauvegardées dans: $backup_dir"
fi

# Reset forcé vers la version distante
echo "🔄 Réinitialisation vers la version GitHub..."
git fetch origin
git reset --hard origin/main

# Alternative si le reset échoue
if [ $? -ne 0 ]; then
    echo "🔄 Tentative alternative..."
    git clean -fd
    git checkout -- .
    git pull origin main --force
fi

# Restaurer les permissions
echo "🔧 Restauration des permissions..."
chown -R www-data:www-data .
find . -type d -exec chmod 775 {} \;
find . -type f -exec chmod 664 {} \;
find . -name "*.sh" -exec chmod +x {} \;

# Permissions spéciales
chmod 777 captures logs 2>/dev/null || true

# Vérifier les scripts critiques
echo "✅ Vérification des scripts..."
for script in scripts/linux_capture.sh scripts/linux_print.sh; do
    if [ -f "$script" ]; then
        chmod +x "$script"
        echo "  ✅ $script OK"
    else
        echo "  ⚠️ $script manquant"
    fi
done

echo ""
echo "🎉 Mise à jour sécurisée terminée !"
echo "📁 Sauvegarde: $backup_dir"
echo "🌐 Testez l'application sur: http://$(hostname -I | awk '{print $1}')/Photomaton/"
