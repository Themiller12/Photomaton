#!/bin/bash

# ============================================================================
# PHOTOMATON RASPBERRY PI - Script de démarrage Chromium Kiosk
# Démarre automatiquement le photomaton en mode plein écran
# ============================================================================

# Configuration
PHOTOMATON_URL="http://localhost/Photomaton/"
LOG_FILE="/var/log/photomaton_kiosk.log"
DISPLAY_NUM=":0"

# Fonction de logging
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] KIOSK: $1" | tee -a "$LOG_FILE"
}

# Attendre que X11 soit prêt
wait_for_x11() {
    log_message "Attente de X11..."
    while ! xset q &>/dev/null; do
        log_message "X11 non disponible, attente..."
        sleep 2
    done
    log_message "X11 prêt"
}

# Attendre qu'Apache soit prêt
wait_for_apache() {
    log_message "Vérification d'Apache..."
    local attempts=0
    local max_attempts=30
    
    while [ $attempts -lt $max_attempts ]; do
        if curl -f -s "$PHOTOMATON_URL" >/dev/null 2>&1; then
            log_message "Apache et Photomaton prêts"
            return 0
        fi
        
        log_message "Apache non prêt, tentative $((attempts + 1))/$max_attempts"
        sleep 2
        attempts=$((attempts + 1))
    done
    
    log_message "ERREUR: Apache non accessible après $max_attempts tentatives"
    return 1
}

# Configurer l'affichage
setup_display() {
    log_message "Configuration de l'affichage..."
    
    # Désactiver l'économiseur d'écran et la mise en veille
    export DISPLAY="$DISPLAY_NUM"
    xset s off
    xset -dpms
    xset s noblank
    
    # Masquer le curseur après 1 seconde d'inactivité
    unclutter -idle 1 -root &
    
    log_message "Affichage configuré"
}

# Démarrer Chromium en mode kiosque
start_chromium() {
    log_message "Démarrage de Chromium en mode kiosque..."
    log_message "URL: $PHOTOMATON_URL"
    
    # Supprimer les données de session précédentes pour un démarrage propre
    rm -rf ~/.config/chromium/Default/Web\ Data-lock
    rm -rf ~/.config/chromium/SingletonLock
    
    # Options Chromium pour le mode kiosque
    chromium-browser \
        --kiosk \
        --start-fullscreen \
        --no-sandbox \
        --disable-infobars \
        --disable-restore-session-state \
        --disable-session-crashed-bubble \
        --disable-web-security \
        --disable-features=TranslateUI \
        --disable-ipc-flooding-protection \
        --disable-backgrounding-occluded-windows \
        --disable-renderer-backgrounding \
        --disable-background-timer-throttling \
        --disable-background-networking \
        --autoplay-policy=no-user-gesture-required \
        --window-position=0,0 \
        --app="$PHOTOMATON_URL" \
        &
    
    local chromium_pid=$!
    log_message "Chromium démarré avec PID: $chromium_pid"
    
    # Surveiller Chromium et le relancer si nécessaire
    while true; do
        if ! kill -0 $chromium_pid 2>/dev/null; then
            log_message "Chromium s'est arrêté, redémarrage..."
            sleep 5
            start_chromium
            break
        fi
        sleep 10
    done
}

# Script principal
main() {
    log_message "=== DÉMARRAGE PHOTOMATON KIOSK ==="
    log_message "Utilisateur: $(whoami)"
    log_message "Display: $DISPLAY_NUM"
    
    # Attendre les prérequis
    wait_for_x11
    
    # Petit délai pour laisser le bureau se charger
    sleep 5
    
    # Vérifier qu'Apache fonctionne
    if ! wait_for_apache; then
        log_message "ERREUR CRITIQUE: Impossible d'accéder au Photomaton"
        exit 1
    fi
    
    # Configurer l'affichage
    setup_display
    
    # Démarrer Chromium
    start_chromium
}

# Trap pour un arrêt propre
trap 'log_message "Arrêt du kiosque"; pkill chromium-browser; exit 0' SIGINT SIGTERM

# Lancer le script principal
main "$@"
