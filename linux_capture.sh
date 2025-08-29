#!/bin/bash

# ============================================================================
# PHOTOMATON LINUX - Script de capture photo
# Compatible avec gPhoto2 pour appareils photo Canon, Nikon, Sony, etc.
# ============================================================================

# Configuration
CAPTURE_DIR="/var/www/html/Photomaton/captures"
LOG_FILE="/var/www/html/Photomaton/capture_log.txt"
TEMP_DIR="/tmp/photomaton"
LOCK_FILE="/tmp/photomaton_capture.lock"

# Fonction de logging
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Fonction d'erreur
error_exit() {
    log_message "ERREUR: $1"
    echo "ERROR: $1" >&2
    exit 1
}

# Vérifier les dépendances
check_dependencies() {
    log_message "Vérification des dépendances..."
    
    if ! command -v gphoto2 &> /dev/null; then
        error_exit "gPhoto2 n'est pas installé. Installez avec: sudo apt install gphoto2"
    fi
    
    if ! command -v identify &> /dev/null; then
        error_exit "ImageMagick n'est pas installé. Installez avec: sudo apt install imagemagick"
    fi
    
    log_message "Dépendances OK"
}

# Créer les dossiers nécessaires
setup_directories() {
    mkdir -p "$CAPTURE_DIR" || error_exit "Impossible de créer $CAPTURE_DIR"
    mkdir -p "$TEMP_DIR" || error_exit "Impossible de créer $TEMP_DIR"
    chmod 755 "$CAPTURE_DIR" 2>/dev/null || true
    
    log_message "Dossiers créés/vérifiés (UID=$(id -u), USER=$(whoami))"
}

# Gestion de verrou pour éviter les accès concurrents à gphoto2
acquire_lock() {
    if [ -f "$LOCK_FILE" ]; then
        local age=$(( $(date +%s) - $(stat -c %Y "$LOCK_FILE" 2>/dev/null || echo 0) ))
        # Si lock plus vieux que 30s on le considère abandonné
        if [ $age -gt 30 ]; then
            log_message "Lock ancien (>30s), suppression de sécurité"
            rm -f "$LOCK_FILE"
        else
            log_message "Un autre processus de capture est en cours (lock présent)"
            error_exit "Capture déjà en cours, réessayez dans quelques secondes"
        fi
    fi
    echo $$ > "$LOCK_FILE" || error_exit "Impossible de créer le lockfile"
    trap release_lock EXIT INT TERM
}

release_lock() {
    [ -f "$LOCK_FILE" ] && rm -f "$LOCK_FILE"
}

# Tentative de libération de la caméra si bloquée
free_camera_if_busy() {
    if ! gphoto2 --summary >/dev/null 2>&1; then
        log_message "Appareil semble occupé - tentative de libération (kill gvfs / reset)"
        sudo killall -q gvfs-gphoto2-volume-monitor 2>/dev/null || true
        sudo killall -q gvfsd-gphoto2 2>/dev/null || true
        # Tuer d'éventuels gphoto2 zombies
        pkill -f gphoto2 2>/dev/null || true
        sleep 2
        gphoto2 --reset >/dev/null 2>&1 || true
        sleep 1
    fi
}

# Détecter l'appareil photo
detect_camera() {
    log_message "Détection de l'appareil photo..."
    
    # Vérifier si un appareil est connecté
    if ! gphoto2 --auto-detect | grep -q "usb:"; then
        error_exit "Aucun appareil photo détecté. Vérifiez la connexion USB."
    fi
    
    # Obtenir le modèle
    CAMERA_MODEL=$(gphoto2 --summary 2>/dev/null | grep "Model:" | cut -d: -f2 | xargs)
    log_message "Appareil détecté: $CAMERA_MODEL"
    
    # Vérifier si l'appareil est libre (pas utilisé par un autre processus)
    free_camera_if_busy

    if ! gphoto2 --summary >/dev/null 2>&1; then
        error_exit "Appareil photo occupé ou inaccessible après tentative de libération"
    fi
}

# Fonction de capture principale
capture_photo() {
    local filename_base="capture_$(date +%Y%m%d_%H%M%S)_$(openssl rand -hex 3)"
    local temp_file="$TEMP_DIR/${filename_base}.jpg"
    local final_file="$CAPTURE_DIR/${filename_base}.jpg"
    
    log_message "Début capture: $filename_base"
    
    # Capture avec gPhoto2
    log_message "Déclenchement de l'appareil photo..."
    
    # Utiliser LC_ALL=C pour une sortie stable
    if LC_ALL=C gphoto2 --capture-image-and-download --filename="$temp_file" --force-overwrite 2>&1 | tee -a "$LOG_FILE"; then
        log_message "Capture réussie dans $temp_file"
    else
        error_exit "Échec de la capture photo"
    fi
    
    # Vérifier que le fichier existe et n'est pas vide
    if [[ ! -f "$temp_file" ]] || [[ ! -s "$temp_file" ]]; then
        error_exit "Fichier capturé invalide ou vide"
    fi
    
    # Obtenir les informations de l'image
    local image_info=$(identify "$temp_file" 2>/dev/null)
    log_message "Image info: $image_info"
    
    # Déplacer vers le dossier final
    if mv "$temp_file" "$final_file"; then
        chmod 644 "$final_file"
        log_message "Photo sauvegardée: $final_file"
        
        # Retourner le nom du fichier (chemin relatif)
        echo "captures/${filename_base}.jpg"
        return 0
    else
        error_exit "Impossible de déplacer le fichier vers $final_file"
    fi
}

# Fonction principale
main() {
    log_message "=== DÉMARRAGE CAPTURE LINUX ==="
    
    # Vérifications initiales
    check_dependencies
    acquire_lock
    setup_directories
    detect_camera
    
    # Capture
    local captured_file
    captured_file=$(capture_photo)
    
    if [[ $? -eq 0 ]] && [[ -n "$captured_file" ]]; then
        log_message "SUCCÈS: Photo capturée - $captured_file"
        echo "$captured_file"
        exit 0
    else
        error_exit "Échec de la capture"
    fi
}

# Configuration avancée (optionnel)
configure_camera() {
    log_message "Configuration de l'appareil photo..."
    
    # Exemples de configuration (adapter selon votre appareil)
    gphoto2 --set-config iso=400 2>/dev/null || true
    gphoto2 --set-config imageformat=jpeg 2>/dev/null || true
    gphoto2 --set-config imagequality=fine 2>/dev/null || true
    gphoto2 --set-config whitebalance=auto 2>/dev/null || true
    
    log_message "Configuration appliquée"
}

# Fonction de nettoyage
cleanup() {
    rm -f "$TEMP_DIR"/capture_* 2>/dev/null || true
    release_lock
}

# Gestion des signaux
trap cleanup EXIT INT TERM

# Exécution en fonction des paramètres
case "${1:-capture}" in
    "capture")
        main
        ;;
    "detect")
        detect_camera
        ;;
    "config")
        configure_camera
        ;;
    "test")
        check_dependencies
        detect_camera
        echo "Test OK - Appareil prêt"
        ;;
    *)
        echo "Usage: $0 [capture|detect|config|test]"
        echo "  capture  - Capturer une photo (défaut)"
        echo "  detect   - Détecter l'appareil photo"
        echo "  config   - Configurer l'appareil"
        echo "  test     - Test des dépendances et détection"
        exit 1
        ;;
esac
