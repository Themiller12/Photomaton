#!/bin/bash

# ============================================================================
# PHOTOMATON RASPBERRY PI - Désinstallation du mode kiosque
# Supprime le démarrage automatique du photomaton
# ============================================================================

echo "🗑️  Désinstallation du mode kiosque Photomaton..."

# Arrêter Chromium s'il fonctionne
echo "🛑 Arrêt de Chromium..."
pkill chromium-browser 2>/dev/null || true

# Supprimer le fichier autostart
AUTOSTART_FILE="/home/pi/.config/autostart/photomaton-kiosk.desktop"
if [ -f "$AUTOSTART_FILE" ]; then
    echo "📝 Suppression de l'autostart..."
    rm "$AUTOSTART_FILE"
else
    echo "ℹ️  Aucun fichier autostart trouvé"
fi

# Nettoyer les données Chromium
echo "🧹 Nettoyage des données Chromium..."
rm -rf ~/.config/chromium/Default/Web\ Data-lock 2>/dev/null || true
rm -rf ~/.config/chromium/SingletonLock 2>/dev/null || true

# Restaurer la configuration d'affichage par défaut
echo "🖥️  Restauration de l'affichage..."
grep -v "# Configuration Photomaton Kiosk" ~/.bashrc > ~/.bashrc.tmp 2>/dev/null || true
grep -v "export DISPLAY=:0" ~/.bashrc.tmp > ~/.bashrc.tmp2 2>/dev/null || true
grep -v "xset s off" ~/.bashrc.tmp2 > ~/.bashrc.tmp3 2>/dev/null || true
grep -v "xset -dpms" ~/.bashrc.tmp3 > ~/.bashrc.tmp4 2>/dev/null || true
grep -v "xset s noblank" ~/.bashrc.tmp4 > ~/.bashrc 2>/dev/null || true
rm -f ~/.bashrc.tmp* 2>/dev/null || true

echo ""
echo "✅ Désinstallation terminée !"
echo ""
echo "🔄 Redémarrez le Raspberry Pi pour que les changements prennent effet"
echo "   sudo reboot"
