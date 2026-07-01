<?php
// tools/batch_translate.php
require_once '../../admin/includes/config.php';

$targets = ['sw','fr', 'am'];
$libretranslate_url = 'http://localhost:5000/translate';

$rows = $con->query("SELECT source_text FROM translations WHERE language_code='en' AND status='approved'");
$en_texts = [];
while ($r = $rows->fetch_assoc()) $en_texts[] = $r['source_text'];

foreach ($targets as $lang) {
    foreach (array_chunk($en_texts, 25) as $chunk) {
        $ch = curl_init($libretranslate_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['q'=>$chunk,'source'=>'en','target'=>$lang,'format'=>'text']));
        $resp = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $translated = $resp['translatedText'] ?? [];
        if (!is_array($translated)) $translated = [$translated];

        foreach ($chunk as $i => $text) {
            $tr = $translated[$i] ?? $text;
            $stmt = $con->prepare(
                "INSERT INTO translations (language_code, source_text, translation, status, source)
                 VALUES (?, ?, ?, 'pending', 'seed')
                 ON DUPLICATE KEY UPDATE translation=VALUES(translation)"
            );
            $stmt->bind_param('sss', $lang, $text, $tr);
            $stmt->execute();
        }
        echo "Translated chunk for $lang\n";
        sleep(1);
    }
}
echo "Done. Rows inserted as 'pending' — review in admin before approving/rebuilding cache.\n";