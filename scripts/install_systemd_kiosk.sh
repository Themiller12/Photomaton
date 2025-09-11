#!/bin/bash

# Installation alternative avec service systemd
echo "Installation du service systemd pour le kiosque..."

# Créer le service systemd
sudo tee /etc/systemd/system/photomaton-kiosk.service > /dev/null << 'EOF'
[Unit]
Description=Photomaton Kiosk Mode
After=graphical-session.target
Wants=graphical-session.target

[Service]
Type=simple
User=pi
Group=pi
Environment=DISPLAY=:0
Environment=XDG_RUNTIME_DIR=/run/user/1000
ExecStartPre=/bin/sleep 30
ExecStart=/var/www/html/Photomaton/scripts/start_kiosk.sh
Restart=always
RestartSec=10

[Install]
WantedBy=graphical-session.target
EOF

# Activer le service
sudo systemctl daemon-reload
sudo systemctl enable photomaton-kiosk.service

echo "Service systemd cree et active"
echo "Commandes utiles:"
echo "  - Statut: sudo systemctl status photomaton-kiosk"
echo "  - Demarrer: sudo systemctl start photomaton-kiosk"
echo "  - Arreter: sudo systemctl stop photomaton-kiosk"
echo "  - Desactiver: sudo systemctl disable photomaton-kiosk"
echo "  - Logs: journalctl -u photomaton-kiosk -f"
