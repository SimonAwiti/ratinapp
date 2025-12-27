<?php
// export_miller_prices.php
session_start();
include '../admin/includes/config.php';

// Handle Export functionality
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $selected_ids = isset($_GET['ids']) ? json_decode($_GET['ids']) : [];
    $export_all = isset($_GET['export_all']) ? $_GET['export_all'] : false;
    $apply_filters = isset($_GET['apply_filters']) ? $_GET['apply_filters'] : false;
    
    // Get filter parameters if provided
    $filters = [
        'country' => isset($_GET['filter_country']) ? $_GET['filter_country'] : '',
        'town' => isset($_GET['filter_town']) ? $_GET['filter_town'] : '',
        'commodity' => isset($_GET['filter_commodity']) ? $_GET['filter_commodity'] : '',
        'date' => isset($_GET['filter_date']) ? $_GET['filter_date'] : '',
        'status' => isset($_GET['filter_status']) ? $_GET['filter_status'] : '',
        'data_source' => isset($_GET['filter_data_source']) ? $_GET['filter_data_source'] : ''
    ];
    
    if ($export_type === 'excel') {
        exportToExcel($con, $selected_ids, $export_all, $filters, $apply_filters);
    } elseif ($export_type === 'pdf') {
        exportToPDF($con, $selected_ids, $export_all, $filters, $apply_filters);
    } elseif ($export_type === 'csv') {
        exportToCSV($con, $selected_ids, $export_all, $filters, $apply_filters);
    }
    exit;
}

// Function to get miller prices data
function getMillerPricesDataForExport($con, $selected_ids = [], $export_all = false, $filters = [], $apply_filters = false) {
    $where_clauses = [];
    $params = [];
    $types = '';
    
    // Only apply filters if requested
    if ($apply_filters) {
        if (!empty($filters['country'])) {
            $where_clauses[] = "mp.country LIKE ?";
            $params[] = '%' . $filters['country'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['town'])) {
            $where_clauses[] = "mp.town LIKE ?";
            $params[] = '%' . $filters['town'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['commodity'])) {
            $where_clauses[] = "(c.commodity_name LIKE ? OR c.variety LIKE ?)";
            $params[] = '%' . $filters['commodity'] . '%';
            $params[] = '%' . $filters['commodity'] . '%';
            $types .= 'ss';
        }
        
        if (!empty($filters['date'])) {
            $where_clauses[] = "DATE(mp.date_posted) LIKE ?";
            $params[] = '%' . $filters['date'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['status'])) {
            $where_clauses[] = "mp.status LIKE ?";
            $params[] = '%' . $filters['status'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['data_source'])) {
            $where_clauses[] = "ds.data_source_name LIKE ?";
            $params[] = '%' . $filters['data_source'] . '%';
            $types .= 's';
        }
    }
    
    // Add ID filter if not exporting all and IDs are provided
    if (!$export_all && !empty($selected_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $where_clauses[] = "mp.id IN ($placeholders)";
        $params = array_merge($params, $selected_ids);
        $types .= str_repeat('i', count($selected_ids));
    }
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    $sql = "SELECT
                mp.id,
                mp.country,
                mp.town,
                c.commodity_name,
                c.variety,
                CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
                mp.price_usd,
                mp.day_change,
                mp.month_change,
                DATE(mp.date_posted) as date_posted,
                mp.status,
                ds.data_source_name AS data_source
            FROM
                miller_prices mp
            LEFT JOIN
                commodities c ON mp.commodity_id = c.id
            LEFT JOIN
                data_sources ds ON mp.data_source_id = ds.id
            $where_sql
            ORDER BY
                mp.date_posted DESC";
    
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        error_log("Error preparing statement: " . $con->error);
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    $stmt->close();
    
    return $data;
}

function exportToExcel($con, $selected_ids = [], $export_all = false, $filters = [], $apply_filters = false) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Get data
    $data = getMillerPricesDataForExport($con, $selected_ids, $export_all, $filters, $apply_filters);
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="miller_prices_'.date('Y-m-d_H-i-s').'.xls"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: public');
    
    // Output BOM for UTF-8
    echo "\xEF\xBB\xBF";
    
    // Excel header row
    echo "ID\tCountry\tTown\tCommodity\tPrice (USD)\tDay Change %\tMonth Change %\tDate\tStatus\tData Source\n";
    
    // Output data
    foreach ($data as $row) {
        echo $row['id'] . "\t";
        echo str_replace(["\t", "\n", "\r"], " ", $row['country']) . "\t";
        echo str_replace(["\t", "\n", "\r"], " ", $row['town']) . "\t";
        echo str_replace(["\t", "\n", "\r"], " ", $row['commodity_display']) . "\t";
        echo $row['price_usd'] . "\t";
        echo ($row['day_change'] !== null ? $row['day_change'] . '%' : 'N/A') . "\t";
        echo ($row['month_change'] !== null ? $row['month_change'] . '%' : 'N/A') . "\t";
        echo $row['date_posted'] . "\t";
        echo $row['status'] . "\t";
        echo str_replace(["\t", "\n", "\r"], " ", $row['data_source']) . "\n";
    }
    
    exit;
}

function exportToCSV($con, $selected_ids = [], $export_all = false, $filters = [], $apply_filters = false) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Get data
    $data = getMillerPricesDataForExport($con, $selected_ids, $export_all, $filters, $apply_filters);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="miller_prices_'.date('Y-m-d_H-i-s').'.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: public');
    
    // Output BOM for UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Output headers
    fputcsv($output, ['ID', 'Country', 'Town', 'Commodity', 'Price (USD)', 'Day Change %', 'Month Change %', 'Date', 'Status', 'Data Source']);
    
    // Output data
    foreach ($data as $row) {
        fputcsv($output, [
            $row['id'],
            $row['country'],
            $row['town'],
            $row['commodity_display'],
            $row['price_usd'],
            $row['day_change'] !== null ? $row['day_change'] . '%' : 'N/A',
            $row['month_change'] !== null ? $row['month_change'] . '%' : 'N/A',
            $row['date_posted'],
            $row['status'],
            $row['data_source']
        ]);
    }
    
    fclose($output);
    exit;
}

function exportToPDF($con, $selected_ids = [], $export_all = false, $filters = [], $apply_filters = false) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    require_once('tcpdf/tcpdf.php');
    
    // Get data
    $data = getMillerPricesDataForExport($con, $selected_ids, $export_all, $filters, $apply_filters);
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Miller Prices System');
    $pdf->SetAuthor('Miller Prices System');
    $pdf->SetTitle('Miller Prices Export');
    $pdf->SetSubject('Miller Prices Data');
    
    // Set margins
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage('L'); // Landscape orientation for better table display
    
    // Set font
    $pdf->SetFont('helvetica', '', 8);
    
    // Add title
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Miller Prices Export', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    
    if ($export_all) {
        $pdf->Cell(0, 10, 'Export Type: All Data' . ($apply_filters ? ' (with filters applied)' : ''), 0, 1, 'C');
    } else {
        $pdf->Cell(0, 10, 'Export Type: Selected Items', 0, 1, 'C');
    }
    
    $pdf->Ln(5);
    
    if (empty($data)) {
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'No data to export.', 0, 1, 'C');
        $pdf->Output('miller_prices_'.date('Y-m-d_H-i-s').'.pdf', 'D');
        exit;
    }
    
    // Create table with display format
    $pdf->SetFont('helvetica', '', 7);
    
    // Table headers
    $headers = ['Country', 'Town', 'Commodity', 'Price (USD)', 'Day Change %', 'Month Change %', 'Date', 'Status', 'Data Source'];
    
    // Column widths
    $col_widths = [15, 20, 30, 15, 15, 15, 15, 15, 20];
    
    // Header row
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('helvetica', 'B', 7);
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($col_widths[$i], 6, $headers[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Data rows
    $pdf->SetFont('helvetica', '', 7);
    $fill = false;
    
    foreach ($data as $row) {
        // Alternate row color
        if ($fill) {
            $pdf->SetFillColor(245, 245, 245);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        // Country
        $pdf->Cell($col_widths[0], 6, substr($row['country'], 0, 12), 1, 0, 'L', $fill);
        
        // Town
        $pdf->Cell($col_widths[1], 6, substr($row['town'], 0, 15), 1, 0, 'L', $fill);
        
        // Commodity
        $pdf->Cell($col_widths[2], 6, substr($row['commodity_display'], 0, 20), 1, 0, 'L', $fill);
        
        // Price USD
        $pdf->Cell($col_widths[3], 6, $row['price_usd'], 1, 0, 'R', $fill);
        
        // Day Change
        $day_change = $row['day_change'] !== null ? $row['day_change'] . '%' : 'N/A';
        $pdf->Cell($col_widths[4], 6, $day_change, 1, 0, 'C', $fill);
        
        // Month Change
        $month_change = $row['month_change'] !== null ? $row['month_change'] . '%' : 'N/A';
        $pdf->Cell($col_widths[5], 6, $month_change, 1, 0, 'C', $fill);
        
        // Date
        $pdf->Cell($col_widths[6], 6, $row['date_posted'], 1, 0, 'C', $fill);
        
        // Status
        $pdf->Cell($col_widths[7], 6, $row['status'], 1, 0, 'C', $fill);
        
        // Data Source
        $pdf->Cell($col_widths[8], 6, substr($row['data_source'], 0, 15), 1, 0, 'L', $fill);
        
        $pdf->Ln();
        $fill = !$fill;
    }
    
    // Add summary at the end
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 10, 'Total Records Exported: ' . count($data), 0, 1, 'L');
    
    // Output PDF
    $pdf->Output('miller_prices_'.date('Y-m-d_H-i-s').'.pdf', 'D');
    exit;
}

// If no export parameter, redirect back
header("Location: miller_price_boilerplate.php");
exit;
?>