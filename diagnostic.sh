#!/bin/bash
# Script de diagnostic pour Photomaton Linux
# Usage: sudo ./diagnostic.sh

echo "🔍 DIAGNOSTIC PHOTOMATON LINUX"
echo "=============================="
echo ""

# Vérifier le répertoire courant
echo "📁 Répertoire courant:"
pwd
echo ""

# Vérifier la structure des dossiers
echo "📂 Structure du projet:"
ls -la /var/www/html/Photomaton/ 2>/dev/null || echo "❌ /var/www/html/Photomaton/ non trouvé"
echo ""

# Vérifier le dossier scripts
echo "📜 Contenu du dossier scripts:"
if [ -d "/var/www/html/Photomaton/scripts" ]; then
    ls -la /var/www/html/Photomaton/scripts/
    echo ""
    
    # Vérifier les permissions des scripts
    echo "🔒 Permissions des scripts Linux:"
    for script in linux_capture.sh linux_print.sh; do
        script_path="/var/www/html/Photomaton/scripts/$script"
        if [ -f "$script_path" ]; then
            echo "  ✅ $script: $(ls -l "$script_path" | cut -d' ' -f1)"
            echo "     Exécutable: $([ -x "$script_path" ] && echo "OUI" || echo "NON")"
        else
            echo "  ❌ $script: NON TROUVÉ"
        fi
    done
else
    echo "❌ Dossier scripts non trouvé"
fi
echo ""

# Vérifier gPhoto2
echo "📷 Installation gPhoto2:"
if command -v gphoto2 &> /dev/null; then
    echo "  ✅ gPhoto2 installé: $(gphoto2 --version | head -1)"
    echo "  📱 Appareils détectés:"
    gphoto2 --auto-detect 2>/dev/null || echo "    ❌ Aucun appareil détecté"
else
    echo "  ❌ gPhoto2 non installé"
fi
echo ""

# Vérifier les permissions web
echo "👤 Permissions utilisateur web:"
echo "  Utilisateur PHP: $(whoami)"
echo "  Groupes: $(groups)"
echo "  Propriétaire /var/www/html/Photomaton: $(ls -ld /var/www/html/Photomaton 2>/dev/null | awk '{print $3":"$4}' || echo "N/A")"
echo ""

# Vérifier les logs
echo "📋 Logs récents:"
log_files="/var/www/html/Photomaton/logs/capture_log.txt"
if [ -f "$log_files" ]; then
    echo "  📝 Dernières entrées capture_log.txt:"
    tail -5 "$log_files" | sed 's/^/    /'
else
    echo "  ❌ Fichier de log non trouvé: $log_files"
fi
echo ""

# Test rapide de capture (si un appareil est connecté)
echo "🧪 Test rapide:"
if command -v gphoto2 &> /dev/null; then
    if gphoto2 --auto-detect 2>/dev/null | grep -q "usb:"; then
        echo "  📷 Tentative de test de connexion..."
        timeout 10s gphoto2 --summary 2>/dev/null && echo "  ✅ Connexion appareil OK" || echo "  ⚠️ Problème de connexion appareil"
    else
        echo "  ⚠️ Aucun appareil photo détecté via USB"
    fi
else
    echo "  ❌ Impossible de tester - gPhoto2 non disponible"
fi

echo ""
echo "🎯 RECOMMANDATIONS:"
echo "=================="

# Recommandations basées sur les vérifications
if [ ! -f "/var/www/html/Photomaton/scripts/linux_capture.sh" ]; then
    echo "1. ❌ Copier les scripts dans /var/www/html/Photomaton/scripts/"
fi

if [ -f "/var/www/html/Photomaton/scripts/linux_capture.sh" ] && [ ! -x "/var/www/html/Photomaton/scripts/linux_capture.sh" ]; then
    echo "2. 🔒 Rendre les scripts exécutables:"
    echo "   sudo chmod +x /var/www/html/Photomaton/scripts/*.sh"
fi

if ! command -v gphoto2 &> /dev/null; then
    echo "3. 📷 Installer gPhoto2:"
    echo "   sudo apt update && sudo apt install gphoto2"
fi

echo "4. 🔄 Redémarrer Apache:"
echo "   sudo systemctl restart apache2"

echo ""
echo "✅ Diagnostic terminé"
