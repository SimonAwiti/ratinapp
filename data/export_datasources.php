<?php
// export_datasources.php
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
        'name' => isset($_GET['filter_name']) ? $_GET['filter_name'] : '',
        'country' => isset($_GET['filter_country']) ? $_GET['filter_country'] : '',
        'date' => isset($_GET['filter_date']) ? $_GET['filter_date'] : ''
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

// Function to get data sources data
function getDataSourcesDataForExport($con, $selected_ids = [], $export_all = false, $filters = [], $apply_filters = false) {
    $where_clauses = [];
    $params = [];
    $types = '';
    
    // Only apply filters if requested
    if ($apply_filters) {
        if (!empty($filters['name'])) {
            $where_clauses[] = "data_source_name LIKE ?";
            $params[] = '%' . $filters['name'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['country'])) {
            $where_clauses[] = "countries_covered LIKE ?";
            $params[] = '%' . $filters['country'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['date'])) {
            $where_clauses[] = "DATE(created_at) = ?";
            $params[] = $filters['date'];
            $types .= 's';
        }
    }
    
    // Add ID filter if not exporting all and IDs are provided
    if (!$export_all && !empty($selected_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $where_clauses[] = "id IN ($placeholders)";
        $params = array_merge($params, $selected_ids);
        $types .= str_repeat('i', count($selected_ids));
    }
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    $sql = "SELECT 
                id, 
                data_source_name, 
                countries_covered,
                DATE_FORMAT(created_at, '%Y-%m-%d') as created_date
            FROM 
                data_sources
            $where_sql
            ORDER BY 
                data_source_name ASC";
    
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
    $data = getDataSourcesDataForExport($con, $selected_ids, $export_all, $filters, $apply_filters);
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="data_sources_'.date('Y-m-d_H-i-s').'.xls"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: public');
    
    // Output BOM for UTF-8
    echo "\xEF\xBB\xBF";
    
    // Excel header row
    echo "ID\tData Source Name\tCountries Covered\tDate Added\n";
    
    // Output data
    foreach ($data as $row) {
        echo $row['id'] . "\t";
        echo str_replace(["\t", "\n", "\r"], " ", $row['data_source_name']) . "\t";
        echo str_replace(["\t", "\n", "\r"], " ", $row['countries_covered']) . "\t";
        echo $row['created_date'] . "\n";
    }
    
    exit;
}

function exportToCSV($con, $selected_ids = [], $export_all = false, $filters = [], $apply_filters = false) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Get data
    $data = getDataSourcesDataForExport($con, $selected_ids, $export_all, $filters, $apply_filters);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="data_sources_'.date('Y-m-d_H-i-s').'.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: public');
    
    // Output BOM for UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Output headers
    fputcsv($output, ['ID', 'Data Source Name', 'Countries Covered', 'Date Added']);
    
    // Output data
    foreach ($data as $row) {
        fputcsv($output, [
            $row['id'],
            $row['data_source_name'],
            $row['countries_covered'],
            $row['created_date']
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
    $data = getDataSourcesDataForExport($con, $selected_ids, $export_all, $filters, $apply_filters);
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Data Sources System');
    $pdf->SetAuthor('Data Sources System');
    $pdf->SetTitle('Data Sources Export');
    $pdf->SetSubject('Data Sources Data');
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Add title
    $pdf->Cell(0, 10, 'Data Sources Export - ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Ln(5);
    
    if (empty($data)) {
        $pdf->Cell(0, 10, 'No data to export.', 0, 1, 'C');
        $pdf->Output('data_sources_'.date('Y-m-d_H-i-s').'.pdf', 'D');
        exit;
    }
    
    // Create table header
    $header = ['ID', 'Data Source Name', 'Countries Covered', 'Date Added'];
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('helvetica', 'B', 9);
    
    // Column widths
    $col_widths = [15, 60, 80, 35];
    
    foreach ($header as $key => $col) {
        $pdf->Cell($col_widths[$key], 7, $col, 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Add data
    $pdf->SetFont('helvetica', '', 8);
    foreach ($data as $row) {
        $pdf->Cell($col_widths[0], 6, $row['id'], 1);
        $pdf->Cell($col_widths[1], 6, substr($row['data_source_name'], 0, 30), 1);
        $pdf->Cell($col_widths[2], 6, substr($row['countries_covered'], 0, 40), 1);
        $pdf->Cell($col_widths[3], 6, $row['created_date'], 1);
        $pdf->Ln();
    }
    
    // Add summary
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 10, 'Total Records Exported: ' . count($data), 0, 1, 'L');
    
    // Output PDF
    $pdf->Output('data_sources_'.date('Y-m-d_H-i-s').'.pdf', 'D');
    exit;
}

// If no export parameter, redirect back
header("Location: datasources_boilerplate.php");
exit;
?>