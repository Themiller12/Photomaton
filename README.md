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

## Utilisation d'un Canon et d'un Raspberry Pi (conseillé)

Dans l'invité de commande, téléchargez le script :

```
wget https://raw.githubusercontent.com/Themiller12/Photomaton/main/install_photomaton.sh
chmod +x install_photomaton.sh
```

Puis lancez l'installation :

```
sudo ./install_photomaton.sh
```

Le script vous guidera à travers toute l'installation et proposera un redémarrage à la fin.

📋 Après Installation :
Brancher l'appareil photo
Brancher l'imprimante (Canon SELPHY CP1500 suggéré)

Configurer l'imprimante :

```
sudo lpadmin -p Canon_SELPHY_CP1500 -v usb://Canon/CP1500 -P /usr/share/cups/model/Canon_SELPHY_CP1500.ppd -E
```

Testez :

```
cd /var/www/html/Photomaton && ./test_installation.sh
```
