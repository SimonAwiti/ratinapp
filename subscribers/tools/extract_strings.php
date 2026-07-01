<?php
// tools/extract_strings.php
// Usage: php extract_strings.php /path/to/your/php/pages/
// Read-only scan — does NOT modify any of your files.

$dir = $argv[1] ?? '.';
$files = glob(rtrim($dir, '/') . '/*.php');

$found = [];

foreach ($files as $file) {
    $html = file_get_contents($file);

    // Strip PHP blocks and script/style to avoid false positives
    $html = preg_replace('/<\?(php)?.*?\?>/is', '', $html);
    $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html);

    preg_match_all('/<(h1|h2|h3|h4|label|button|span|p|a|legend|caption|title)\b[^>]*>([^<>]{2,200})<\/\1>/i', $html, $m);

    foreach ($m[2] as $text) {
        $text = trim(html_entity_decode($text));
        if ($text === '') continue;
        if (!preg_match('/[A-Za-z]{3,}/', $text)) continue;
        if (preg_match('/^\$?[\d.,%\s\-+]+$/', $text)) continue;
        $found[$text] = true;
    }
}

$out = fopen('seed_translations.sql', 'w');
foreach (array_keys($found) as $text) {
    $esc = addslashes($text);
    fwrite($out, "INSERT INTO translations (language_code, source_text, translation, status, source) VALUES ('en', '$esc', '$esc', 'approved', 'seed') ON DUPLICATE KEY UPDATE translation=VALUES(translation);\n");
}
fclose($out);

echo "Found " . count($found) . " unique strings.\n";
echo "Review seed_translations.sql, then import it.\n";