<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impression rapide - Canon SELPHY</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            color: white;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            text-align: center;
        }
        .print-box {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 30px;
            margin: 20px 0;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn {
            background: #4CAF50;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 18px;
            margin: 10px;
            display: inline-block;
            text-decoration: none;
        }
        .btn:hover {
            background: #45a049;
        }
        .file-input {
            margin: 20px 0;
            padding: 10px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 6px;
            color: #333;
        }
        .copies-input {
            padding: 10px;
            font-size: 16px;
            border-radius: 6px;
            border: 1px solid #ddd;
            margin: 0 10px;
            width: 60px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🖨️ Impression Canon SELPHY CP1500</h1>
        <p>Solution d'impression fiable pour vos photos</p>

        <div class="print-box">
            <h2>Imprimer une photo</h2>
            <input type="file" id="imageFile" accept="image/*" class="file-input">
            <br>
            <label>Nombre de copies : </label>
            <input type="number" id="copies" value="2" min="1" max="10" class="copies-input">
            <br><br>
            <button class="btn" onclick="printImage()">🖨️ Imprimer maintenant</button>
        </div>

        <div class="print-box">
            <h2>Photos récentes</h2>
            <p>Cliquez sur une photo pour l'imprimer :</p>
            <div id="recent-photos">Chargement...</div>
        </div>

        <div class="print-box">
            <h2>Impression rapide par nom</h2>
            <input type="text" id="imageName" placeholder="Ex: DSC_0081.JPG" style="padding: 10px; margin: 10px; width: 200px;">
            <input type="number" id="quickCopies" value="1" min="1" max="10" class="copies-input">
            <br>
            <button class="btn" onclick="printByName()">⚡ Impression rapide</button>
        </div>
    </div>

    <script>
        // Charger les photos récentes
        document.addEventListener('DOMContentLoaded', function() {
            loadRecentPhotos();
        });

        async function loadRecentPhotos() {
            try {
                const response = await fetch('src/get_recent_photos.php');
                const photos = await response.json();
                
                const container = document.getElementById('recent-photos');
                if (photos.length > 0) {
                    container.innerHTML = photos.map(photo => 
                        `<div style="display: inline-block; margin: 10px; cursor: pointer;" onclick="printPhoto('${photo}')">
                            <img src="${photo}" style="width: 100px; height: 75px; object-fit: cover; border-radius: 6px;">
                            <br><small>${photo.split('/').pop()}</small>
                        </div>`
                    ).join('');
                } else {
                    container.innerHTML = '<p>Aucune photo récente trouvée</p>';
                }
            } catch (error) {
                console.error('Erreur:', error);
                document.getElementById('recent-photos').innerHTML = '<p>Erreur lors du chargement</p>';
            }
        }

        function printPhoto(photoPath) {
            const copies = prompt('Nombre de copies ?', '2');
            if (copies && !isNaN(copies)) {
                openPrintWindow(photoPath, parseInt(copies));
            }
        }

        function printImage() {
            const fileInput = document.getElementById('imageFile');
            const copies = document.getElementById('copies').value;
            
            if (!fileInput.files[0]) {
                alert('Veuillez sélectionner une image');
                return;
            }
            
            // Ici vous pourriez upload l'image puis l'imprimer
            // Pour simplifier, on demande le chemin
            const imagePath = prompt('Chemin de l\'image (ex: captures/photo.jpg):', '');
            if (imagePath) {
                openPrintWindow(imagePath, parseInt(copies));
            }
        }

        function printByName() {
            const imageName = document.getElementById('imageName').value;
            const copies = document.getElementById('quickCopies').value;
            
            if (!imageName) {
                alert('Veuillez entrer un nom de fichier');
                return;
            }
            
            // Essayer différents dossiers
            const possiblePaths = [
                `captures/${imageName}`,
                `src/images/${imageName}`,
                imageName
            ];
            
            // Utiliser le premier chemin par défaut
            openPrintWindow(possiblePaths[0], parseInt(copies));
        }

        function openPrintWindow(imagePath, copies) {
            const printUrl = `print_page.html?` + new URLSearchParams({
                image: imagePath,
                copies: copies,
                format: 'Postcard.Fullbleed',
                auto: '1'
            });
            
            console.log('Ouverture de:', printUrl);
            
            const printWindow = window.open(printUrl, 'printWindow', 'width=800,height=600');
            if (!printWindow) {
                alert('Impossible d\'ouvrir la fenêtre d\'impression. Vérifiez les bloqueurs de popup.');
            }
        }
    </script>
</body>
</html>
