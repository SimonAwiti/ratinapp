<?php
// tools/import_pending_log.php — run via cron, e.g. daily
require_once '../../admin/includes/config.php';

$log_file = __DIR__ . '/../cache/pending_phrases.log';
if (!file_exists($log_file)) exit("No pending phrases.\n");

$lines = array_unique(array_filter(array_map('trim', file($log_file))));
$inserted = 0;

foreach ($lines as $text) {
    $stmt = $con->prepare(
        "INSERT IGNORE INTO translations (language_code, source_text, translation, status, source)
         VALUES ('en', ?, ?, 'approved', 'auto_detected')"
    );
    $stmt->bind_param('ss', $text, $text);
    if ($stmt->execute() && $stmt->affected_rows > 0) $inserted++;
}

// Clear the log now that it's processed
file_put_contents($log_file, '');

echo "Imported $inserted new English source phrases.\n";
echo "Run batch_translate.php to generate sw/fr/am translations for them, then review in admin.\n";