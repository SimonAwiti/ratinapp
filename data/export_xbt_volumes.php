<?php
// export_xbt_volumes.php
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
        'border' => isset($_GET['filter_border']) ? $_GET['filter_border'] : '',
        'commodity' => isset($_GET['filter_commodity']) ? $_GET['filter_commodity'] : '',
        'source' => isset($_GET['filter_source']) ? $_GET['filter_source'] : '',
        'destination' => isset($_GET['filter_destination']) ? $_GET['filter_destination'] : '',
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

// Function to get XBT volumes data
function getXBTVolumesDataForExport($con, $selected_ids = [], $export_all = false, $filters = [], $apply_filters = false) {
    $where_clauses = [];
    $params = [];
    $types = '';
    
    // Only apply filters if requested
    if ($apply_filters) {
        if (!empty($filters['border'])) {
            $where_clauses[] = "b.name LIKE ?";
            $params[] = '%' . $filters['border'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['commodity'])) {
            $where_clauses[] = "(c.commodity_name LIKE ? OR c.variety LIKE ?)";
            $params[] = '%' . $filters['commodity'] . '%';
            $params[] = '%' . $filters['commodity'] . '%';
            $types .= 'ss';
        }
        
        if (!empty($filters['source'])) {
            $where_clauses[] = "x.source LIKE ?";
            $params[] = '%' . $filters['source'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['destination'])) {
            $where_clauses[] = "x.destination LIKE ?";
            $params[] = '%' . $filters['destination'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['date'])) {
            $where_clauses[] = "DATE(x.date_posted) LIKE ?";
            $params[] = '%' . $filters['date'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['status'])) {
            $where_clauses[] = "x.status LIKE ?";
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
        $where_clauses[] = "x.id IN ($placeholders)";
        $params = array_merge($params, $selected_ids);
        $types .= str_repeat('i', count($selected_ids));
    }
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    $sql = "SELECT
                x.id,
                b.name AS border_name,
                c.commodity_name,
                c.variety,
                CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
                x.volume,
                x.source,
                x.destination,
                DATE(x.date_posted) as date_posted,
                x.status,
                ds.data_source_name AS data_source
            FROM
                xbt_volumes x
            LEFT JOIN
                border_points b ON x.border_id = b.id
            LEFT JOIN
                commodities c ON x.commodity_id = c.id
            LEFT JOIN
                data_sources ds ON x.data_source_id = ds.id
            $where_sql
            ORDER BY
                x.date_posted DESC";
    
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
    $data = getXBTVolumesDataForExport($con, $selected_ids, $export_all, $filters, $apply_filters);
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="xbt_volumes_'.date('Y-m-d_H-i-s').'.xls"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: public');
    
    // Output BOM for UTF-8
    echo "\xEF\xBB\xBF";
    
    // Excel header row
    echo "ID\tBorder Point\tCommodity\tVolume (MT)\tSource\tDestination\tDate\tStatus\tData Source\n";
    
    // Output data
    foreach ($data as $row) {
        echo $row['id'] . "\t";
        echo str_replace(["\t", "\n", "\r"], " ", $row['border_name']) . "\t";
        echo str_replace(["\t", "\n", "\r"], " ", $row['commodity_display']) . "\t";
        echo $row['volume'] . "\t";
        echo str_replace(["\t", "\n", "\r"], " ", $row['source']) . "\t";
        echo str_replace(["\t", "\n", "\r"], " ", $row['destination']) . "\t";
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
    $data = getXBTVolumesDataForExport($con, $selected_ids, $export_all, $filters, $apply_filters);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="xbt_volumes_'.date('Y-m-d_H-i-s').'.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: public');
    
    // Output BOM for UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Output headers
    fputcsv($output, ['ID', 'Border Point', 'Commodity', 'Volume (MT)', 'Source', 'Destination', 'Date', 'Status', 'Data Source']);
    
    // Output data
    foreach ($data as $row) {
        fputcsv($output, [
            $row['id'],
            $row['border_name'],
            $row['commodity_display'],
            $row['volume'],
            $row['source'],
            $row['destination'],
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
    $data = getXBTVolumesDataForExport($con, $selected_ids, $export_all, $filters, $apply_filters);
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('XBT Volumes System');
    $pdf->SetAuthor('XBT Volumes System');
    $pdf->SetTitle('XBT Volumes Export');
    $pdf->SetSubject('XBT Volumes Data');
    
    // Set margins
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage('L'); // Landscape orientation for better table display
    
    // Set font
    $pdf->SetFont('helvetica', '', 8);
    
    // Add title
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'XBT Volumes Export', 0, 1, 'C');
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
        $pdf->Output('xbt_volumes_'.date('Y-m-d_H-i-s').'.pdf', 'D');
        exit;
    }
    
    // Create table with display format
    $pdf->SetFont('helvetica', '', 7);
    
    // Table headers
    $headers = ['Border Point', 'Commodity', 'Volume (MT)', 'Source', 'Destination', 'Date', 'Status', 'Data Source'];
    
    // Column widths
    $col_widths = [25, 35, 15, 20, 20, 15, 15, 25];
    
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
        
        // Border Point
        $pdf->Cell($col_widths[0], 6, substr($row['border_name'], 0, 20), 1, 0, 'L', $fill);
        
        // Commodity
        $pdf->Cell($col_widths[1], 6, substr($row['commodity_display'], 0, 25), 1, 0, 'L', $fill);
        
        // Volume
        $pdf->Cell($col_widths[2], 6, $row['volume'], 1, 0, 'R', $fill);
        
        // Source
        $pdf->Cell($col_widths[3], 6, substr($row['source'], 0, 15), 1, 0, 'L', $fill);
        
        // Destination
        $pdf->Cell($col_widths[4], 6, substr($row['destination'], 0, 15), 1, 0, 'L', $fill);
        
        // Date
        $pdf->Cell($col_widths[5], 6, $row['date_posted'], 1, 0, 'C', $fill);
        
        // Status
        $pdf->Cell($col_widths[6], 6, $row['status'], 1, 0, 'C', $fill);
        
        // Data Source
        $pdf->Cell($col_widths[7], 6, substr($row['data_source'], 0, 20), 1, 0, 'L', $fill);
        
        $pdf->Ln();
        $fill = !$fill;
    }
    
    // Add summary at the end
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 10, 'Total Records Exported: ' . count($data), 0, 1, 'L');
    
    // Output PDF
    $pdf->Output('xbt_volumes_'.date('Y-m-d_H-i-s').'.pdf', 'D');
    exit;
}

// If no export parameter, redirect back
header("Location: xbtvol_boilerplate.php");
exit;
?>