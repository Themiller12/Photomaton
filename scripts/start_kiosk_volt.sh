#!/bin/bash

# ============================================================================
# PHOTOMATON RASPBERRY PI - Script de démarrage Chromium Kiosk
# Démarre automatiquement le photomaton en mode plein écran
# Adapté pour l'utilisateur volt
# ============================================================================

# Configuration
PHOTOMATON_URL="http://localhost/Photomaton/"
LOG_FILE="/home/volt/photomaton_kiosk.log"
DISPLAY_NUM=":0"
USER="volt"

# Fonction de logging
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] KIOSK: $1" | tee -a "$LOG_FILE"
}

# Configuration de l'environnement pour l'utilisateur volt
setup_environment() {
    export USER="volt"
    export HOME="/home/volt"
    export DISPLAY=":0"
    export XAUTHORITY="/home/volt/.Xauthority"
    
    log_message "Configuration environnement pour utilisateur: $USER"
    log_message "HOME: $HOME"
    log_message "DISPLAY: $DISPLAY"
}

# Attendre que X11 soit prêt
wait_for_x11() {
    log_message "Attente de X11..."
    local max_attempts=30
    local attempt=0
    
    while [ $attempt -lt $max_attempts ]; do
        if xdpyinfo -display "$DISPLAY_NUM" >/dev/null 2>&1; then
            log_message "X11 est prêt après $attempt tentatives"
            return 0
        fi
        
        log_message "X11 pas encore prêt (tentative $((attempt + 1))/$max_attempts)"
        sleep 2
        attempt=$((attempt + 1))
    done
    
    log_message "ERREUR: X11 non disponible après $max_attempts tentatives"
    return 1
}

# Attendre qu'Apache soit démarré
wait_for_apache() {
    log_message "Vérification d'Apache..."
    local max_attempts=30
    local attempt=0
    
    while [ $attempt -lt $max_attempts ]; do
        if systemctl is-active --quiet apache2; then
            log_message "Apache2 est actif"
            if curl -s "$PHOTOMATON_URL" >/dev/null 2>&1; then
                log_message "Photomaton accessible à $PHOTOMATON_URL"
                return 0
            fi
        fi
        
        log_message "Apache/Photomaton pas encore prêt (tentative $((attempt + 1))/$max_attempts)"
        sleep 3
        attempt=$((attempt + 1))
    done
    
    log_message "ERREUR: Apache/Photomaton non disponible"
    return 1
}

# Tuer les processus Chromium existants
kill_existing_chromium() {
    log_message "Nettoyage des processus Chromium existants..."
    pkill -f chromium-browser 2>/dev/null || true
    pkill -f chrome 2>/dev/null || true
    sleep 2
}

# Lancer Chromium en mode kiosk
start_chromium_kiosk() {
    log_message "Démarrage de Chromium en mode kiosk..."
    
    # Supprimer les verrous de session
    rm -rf /home/volt/.config/chromium/Singleton* 2>/dev/null || true
    
    # Démarrer Chromium avec les paramètres kiosk
    chromium-browser \
        --kiosk \
        --display="$DISPLAY_NUM" \
        --no-first-run \
        --disable-infobars \
        --disable-session-crashed-bubble \
        --disable-restore-session-state \
        --no-default-browser-check \
        --disable-translate \
        --disable-features=TranslateUI \
        --disable-ipc-flooding-protection \
        --password-store=basic \
        --use-fake-ui-for-media-stream \
        --no-sandbox \
        --disable-web-security \
        --disable-features=VizDisplayCompositor \
        --start-fullscreen \
        --window-size=1920,1080 \
        --window-position=0,0 \
        "$PHOTOMATON_URL" \
        >> "$LOG_FILE" 2>&1 &
    
    local chromium_pid=$!
    log_message "Chromium démarré avec PID: $chromium_pid"
    
    # Attendre un peu et vérifier que Chromium fonctionne
    sleep 5
    if kill -0 $chromium_pid 2>/dev/null; then
        log_message "Chromium fonctionne correctement"
        
        # Cacher le curseur
        which unclutter >/dev/null 2>&1 && {
            unclutter -display "$DISPLAY_NUM" -idle 1 &
            log_message "Curseur masqué avec unclutter"
        }
        
        return 0
    else
        log_message "ERREUR: Chromium a échoué au démarrage"
        return 1
    fi
}

# Fonction principale
main() {
    log_message "========== DÉMARRAGE DU MODE KIOSK PHOTOMATON =========="
    log_message "Version adaptée pour l'utilisateur volt"
    
    # Configuration de l'environnement
    setup_environment
    
    # Attendre que le système soit prêt
    sleep 15
    
    # Vérifier et attendre X11
    if ! wait_for_x11; then
        log_message "ÉCHEC: Impossible de se connecter à X11"
        exit 1
    fi
    
    # Vérifier et attendre Apache
    if ! wait_for_apache; then
        log_message "ÉCHEC: Apache/Photomaton non disponible"
        exit 1
    fi
    
    # Nettoyer les processus existants
    kill_existing_chromium
    
    # Démarrer Chromium
    if start_chromium_kiosk; then
        log_message "Mode kiosk démarré avec succès!"
        
        # Surveiller Chromium et redémarrer si nécessaire
        while true; do
            sleep 30
            if ! pgrep -f "chromium.*kiosk" > /dev/null; then
                log_message "Chromium arrêté, redémarrage..."
                kill_existing_chromium
                sleep 5
                start_chromium_kiosk
            fi
        done
    else
        log_message "ÉCHEC: Impossible de démarrer le mode kiosk"
        exit 1
    fi
}

# Vérifier que nous ne sommes pas déjà en cours d'exécution
if pgrep -f "start_kiosk.sh" | grep -v $$ > /dev/null; then
    log_message "Script déjà en cours d'exécution, arrêt"
    exit 0
fi

# Lancer le script principal
main "$@"
