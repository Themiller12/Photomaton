#!/bin/bash

# Script de dépannage et installation multiple
echo "Depannage du mode kiosque Photomaton"
echo "===================================="

# Fonction pour tester le script manuellement
test_manual() {
    echo "Test manuel du script..."
    if [ -f "/var/www/html/Photomaton/scripts/start_kiosk.sh" ]; then
        echo "Lancement en arriere-plan..."
        /var/www/html/Photomaton/scripts/start_kiosk.sh &
        sleep 10
        if pgrep chromium-browser > /dev/null; then
            echo "SUCCESS: Chromium demarre"
            pkill chromium-browser
            echo "Chromium arrete pour les tests"
        else
            echo "ECHEC: Chromium ne demarre pas"
            echo "Verifiez les logs: tail -f /var/log/photomaton_kiosk.log"
        fi
    else
        echo "ERREUR: Script manquant"
    fi
}

# Menu principal
while true; do
    echo ""
    echo "Choisissez une option:"
    echo "1. Diagnostic complet"
    echo "2. Test manuel"
    echo "3. Reinstaller autostart (.desktop)"
    echo "4. Installer service systemd"
    echo "5. Installer crontab"
    echo "6. Tout nettoyer"
    echo "7. Quitter"
    echo ""
    echo "Votre choix (1-7): "
    read -r choice

    case $choice in
        1)
            ./diagnose_kiosk.sh 2>/dev/null || echo "Script diagnose_kiosk.sh manquant"
            ;;
        2)
            test_manual
            ;;
        3)
            echo "Reinstallation autostart..."
            ./install_kiosk.sh
            ;;
        4)
            echo "Installation service systemd..."
            ./install_systemd_kiosk.sh 2>/dev/null || echo "Script systemd manquant"
            ;;
        5)
            echo "Installation crontab..."
            ./install_cron_kiosk.sh 2>/dev/null || echo "Script cron manquant"
            ;;
        6)
            echo "Nettoyage complet..."
            # Supprimer autostart
            rm -f "/home/pi/.config/autostart/photomaton-kiosk.desktop"
            # Supprimer service systemd
            sudo systemctl stop photomaton-kiosk 2>/dev/null || true
            sudo systemctl disable photomaton-kiosk 2>/dev/null || true
            sudo rm -f /etc/systemd/system/photomaton-kiosk.service
            sudo systemctl daemon-reload
            # Nettoyer crontab
            crontab -l | grep -v "start_kiosk" | crontab - 2>/dev/null || true
            # Nettoyer profile
            grep -v "Photomaton Kiosk" ~/.profile > ~/.profile.tmp 2>/dev/null || touch ~/.profile.tmp
            mv ~/.profile.tmp ~/.profile
            echo "Nettoyage termine"
            ;;
        7)
            echo "Au revoir!"
            exit 0
            ;;
        *)
            echo "Choix invalide"
            ;;
    esac
done
