<?php
// data/generate_currency_template.php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=currency_rates_template.csv');

$output = fopen('php://output', 'w');

// Simple header row - just like your market prices template
fputcsv($output, ['Country', 'Currency Code', 'Exchange Rate', 'Effective Date']);

// Clean sample data - no BOM, no extra columns
fputcsv($output, ['Kenya', 'KES', '128.24', date('Y-m-d')]);
fputcsv($output, ['Uganda', 'UGX', '3522.29', date('Y-m-d')]);
fputcsv($output, ['Tanzania', 'TZS', '2640.50', date('Y-m-d')]);
fputcsv($output, ['Rwanda', 'RWF', '1350.75', date('Y-m-d')]);
fputcsv($output, ['Burundi', 'BIF', '2850.25', date('Y-m-d')]);
fputcsv($output, ['Ethiopia', 'ETB', '56.45', date('Y-m-d')]);

fclose($output);
exit;
?>