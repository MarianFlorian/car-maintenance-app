<?php
// pages/parse_receipt.php

ini_set('display_errors', 0);
header('Content-Type: application/json');

// 1) Autoload + dotenv + DB
require __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require __DIR__ . '/../includes/db.php';  // $conn = mysqli connection

// 2) API key
$apiKey = $_ENV['OPENAI_API_KEY'] ?? '';
if (!$apiKey) {
    http_response_code(500);
    exit(json_encode(['success'=>false,'error'=>'OPENAI_API_KEY nu este setată']));
}

// 3) Citește OCR text
$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['ocr_text'])) {
    http_response_code(400);
    exit(json_encode(['success'=>false,'error'=>'OCR text lipsește']));
}
$ocrText = $conn->real_escape_string($input['ocr_text']);

// 4) Încarcă ultimele 3 exemple din DB
$examples = [];
$res = $conn->query(
  "SELECT ocr_text, parsed_json 
     FROM parse_examples 
    ORDER BY id DESC 
    LIMIT 3"
);
while ($row = $res->fetch_assoc()) {
    $examples[] = [
      'ocr'  => $row['ocr_text'],
      'json' => $row['parsed_json']
    ];
}

// 5) Construieşte prompt-ul few-shot
$fewShot = "";
foreach (array_reverse($examples) as $ex) {
    $fewShot .= "OCR:\n" . $ex['ocr'] . "\nJSON:\n" . $ex['json'] . "\n\n";
}

// 6) Prompt sistem + exemple + instrucţiuni finale
$systemPrompt = <<<SYS
{$fewShot}
Acum primești textul OCR de bon.  
Returnează NUMAI un obiect JSON cu:
- date (YYYY-MM-DD)  
- time (HH:MM)  
- gas_station (ex. "PETROM 52 - Baia Mare")  
- fuel_type ("MOTORINA","BENZINĂ","GPL","ELECTRIC")  
- liters (număr cu 2 zecimale)  
- price_per_l (număr)  
- total_cost (număr)  
SYS;

// 7) Apel API
$openai = \OpenAI::client($apiKey);
$resp = $openai->chat()->create([
    'model'       => 'gpt-3.5-turbo',
    'messages'    => [
        ['role'=>'system','content'=>$systemPrompt],
        ['role'=>'user',  'content'=>$ocrText]
    ],
    'max_tokens'  => 512,
    'temperature' => 0.0,
]);

$raw = $resp->choices[0]->message->content;

// 8) Extrage JSON
if (!preg_match('/\{[\s\S]*\}/', $raw, $m)) {
    throw new \Exception("JSON nu a fost găsit:\n$raw");
}
$data = json_decode($m[0], true);
if (!is_array($data)) {
    throw new \Exception("JSON invalid:\n".$m[0]);
}

// 9) Normalizează câmpuri
$data['time']   = substr($data['time'],0,5);
$data['liters'] = number_format((float)$data['liters'],2,'.','');

// 10) Salvează în DB pentru viitoare few-shot
$stmt = $conn->prepare("
    INSERT INTO parse_examples (ocr_text, parsed_json)
    VALUES (?, ?)
");
$parsedJson = json_encode($data, JSON_UNESCAPED_UNICODE);
$stmt->bind_param("ss", $ocrText, $parsedJson);
$stmt->execute();
$stmt->close();

// 11) Returnează răspunsul
echo json_encode(['success'=>true,'data'=>$data]);
