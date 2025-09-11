#!/bin/bash

# Installation crontab uniquement pour l'utilisateur volt
# Photomaton - Crontab Kiosk Setup

echo "=== Installation crontab pour l'utilisateur volt ==="

USER="volt"
SCRIPT_DIR="/home/$USER/Photomaton/scripts"
KIOSK_SCRIPT="$SCRIPT_DIR/start_kiosk.sh"

# Vérifier que le script existe
if [ ! -f "$KIOSK_SCRIPT" ]; then
    echo "Erreur: Le script $KIOSK_SCRIPT n'existe pas"
    echo "Veuillez copier les scripts dans $SCRIPT_DIR"
    exit 1
fi

# Rendre le script exécutable
chmod +x "$KIOSK_SCRIPT"

# Sauvegarder le crontab actuel
echo "Sauvegarde du crontab actuel..."
sudo -u $USER crontab -l > "/tmp/crontab_backup_$(date +%Y%m%d_%H%M%S)" 2>/dev/null || true

# Supprimer les anciennes entrées photomaton/kiosk
echo "Nettoyage des anciennes entrées..."
sudo -u $USER crontab -l 2>/dev/null | grep -v "start_kiosk\|photomaton\|kiosk" | sudo -u $USER crontab - 2>/dev/null || true

# Ajouter la nouvelle entrée avec délai plus long
echo "Ajout de l'entrée crontab..."
(sudo -u $USER crontab -l 2>/dev/null; echo "@reboot sleep 60 && DISPLAY=:0 $KIOSK_SCRIPT >> /home/$USER/kiosk.log 2>&1") | sudo -u $USER crontab -

# Vérifier l'installation
echo ""
echo "Vérification de l'installation:"
echo "Crontab pour $USER:"
sudo -u $USER crontab -l | sed 's/^/  /'

echo ""
echo "=== Installation crontab terminée ==="
echo "Le mode kiosk démarrera 60 secondes après le redémarrage."
echo "Logs disponibles dans: /home/$USER/kiosk.log"
echo ""
echo "Pour tester: sudo -u $USER DISPLAY=:0 $KIOSK_SCRIPT"
echo "Pour redémarrer: sudo reboot"
