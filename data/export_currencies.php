<?php
// data/export_currencies.php
include '../admin/includes/config.php';

// Set headers to prevent caching
header('Content-Type: text/html; charset=utf-8');

// Get export parameters
$export_format = $_POST['export_format'] ?? 'csv';
$selected_ids = $_POST['selected_ids'] ?? [];
$export_all = isset($_POST['export_all']) && $_POST['export_all'] == '1';
$search = $_POST['search'] ?? '';

// Build query based on request type
if ($export_all) {
    // Export ALL records (with search filter if provided)
    if (!empty($search)) {
        $sql = "SELECT country, currency_code, exchange_rate, effective_date 
                FROM currencies 
                WHERE country LIKE ? OR currency_code LIKE ? 
                ORDER BY effective_date DESC, country ASC";
        $stmt = $con->prepare($sql);
        $search_param = "%$search%";
        $stmt->bind_param('ss', $search_param, $search_param);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $sql = "SELECT country, currency_code, exchange_rate, effective_date 
                FROM currencies 
                ORDER BY effective_date DESC, country ASC";
        $result = $con->query($sql);
    }
} elseif (!empty($selected_ids)) {
    // Export SELECTED records only
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
    // No selection and not exporting all - show error or export all with warning?
    // For backward compatibility, we'll export all
    $export_all = true;
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

// Determine filename based on export type
if ($export_all) {
    $filename = 'all_currency_rates_' . date('Y-m-d');
    if (!empty($search)) {
        $filename .= '_filtered';
    }
} else {
    $filename = 'selected_currency_rates_' . date('Y-m-d');
}

if ($export_format === 'csv') {
    exportToCSV($currency_rates, $filename);
} elseif ($export_format === 'pdf') {
    exportToPDF($currency_rates, $filename);
}

function exportToCSV($data, $filename = 'currency_rates') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
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

function exportToPDF($data, $filename = 'currency_rates') {
    require_once('../admin/includes/tcpdf/tcpdf.php');
    
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetCreator('Your System');
    $pdf->SetAuthor('Your System');
    
    // Set title based on export type
    if (strpos($filename, 'all_') === 0) {
        $title = 'All Currency Exchange Rates';
        if (strpos($filename, '_filtered') !== false) {
            $title .= ' (Filtered)';
        }
    } else {
        $title = 'Selected Currency Exchange Rates';
    }
    
    $pdf->SetTitle($title);
    $pdf->SetSubject('Currency Rates Export');
    
    $pdf->AddPage();
    
    // Title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, $title, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    
    // Add record count
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Total Records: ' . count($data), 0, 1, 'C');
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
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    $pdf->Output($filename . '.pdf', 'D');
    exit;
}

// Add a fallback function in case no data is found
function noDataExport($export_format, $filename) {
    if ($export_format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
        fputcsv($output, ['No data found']);
        fclose($output);
        exit;
    } else {
        require_once('../admin/includes/tcpdf/tcpdf.php');
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'No Data Found', 0, 1, 'C');
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
        $pdf->Output($filename . '.pdf', 'D');
        exit;
    }
}

// Check if we have data, if not, export empty file
if (empty($currency_rates)) {
    noDataExport($export_format, $filename);
}
?>