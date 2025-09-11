# Photomaton Raspberry Pi - Mode Kiosque

Ce guide explique comment configurer le Raspberry Pi pour démarrer automatiquement le photomaton en mode plein écran.

## 🚀 Installation rapide

```bash
# Se placer dans le dossier Photomaton
cd /var/www/html/Photomaton

# Rendre les scripts exécutables
chmod +x scripts/*.sh

# Lancer l'installation
./scripts/install_kiosk.sh

# Redémarrer le Pi
sudo reboot
```

## 📁 Fichiers créés

- `scripts/start_kiosk.sh` - Script principal du mode kiosque
- `scripts/install_kiosk.sh` - Installation automatique
- `scripts/uninstall_kiosk.sh` - Désinstallation
- `scripts/test_kiosk.sh` - Test du mode kiosque
- `~/.config/autostart/photomaton-kiosk.desktop` - Autostart du bureau

## ⚙️ Configuration

### URL du photomaton
Modifiez dans `start_kiosk.sh` :
```bash
PHOTOMATON_URL="http://localhost/Photomaton/"
```

### Délai de démarrage
Modifiez dans le fichier `.desktop` :
```
X-GNOME-Autostart-Delay=10
```

### Options Chromium
Personnalisez les options dans `start_kiosk.sh` section `chromium-browser`.

## 🎮 Commandes utiles

```bash
# Tester le kiosque maintenant
./scripts/test_kiosk.sh

# Voir les logs en temps réel
tail -f /var/log/photomaton_kiosk.log

# Arrêter le mode kiosque
pkill chromium-browser

# Redémarrer en mode kiosque
./scripts/start_kiosk.sh &

# Désinstaller complètement
./scripts/uninstall_kiosk.sh
```

## 🔧 Dépannage

### Chromium ne démarre pas
```bash
# Vérifier X11
echo $DISPLAY
xset q

# Tester Chromium manuellement
chromium-browser --version
```

### Apache non accessible
```bash
# Vérifier Apache
sudo systemctl status apache2
curl http://localhost/Photomaton/

# Redémarrer Apache
sudo systemctl restart apache2
```

### Logs de diagnostic
```bash
# Logs du kiosque
tail -50 /var/log/photomaton_kiosk.log

# Logs Apache
sudo tail /var/log/apache2/error.log

# Logs système
journalctl -u lightdm -f
```

## 🖥️ Affichage

### Résolution d'écran
Configurez dans `/boot/config.txt` :
```
# Forcer une résolution
hdmi_force_hotplug=1
hdmi_group=2
hdmi_mode=82  # 1920x1080 60Hz
```

### Rotation d'écran
```bash
# Dans /boot/config.txt
display_rotate=1  # 90°
display_rotate=2  # 180°
display_rotate=3  # 270°
```

## 🔐 Sécurité

### Désactiver les raccourcis dangereux
Le script désactive automatiquement :
- Alt+F4 (fermer)
- Ctrl+Shift+Q (quitter)
- F11 (sortir du plein écran)

### Masquer le curseur
Le script utilise `unclutter` pour masquer automatiquement le curseur.

## 📱 Écran tactile

Pour un écran tactile, ajoutez ces options dans `start_kiosk.sh` :
```bash
--touch-events=enabled \
--enable-touch-drag-drop \
--enable-touchview \
```

## 🔄 Redémarrage automatique

Le script surveille Chromium et le relance automatiquement s'il plante.

Pour redémarrer le Pi toutes les nuits :
```bash
# Ajouter au crontab de root
sudo crontab -e

# Redémarrage à 4h du matin
0 4 * * * /sbin/reboot
```

## 🆘 Support

En cas de problème :
1. Consultez les logs : `tail -f /var/log/photomaton_kiosk.log`
2. Testez manuellement : `./scripts/test_kiosk.sh`
3. Redémarrez le Pi : `sudo reboot`
4. Désinstallez et réinstallez si nécessaire
