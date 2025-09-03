#!/bin/bash

# ============================================================================
# PHOTOMATON LINUX - Script d'impression CUPS
# Compatible Canon SELPHY CP1500 et autres imprimantes photo
# ============================================================================

# Configuration
CAPTURES_DIR="/var/www/html/Photomaton/captures"
LOG_FILE="/var/www/html/Photomaton/logs/print_log.txt"
PRINTER_NAME="Canon_SELPHY_CP1500"
PPD_FILE="/var/www/html/Photomaton/ppd/Canon_SELPHY_CP1500.ppd"
DEFAULT_COPIES=1
DEFAULT_MEDIA="Postcard.Fullbleed"  # Format Canon SELPHY exact du PPD

# Fonction de logging
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] LINUX_PRINT: $1" | tee -a "$LOG_FILE"
}

# Fonction d'erreur
error_exit() {
    log_message "ERREUR: $1"
    echo "ERROR: $1" >&2
    exit 1
}

# Vérifier CUPS et les dépendances
check_cups() {
    log_message "Vérification CUPS..."
    
    if ! command -v lp &> /dev/null; then
        error_exit "CUPS n'est pas installé. Installez avec: sudo apt install cups"
    fi
    
    if ! systemctl is-active --quiet cups; then
        error_exit "Service CUPS non démarré. Démarrez avec: sudo systemctl start cups"
    fi
    
    log_message "CUPS OK"
}

# Lister les imprimantes disponibles
list_printers() {
    log_message "Imprimantes disponibles:"
    lpstat -p 2>/dev/null | while read -r line; do
        log_message "  $line"
    done
}

# Détecter l'imprimante Canon SELPHY
detect_printer() {
    log_message "Détection imprimante Canon SELPHY..."
    
    # Chercher différentes variantes du nom
    local printer_found=""
    
    # Chercher par nom exact
    if lpstat -p "$PRINTER_NAME" >/dev/null 2>&1; then
        printer_found="$PRINTER_NAME"
    else
        # Chercher par pattern
        local printers=($(lpstat -p | grep -i "selphy\|cp1500\|canon" | awk '{print $2}'))
        
        if [[ ${#printers[@]} -gt 0 ]]; then
            printer_found="${printers[0]}"
            log_message "Imprimante trouvée par pattern: $printer_found"
        fi
    fi
    
    if [[ -n "$printer_found" ]]; then
        PRINTER_NAME="$printer_found"
        log_message "Imprimante configurée: $PRINTER_NAME"
        
        # Vérifier le statut
        local status=$(lpstat -p "$PRINTER_NAME" 2>/dev/null)
        log_message "Statut: $status"
        
        return 0
    else
        log_message "Aucune imprimante Canon SELPHY trouvée"
        list_printers
        return 1
    fi
}

# Configuration automatique imprimante USB
auto_setup_printer() {
    log_message "Configuration automatique imprimante..."
    
    # Détecter les imprimantes USB
    local usb_printers=$(sudo lpinfo -v | grep usb | grep -i "canon\|selphy")
    
    if [[ -n "$usb_printers" ]]; then
        log_message "Imprimante USB détectée:"
        log_message "$usb_printers"
        
        # Extraire l'URI USB
        local usb_uri=$(echo "$usb_printers" | head -n1 | awk '{print $2}')
        
        # Ajouter l'imprimante à CUPS avec le fichier PPD si disponible
        log_message "Ajout imprimante à CUPS..."
        if [[ -f "$PPD_FILE" ]]; then
            log_message "Utilisation du fichier PPD: $PPD_FILE"
            sudo lpadmin -p "$PRINTER_NAME" -v "$usb_uri" -P "$PPD_FILE" -E
        else
            log_message "Fichier PPD non trouvé, utilisation du driver par défaut"
            sudo lpadmin -p "$PRINTER_NAME" -v "$usb_uri" -E -m everywhere 2>/dev/null || \
            sudo lpadmin -p "$PRINTER_NAME" -v "$usb_uri" -E -m raw
        fi
        
        # Définir par défaut
        sudo lpadmin -d "$PRINTER_NAME"
        
        log_message "Imprimante configurée: $PRINTER_NAME"
        return 0
    else
        error_exit "Aucune imprimante Canon USB détectée"
    fi
}

# Fonction pour valider les formats PPD
validate_ppd_format() {
    local format="$1"
    
    if [[ ! -f "$PPD_FILE" ]]; then
        log_message "Fichier PPD non trouvé, utilisation des formats par défaut"
        return 1
    fi
    
    # Vérifier si le format existe dans le PPD
    if grep -q "^\*PageSize $format:" "$PPD_FILE"; then
        log_message "Format '$format' validé dans le PPD"
        return 0
    else
        log_message "Format '$format' non trouvé dans le PPD"
        return 1
    fi
}

# Lister les formats disponibles depuis le PPD
list_ppd_formats() {
    if [[ -f "$PPD_FILE" ]]; then
        log_message "Formats disponibles dans le PPD:"
        grep "^\*PageSize" "$PPD_FILE" | sed 's/^\*PageSize /  /' | cut -d: -f1 | while read format; do
            log_message "  - $format"
        done
    else
        log_message "Fichier PPD non disponible"
    fi
}
print_image() {
    local image_path="$1"
    local copies="${2:-$DEFAULT_COPIES}"
    local media="${3:-$DEFAULT_MEDIA}"
    
    # Validation des paramètres
    if [[ -z "$image_path" ]]; then
        error_exit "Chemin d'image requis"
    fi
    
    if [[ ! -f "$image_path" ]]; then
        error_exit "Fichier image introuvable: $image_path"
    fi
    
    log_message "Impression: $image_path (${copies} copie(s), média: $media)"
    
    # Construire la commande d'impression avec options précises du PPD
    local print_options=""
    
    # Options spécifiques Canon SELPHY basées sur le fichier PPD
    case "$media" in
        "Postcard.Fullbleed"|"postcard"|"10x15")
            # Format 10x15cm sans bordure (format par défaut du PPD)
            print_options="-o media=Postcard.fullbleed -o landscape -o fit-to-page"
            ;;
        "Postcard")
            # Format 10x15cm avec bordure
            print_options="-o media=Postcard -o landscape -o fit-to-page"
            ;;
        "54x86mm.Fullbleed")
            # Format carte de crédit sans bordure
            print_options="-o media=54x86mm.fullbleed -o landscape -o fit-to-page"
            ;;
        "54x86mm")
            # Format carte de crédit avec bordure
            print_options="-o media=54x86mm -o landscape -o fit-to-page"
            ;;
        "89x119mm.Fullbleed")
            # Format L sans bordure
            print_options="-o media=89x119mm.fullbleed -o landscape -o fit-to-page"
            ;;
        "89x119mm")
            # Format L avec bordure
            print_options="-o media=89x119mm -o landscape -o fit-to-page"
            ;;
        *)
            # Fallback avec le format par défaut du PPD
            print_options="-o media=Postcard.fullbleed -o landscape -o fit-to-page"
            log_message "Format '$media' non reconnu, utilisation de Postcard.Fullbleed"
            ;;
    esac
    
    # Commande d'impression
    log_message "USER: $(whoami), UID: $(id -u), GROUPS: $(groups)"
    log_message "PATH: $PATH"
    log_message "Imprimante active: $PRINTER_NAME"
    
    # Vérifier l'état de l'imprimante avant impression
    local printer_status=$(lpstat -p "$PRINTER_NAME" 2>/dev/null)
    log_message "État imprimante: $printer_status"
    
    # Vérifier si l'imprimante accepte les travaux
    if echo "$printer_status" | grep -q "disabled\|reject"; then
        log_message "ATTENTION: Imprimante disabled ou reject - tentative d'activation"
        cupsenable "$PRINTER_NAME" 2>/dev/null || true
        cupsaccept "$PRINTER_NAME" 2>/dev/null || true
    fi
    
    local cmd="lp -d '$PRINTER_NAME' -n $copies $print_options '$image_path'"
    log_message "Commande finale: $cmd"
    
    # Exécution
    local output
    local exit_code
    
    output=$(eval "$cmd" 2>&1)
    exit_code=$?
    
    if [[ $exit_code -eq 0 ]]; then
        local job_id=$(echo "$output" | grep -o "request id is [^[:space:]]*" | awk '{print $4}')
        log_message "SUCCÈS: Impression envoyée (Job ID: $job_id)"
        
        # Vérifier le statut du travail
        if [[ -n "$job_id" ]]; then
            sleep 2
            local job_status=$(lpstat "$job_id" 2>/dev/null || echo "Job status unavailable")
            log_message "Statut job: $job_status"
            
            # Vérifier la queue d'impression générale
            local queue_status=$(lpstat -o 2>/dev/null || echo "No jobs in queue")
            log_message "Queue d'impression: $queue_status"
        fi
        
        echo "SUCCESS: $job_id"
        return 0
    else
        log_message "ÉCHEC impression: $output"
        echo "ERROR: $output"
        return 1
    fi
}

# Fonction pour vérifier l'état d'une impression
check_print_status() {
    local job_id="$1"
    
    if [[ -n "$job_id" ]]; then
        lpstat -W completed "$job_id" 2>/dev/null || lpstat "$job_id" 2>/dev/null
    else
        lpstat -o 2>/dev/null
    fi
}

# Fonction principale
main() {
    local action="${1:-print}"
    local image_path="$2"
    local copies="${3:-1}"
    local media="${4:-4x6}"
    
    log_message "=== DÉMARRAGE IMPRESSION LINUX ==="
    log_message "Action: $action, Image: $image_path, Copies: $copies, Média: $media"
    
    case "$action" in
        "print")
            check_cups
            
            if ! detect_printer; then
                auto_setup_printer
            fi
            
            print_image "$image_path" "$copies" "$media"
            ;;
            
        "setup")
            check_cups
            auto_setup_printer
            ;;
            
        "status")
            check_print_status "$image_path"
            ;;
            
        "list")
            list_printers
            ;;
            
        "test")
            check_cups
            detect_printer
            list_printers
            echo "Test OK - Système d'impression prêt"
            ;;
            
        *)
            echo "Usage: $0 <action> [paramètres]"
            echo ""
            echo "Actions:"
            echo "  print <image> [copies] [media]  - Imprimer une image"
            echo "  setup                           - Configurer l'imprimante"
            echo "  status [job_id]                 - Vérifier le statut"
            echo "  list                           - Lister les imprimantes"
            echo "  test                           - Test du système"
            echo ""
            echo "Exemples:"
            echo "  $0 print /path/photo.jpg 2 4x6"
            echo "  $0 setup"
            echo "  $0 test"
            exit 1
            ;;
    esac
}

# Gestion des signaux
trap 'log_message "Script interrompu"' INT TERM

# Exécution
main "$@"
