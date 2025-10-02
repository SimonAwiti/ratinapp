<?php
// data/export_currencies.php
include '../admin/includes/config.php';

// Set headers to prevent caching
header('Content-Type: text/html; charset=utf-8');

// Get export format and selected IDs
$export_format = $_POST['export_format'] ?? 'csv';
$selected_ids = $_POST['selected_ids'] ?? [];

// Build query based on selected IDs
if (!empty($selected_ids)) {
    $ids_placeholder = implode(',', array_fill(0, count($selected_ids), '?'));
    $sql = "SELECT country, currency_code, exchange_rate, effective_date 
            FROM currencies 
            WHERE id IN ($ids_placeholder) 
            ORDER BY effective_date DESC, country ASC";
    $stmt = $con->prepare($sql);
    
    // Bind parameters
    $types = str_repeat('i', count($selected_ids));
    $stmt->bind_param($types, ...$selected_ids);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Export all if no selection
    $sql = "SELECT country, currency_code, exchange_rate, effective_date 
            FROM currencies 
            ORDER BY effective_date DESC, country ASC";
    $result = $con->query($sql);
}

$currency_rates = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $currency_rates[] = $row;
    }
}

if ($export_format === 'csv') {
    exportToCSV($currency_rates);
} elseif ($export_format === 'pdf') {
    exportToPDF($currency_rates);
}

function exportToCSV($data) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=currency_rates_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    
    // Header row
    fputcsv($output, ['Country', 'Currency Code', 'Exchange Rate (to USD)', 'Effective Date']);
    
    // Data rows
    foreach ($data as $row) {
        fputcsv($output, [
            $row['country'],
            $row['currency_code'],
            number_format($row['exchange_rate'], 4),
            $row['effective_date']
        ]);
    }
    
    fclose($output);
    exit;
}

function exportToPDF($data) {
    require_once('../admin/includes/tcpdf/tcpdf.php');
    
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetCreator('Your System');
    $pdf->SetAuthor('Your System');
    $pdf->SetTitle('Currency Exchange Rates');
    $pdf->SetSubject('Currency Rates Export');
    
    $pdf->AddPage();
    
    // Title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Currency Exchange Rates', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(50, 8, 'Country', 1, 0, 'C', 1);
    $pdf->Cell(40, 8, 'Currency Code', 1, 0, 'C', 1);
    $pdf->Cell(50, 8, 'Exchange Rate', 1, 0, 'C', 1);
    $pdf->Cell(50, 8, 'Effective Date', 1, 1, 'C', 1);
    
    // Table data
    $pdf->SetFont('helvetica', '', 9);
    foreach ($data as $row) {
        $pdf->Cell(50, 8, $row['country'], 1);
        $pdf->Cell(40, 8, $row['currency_code'], 1);
        $pdf->Cell(50, 8, number_format($row['exchange_rate'], 4), 1);
        $pdf->Cell(50, 8, $row['effective_date'], 1, 1);
    }
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="currency_rates_' . date('Y-m-d') . '.pdf"');
    $pdf->Output('currency_rates_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}
?>