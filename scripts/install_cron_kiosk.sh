#!/bin/bash

# Installation simple avec crontab
echo "Installation du demarrage avec crontab..."

# Sauvegarder le crontab actuel
crontab -l > /tmp/current_crontab 2>/dev/null || touch /tmp/current_crontab

# Supprimer les anciennes entrées Photomaton
grep -v "photomaton\|start_kiosk" /tmp/current_crontab > /tmp/new_crontab

# Ajouter la nouvelle entrée (démarrage 2 minutes après le boot)
echo "@reboot sleep 120 && DISPLAY=:0 /var/www/html/Photomaton/scripts/start_kiosk.sh" >> /tmp/new_crontab

# Installer le nouveau crontab
crontab /tmp/new_crontab

# Nettoyer
rm -f /tmp/current_crontab /tmp/new_crontab

echo "Crontab configure"
echo "Le kiosque demarrera 2 minutes apres le boot"
echo ""
echo "Verification:"
echo "  crontab -l"
echo ""
echo "Pour supprimer:"
echo "  crontab -e  # puis supprimer la ligne @reboot"
