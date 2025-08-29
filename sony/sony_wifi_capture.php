<?php
/**
 * Capture unique via API WiFi Sony (Camera Remote API, modèles compatibles / mode Smart Remote / Ctrl w/ Smartphone).
 * Réponse JSON: { file:"captures/xxx.jpg" } ou { error:"message" }
 * Nécessite que $CAPTURE_MODE = 'sony_wifi' et la caméra accessible sur $SONY_WIFI_IP
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

if($CAPTURE_MODE !== 'sony_wifi') { http_response_code(400); echo json_encode(['error'=>'Mode sony_wifi non actif']); exit; }

if(empty($SONY_WIFI_IP)) { http_response_code(500); echo json_encode(['error'=>'IP caméra Sony non configurée']); exit; }

$base = 'http://'.$SONY_WIFI_IP.'/sony/camera';

function sony_call($url, $method, $params = [], $id = 1, $version = '1.0', $timeout = 5){
    $payload = json_encode([
        'method'=>$method,
        'params'=>$params,
        'id'=>$id,
        'version'=>$version
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => $timeout
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if($resp === false) return ['curl_error'=>$err];
    $data = json_decode($resp, true);
    return ['status'=>$status,'raw'=>$resp,'data'=>$data];
}

// 1. startRecMode (certains modèles exigent)
$rec = sony_call($base,'startRecMode',[],1,$SONY_API_VERSION,$SONY_HTTP_TIMEOUT);
// On ignore les erreurs si déjà en rec

// 2. actTakePicture
$take = sony_call($base,'actTakePicture',[],2,$SONY_API_VERSION,$SONY_HTTP_TIMEOUT);
if(isset($take['curl_error'])){ http_response_code(504); echo json_encode(['error'=>'Timeout / connexion caméra','details'=>$take]); exit; }
if(empty($take['data']) || isset($take['data']['error'])){
    http_response_code(500);
    echo json_encode(['error'=>'Echec actTakePicture','details'=>$take['data'] ?? $take]);
    exit;
}

// Structure attendue: { "result": [ [ "http://...jpg" ], [thumb?], ... ] }
$url = null;
if(isset($take['data']['result'][0][0])) $url = $take['data']['result'][0][0];
elseif(isset($take['data']['result'][0])) $url = $take['data']['result'][0];

// Si pas d'URL directe, poll getEvent pour récupérer stillImageUri
if(!$url){
    $maxPolls = isset($SONY_WIFI_MAX_EVENT_POLLS)?(int)$SONY_WIFI_MAX_EVENT_POLLS:12;
    $intervalMs = isset($SONY_WIFI_EVENT_POLL_INTERVAL_MS)?(int)$SONY_WIFI_EVENT_POLL_INTERVAL_MS:300;
    for($i=0;$i<$maxPolls && !$url;$i++){
        usleep($intervalMs*1000);
        $ev = sony_call($base,'getEvent',[false],3,$SONY_API_VERSION,$SONY_HTTP_TIMEOUT);
        if(!empty($ev['data']['result'])){
            // Chercher une URL JPG
            $stack = $ev['data']['result'];
            $found = null;
            $iter = new RecursiveIteratorIterator(new RecursiveArrayIterator($stack));
            foreach($iter as $val){
                if(is_string($val) && stripos($val,'http')===0 && stripos($val,'.jpg')!==false){ $found=$val; break; }
            }
            if($found) $url = $found;
        }
    }
}

if(!$url){ http_response_code(500); echo json_encode(['error'=>'URL image non trouvée après polling','raw_take'=>$take['data']??null]); exit; }

// Télécharger l'image
$img = @file_get_contents($url);
if($img === false){ http_response_code(500); echo json_encode(['error'=>'Téléchargement image échoué','url'=>$url]); exit; }

$filename = 'capture_'.date('Ymd_His').'_wifi_'.bin2hex(random_bytes(2)).'.jpg';
$full = $CAPTURE_DIR.'/'.$filename;
file_put_contents($full, $img);

echo json_encode(['file'=>'captures/'.$filename,'source_url'=>$url]);
?>
