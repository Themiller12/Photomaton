# Photomaton de Mariage

Application web simple pour borne photomaton (tablette + appareil photo ou webcam).

## Fonctionnalités actuelles

- Accueil : bouton "Nouvelle Photo" et "Galerie".
- Prise de vues (implémentation actuelle via webcam du navigateur) :
  - Décompte 3 → 1 avant chaque prise.
  - 3 photos prises avec 3 secondes d'intervalle.
  - Sélection d'une des 3 photos et choix du nombre de copies.
- Sauvegarde de la photo choisie dans `captures/` et duplication simulant l'impression dans `prints/`.
- Galerie listant les photos présentes dans `captures/`.

## Limites / TODO

| Besoin | État |
|--------|------|
| Contrôle réel d'un Reflex Canon USB | À intégrer (voir ci-dessous) |
| Impression réelle (imprimante dye-sub) | Placeholder (copies de fichier) |
| Protection contre veille tablette | À configurer côté OS |
| Mode plein écran kiosk | À activer dans le navigateur (F11 / app kiosk) |
| Nettoyage automatique ancien fichiers | À ajouter (cron / tâche planifiée) |

## Intégration d'un Canon (gphoto2 – Linux / Raspberry Pi conseillé)

Sous Linux, installez `gphoto2` :

```bash
sudo apt update
sudo apt install gphoto2
```

Tester la détection :

```bash
gphoto2 --auto-detect
```

Le fichier `start_sequence.php` (fourni) tente d'utiliser `gphoto2` pour capturer 3 images. Front-end à adapter pour appeler ce endpoint au lieu de la webcam :

```js
// Exemple (remplacer runSequence existant)
async function runSequenceDslr(){
  startBtn.disabled = true;
  const res = await fetch('start_sequence.php');
  const data = await res.json();
  if(!res.ok) { alert(data.error||'Erreur capture'); return; }
  captured = data.files.map(f=>f); // chemins des fichiers
  // Charger en <img src="f"> sans base64
  showSelection();
}
```

Adapter ensuite la partie impression : si vous ne passez plus par `save_print.php` (base64), créez un endpoint `print_file.php` qui reçoit `{file, copies}` et envoie à l'imprimante.

### Windows (EDSDK / EOS Utility / Sony API)

Sous Windows il n'existe pas d'outil CLI universel officiel. Options :

1. (Implémenté) Utiliser digiCamControl + CLI `CameraControlCmd.exe` (fichier `win_capture.php`) – haute résolution.
2. Utiliser EOS Utility + dossier de déchargement auto et script de polling.
3. Utiliser un mini service en Node.js + package natif (stabilité variable).
4. Migrer la borne vers un petit PC Linux ou Raspberry Pi pour bénéficier de `gphoto2` (souvent le plus simple).

### Mode DSLR Windows (digiCamControl)
#### Préparation connexion USB (exemple Sony Alpha)

1. Activer dans le boîtier: Menu > Réglages USB > Mode connexion: "PC Remote" (ou "Contrôle à distance PC").
2. Désactiver le Wi‑Fi / Smart Remote si actif (USB et Wi‑Fi ne fonctionnent pas simultanément pour le contrôle).
3. Brancher directement sur un port USB du PC (éviter hub passif).
4. Ouvrir digiCamControl GUI et vérifier que l'appareil apparaît dans la liste (sinon installer pilotes si requis).
5. Fermer tout autre logiciel de tethering (Imaging Edge, Lightroom tether, etc.).
6. Tester en ligne de commande: 
  ```powershell
  & "C:\Program Files (x86)\digiCamControl\CameraControlCmd.exe" /list
  ```
7. Lancer préflight: `http://localhost/Photomaton/usb_preflight.php` (et `usb_preflight.php?test=1` pour capture test).

Si la capture test ne génère pas de fichier:
- Vérifier droits d'écriture du dossier `captures/`.
- Simplifier pattern dans `config.php` (ex: `photo_%i.jpg`).
- Mettre à jour digiCamControl.
- Essayer un autre câble.

### Mode Sony WiFi (Camera Remote API)
### Mode Sony SDK (USB) – expérimental

Si votre modèle est supporté par le Sony Camera Remote SDK (attention: certains anciens modèles comme ILCE-7S original peuvent ne pas l'être), vous pouvez :

1. Compiler le projet dans `sony/` via CMake + Visual Studio (générer en Release x64). Le binaire attendu : `sony/build/RemoteCli.exe`.
2. Dans `config.php` mettre `$CAPTURE_MODE = 'sony_sdk';` et vérifier `$SONY_SDK_CLI`.
3. L'endpoint `sony_sdk_capture.php` déclenche `RemoteCli.exe --auto --capture-once` qui a été patché pour un mode non interactif.
4. Le fichier JPEG téléchargé est détecté dans `captures/` et renvoyé au front.

Arguments ajoutés au binaire :
```
--auto (ou --auto-capture) : mode sans interaction, première caméra
--capture-once            : capture unique puis sortie
--index=N                 : caméra N (1-based)
--wait=ms                 : attente post capture
```

Limitation: si le modèle n'est pas officiellement supporté, la capture échouera malgré la détection du boîtier.


Ajout d'un endpoint `sony_wifi_capture.php` qui :
1. Envoie `startRecMode` (ignoré si déjà prêt).
2. Envoie `actTakePicture`.
3. Télécharge l'URL retournée et sauvegarde le JPEG dans `captures/`.

Configuration dans `config.php` :
```
$CAPTURE_MODE = 'sony_wifi';
$SONY_WIFI_IP = '192.168.x.x';
```

Front : dans `shoot.php` définir `window.PHOTOMATON_MODE = 'sony_wifi';`.

Limitations : Certains modèles récents peuvent nécessiter une version d'API > 1.0 ou l'activation spécifique du contrôle à distance; différents firmwares ont réduit le support de l'ancienne Camera Remote API.


Fichiers ajoutés : `config.php`, `win_capture.php` et adaptation `capture.js`.

`config.php` définit :
```
$CAPTURE_MODE = 'dslr_win';
$CAMERA_CMD = 'C:\\Program Files\\digiCamControl\\CameraControlCmd.exe';
```

Le front (dans `shoot.php`) fixe `window.PHOTOMATON_MODE = 'dslr_win';`.

Séquence : le front effectue 3 appels à `win_capture.php` après un compte à rebours. Chaque appel déclenche une capture via digiCamControl et attend le nouveau fichier.

Si vous souhaitez implémenter une impression directe sans reconversion base64, créez `print_file.php` et envoyez simplement `{ file:'captures/xxx.jpg', copies:n }`.

## Impression réelle

Selon l'imprimante (ex: DNP, Canon Selphy) :

- Sous Windows : script PowerShell `Start-Process -FilePath image.jpg -Verb Print` (attention boîtes de dialogue).
- Sous Linux : `lp -n COPIES chemin/image.jpg` après configuration CUPS.

Vous pouvez remplacer la boucle de copie dans `save_print.php` par un appel à `lp` :

```php
exec('lp -n '.intval($copies).' '.escapeshellarg($fullPath).' 2>&1', $out, $code);
```

## Sécurité & Durabilité

- Mettre la tablette / PC en mode kiosk (pas de barre d'adresse utilisateur).
- Empêcher la mise en veille écran / USB.
- Sauvegarder périodiquement `captures/` sur un stockage externe.

## Structure

```
index.php
shoot.php
gallery.php
save_print.php
start_sequence.php (capture DSLR gphoto2)
captures/ (photos sauvegardées)
prints/   (traces pseudo-impression)
src/css/style.css
src/js/capture.js
```

## Étapes prochaines suggérées

1. Choisir plateforme (Linux recommandé pour gphoto2).
2. Tester capture reflex via `start_sequence.php`.
3. Adapter front-end pour DSLR (remplacer getUserMedia).
4. Implémenter véritable impression selon votre imprimante (endpoint `print_file.php` ajouté pour mode fichier direct).
5. Ajouter rotation auto / recadrage si besoin (ex: `imagick`).
6. Ajouter un thème mariage personnalisé (couleurs, overlay PNG).

## Licence

Projet de démonstration – à adapter selon vos besoins de mariage.
