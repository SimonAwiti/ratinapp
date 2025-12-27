<?php
// export_market_prices.php
session_start();
// Include the configuration file first
include '../admin/includes/config.php';

// Include calculation functions
include 'price_calculations.php'; // You may need to create this or include from existing

// Handle Export functionality
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $selected_ids = isset($_GET['ids']) ? json_decode($_GET['ids']) : [];
    $export_all = isset($_GET['export_all']) ? $_GET['export_all'] : false;
    $apply_filters = isset($_GET['apply_filters']) ? $_GET['apply_filters'] : false;
    
    // Get filter parameters if provided
    $filters = [
        'market' => isset($_GET['filter_market']) ? $_GET['filter_market'] : '',
        'commodity' => isset($_GET['filter_commodity']) ? $_GET['filter_commodity'] : '',
        'date' => isset($_GET['filter_date']) ? $_GET['filter_date'] : '',
        'type' => isset($_GET['filter_type']) ? $_GET['filter_type'] : '',
        'status' => isset($_GET['filter_status']) ? $_GET['filter_status'] : '',
        'source' => isset($_GET['filter_source']) ? $_GET['filter_source'] : ''
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

// Function to get all price data with calculations like display
function getAllPriceDataWithCalculations($con, $selected_ids = [], $export_all = false, $filters = [], $apply_filters = false) {
    $where_clauses = [];
    $params = [];
    $types = '';
    
    // Only apply filters if requested
    if ($apply_filters) {
        if (!empty($filters['market'])) {
            $where_clauses[] = "p.market LIKE ?";
            $params[] = '%' . $filters['market'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['commodity'])) {
            $where_clauses[] = "(c.commodity_name LIKE ? OR c.variety LIKE ?)";
            $params[] = '%' . $filters['commodity'] . '%';
            $params[] = '%' . $filters['commodity'] . '%';
            $types .= 'ss';
        }
        
        if (!empty($filters['date'])) {
            $where_clauses[] = "DATE(p.date_posted) LIKE ?";
            $params[] = '%' . $filters['date'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['type'])) {
            $where_clauses[] = "p.price_type LIKE ?";
            $params[] = '%' . $filters['type'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['status'])) {
            $where_clauses[] = "p.status LIKE ?";
            $params[] = '%' . $filters['status'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['source'])) {
            $where_clauses[] = "p.data_source LIKE ?";
            $params[] = '%' . $filters['source'] . '%';
            $types .= 's';
        }
    }
    
    // Add ID filter if not exporting all and IDs are provided
    if (!$export_all && !empty($selected_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $where_clauses[] = "p.id IN ($placeholders)";
        $params = array_merge($params, $selected_ids);
        $types .= str_repeat('i', count($selected_ids));
    }
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    // Get data with all fields needed for display calculations
    $sql = "SELECT
                p.id,
                p.market,
                p.commodity,
                c.commodity_name,
                c.variety,
                CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
                p.price_type,
                p.Price,
                p.date_posted,
                p.status,
                p.data_source,
                p.variety as price_variety,
                p.weight,
                p.unit,
                p.country_admin_0,
                p.supplied_volume,
                p.comments,
                p.supply_status,
                p.category
            FROM market_prices p
            LEFT JOIN commodities c ON p.commodity = c.id
            $where_sql
            ORDER BY p.date_posted DESC, p.market, p.commodity, p.price_type";
    
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        error_log("Error preparing statement: " . $con->error);
        return ['data' => [], 'grouped' => []];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $all_data = [];
    $grouped_data = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $all_data[] = $row;
            
            // Group data like in display (by date, market, commodity)
            $date = date('Y-m-d', strtotime($row['date_posted']));
            $group_key = $date . '_' . $row['market'] . '_' . $row['commodity'];
            $grouped_data[$group_key][] = $row;
        }
    }
    $stmt->close();
    
    return ['data' => $all_data, 'grouped' => $grouped_data];
}

// Calculate DoD change
function calculateDoDChangeExport($currentPrice, $commodityId, $market, $priceType, $currentDate, $con) {
    $sql = "SELECT Price FROM market_prices
            WHERE commodity = ? 
            AND market = ?
            AND price_type = ?
            AND DATE(date_posted) < DATE(?)
            ORDER BY date_posted DESC
            LIMIT 1";

    $stmt = $con->prepare($sql);
    if (!$stmt) return 'N/A';
    
    $stmt->bind_param('isss', $commodityId, $market, $priceType, $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $previousData = $result->fetch_assoc();
        $previousPrice = $previousData['Price'];
        if ($previousPrice != 0) {
            $change = (($currentPrice - $previousPrice) / $previousPrice) * 100;
            $stmt->close();
            return round($change, 2) . '%';
        }
    }
    $stmt->close();
    return 'N/A';
}

// Calculate MoM change
function calculateMoMChangeExport($currentPrice, $commodityId, $market, $priceType, $currentDate, $con) {
    $thirtyDaysAgo = date('Y-m-d', strtotime($currentDate . ' -30 days'));
    
    $sql = "SELECT Price, ABS(DATEDIFF(DATE(date_posted), ?)) as date_diff 
            FROM market_prices
            WHERE commodity = ?
            AND market = ?
            AND price_type = ?
            AND DATE(date_posted) BETWEEN DATE_SUB(?, INTERVAL 35 DAY) AND DATE_SUB(?, INTERVAL 25 DAY)
            ORDER BY date_diff ASC
            LIMIT 1";

    $stmt = $con->prepare($sql);
    if (!$stmt) return 'N/A';
    
    $stmt->bind_param('sissss', $thirtyDaysAgo, $commodityId, $market, $priceType, $thirtyDaysAgo, $thirtyDaysAgo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $monthAgoData = $result->fetch_assoc();
        $monthAgoPrice = $monthAgoData['Price'];
        if ($monthAgoPrice != 0) {
            $change = (($currentPrice - $monthAgoPrice) / $monthAgoPrice) * 100;
            $stmt->close();
            return round($change, 2) . '%';
        }
    }
    $stmt->close();
    return 'N/A';
}

// Function to prepare export data in display format
function prepareDisplayFormatData($grouped_data, $con) {
    $export_rows = [];
    
    foreach ($grouped_data as $group_key => $prices_in_group) {
        $first_row = true;
        $market = '';
        $commodity_display = '';
        $date_display = '';
        
        foreach ($prices_in_group as $price) {
            // Calculate changes like in display
            $day_change = calculateDoDChangeExport($price['Price'], $price['commodity'], $price['market'], $price['price_type'], $price['date_posted'], $con);
            $month_change = calculateMoMChangeExport($price['Price'], $price['commodity'], $price['market'], $price['price_type'], $price['date_posted'], $con);
            
            // Get display values for first row
            if ($first_row) {
                $market = $price['market'];
                $commodity_display = $price['commodity_display'];
                $date_display = date('Y-m-d', strtotime($price['date_posted']));
            }
            
            // Prepare row like in table display
            $row = [
                'Market' => $first_row ? $market : '',
                'Commodity' => $first_row ? $commodity_display : '',
                'Date' => $first_row ? $date_display : '',
                'Price Type' => $price['price_type'],
                'Price ($)' => number_format($price['Price'], 2),
                'Day Change (%)' => $day_change,
                'Month Change (%)' => $month_change,
                'Status' => $price['status'],
                'Data Source' => $price['data_source'],
                'Variety' => $price['price_variety'] ?: '',
                'Weight' => $price['weight'] ?: '',
                'Unit' => $price['unit'] ?: '',
                'Country' => $price['country_admin_0'] ?: '',
                'Volume' => $price['supplied_volume'] ?: '',
                'Comments' => $price['comments'] ?: '',
                'Supply Status' => $price['supply_status'] ?: '',
                'Category' => $price['category'] ?: ''
            ];
            
            $export_rows[] = $row;
            $first_row = false;
        }
    }
    
    return $export_rows;
}

function exportToExcel($con, $selected_ids = [], $export_all = false, $filters = [], $apply_filters = false) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Get data in display format
    $data_result = getAllPriceDataWithCalculations($con, $selected_ids, $export_all, $filters, $apply_filters);
    $export_data = prepareDisplayFormatData($data_result['grouped'], $con);
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="market_prices_display_format_'.date('Y-m-d_H-i-s').'.xls"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: public');
    
    // Output BOM for UTF-8
    echo "\xEF\xBB\xBF";
    
    // Excel header row
    if (!empty($export_data)) {
        // Output headers
        $headers = array_keys($export_data[0]);
        echo implode("\t", $headers) . "\n";
        
        // Output data
        foreach ($export_data as $row) {
            // Clean and format each cell
            $clean_row = array_map(function($value) {
                // Remove tabs, newlines, and extra spaces
                $clean = preg_replace('/\s+/', ' ', $value);
                $clean = str_replace(["\t", "\n", "\r"], ' ', $clean);
                // Excel needs quotes around values with tabs or commas
                if (strpos($clean, "\t") !== false || strpos($clean, ',') !== false) {
                    $clean = '"' . str_replace('"', '""', $clean) . '"';
                }
                return $clean;
            }, $row);
            
            echo implode("\t", $clean_row) . "\n";
        }
    } else {
        echo "No data to export.\n";
    }
    
    exit;
}

function exportToCSV($con, $selected_ids = [], $export_all = false, $filters = [], $apply_filters = false) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Get data in display format
    $data_result = getAllPriceDataWithCalculations($con, $selected_ids, $export_all, $filters, $apply_filters);
    $export_data = prepareDisplayFormatData($data_result['grouped'], $con);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="market_prices_display_format_'.date('Y-m-d_H-i-s').'.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: public');
    
    // Output BOM for UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Output headers
    if (!empty($export_data)) {
        fputcsv($output, array_keys($export_data[0]));
        
        // Output data
        foreach ($export_data as $row) {
            fputcsv($output, $row);
        }
    } else {
        fputcsv($output, ['No data to export']);
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
    
    // Get data in display format
    $data_result = getAllPriceDataWithCalculations($con, $selected_ids, $export_all, $filters, $apply_filters);
    $export_data = prepareDisplayFormatData($data_result['grouped'], $con);
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Market Prices System');
    $pdf->SetAuthor('Market Prices System');
    $pdf->SetTitle('Market Prices Export - Display Format');
    $pdf->SetSubject('Market Prices Data');
    
    // Set margins
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage('L'); // Landscape orientation for better table display
    
    // Set font
    $pdf->SetFont('helvetica', '', 8);
    
    // Add title
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Market Prices Export - Display Format', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    
    if ($export_all) {
        $pdf->Cell(0, 10, 'Export Type: All Data' . ($apply_filters ? ' (with filters applied)' : ''), 0, 1, 'C');
    } else {
        $pdf->Cell(0, 10, 'Export Type: Selected Items', 0, 1, 'C');
    }
    
    $pdf->Ln(5);
    
    if (empty($export_data)) {
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'No data to export.', 0, 1, 'C');
        $pdf->Output('market_prices_display_format_'.date('Y-m-d_H-i-s').'.pdf', 'D');
        exit;
    }
    
    // Create table with display format
    $pdf->SetFont('helvetica', '', 7);
    
    // Table headers - Display format like the web page
    $headers = ['Market', 'Commodity', 'Date', 'Type', 'Price ($)', 'Day Change (%)', 'Month Change (%)', 'Status', 'Source'];
    
    // Column widths
    $col_widths = [25, 35, 20, 15, 15, 18, 18, 20, 25];
    
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
    
    foreach ($export_data as $row) {
        // Alternate row color
        if ($fill) {
            $pdf->SetFillColor(245, 245, 245);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        // Market (only show for first row of group)
        $market = !empty($row['Market']) ? $row['Market'] : '';
        $pdf->Cell($col_widths[0], 6, substr($market, 0, 20), 1, 0, 'L', $fill);
        
        // Commodity (only show for first row of group)
        $commodity = !empty($row['Commodity']) ? $row['Commodity'] : '';
        $pdf->Cell($col_widths[1], 6, substr($commodity, 0, 25), 1, 0, 'L', $fill);
        
        // Date (only show for first row of group)
        $date = !empty($row['Date']) ? $row['Date'] : '';
        $pdf->Cell($col_widths[2], 6, $date, 1, 0, 'C', $fill);
        
        // Price Type
        $pdf->Cell($col_widths[3], 6, $row['Price Type'], 1, 0, 'C', $fill);
        
        // Price
        $pdf->Cell($col_widths[4], 6, $row['Price ($)'], 1, 0, 'R', $fill);
        
        // Day Change
        $day_change = $row['Day Change (%)'];
        $pdf->Cell($col_widths[5], 6, $day_change, 1, 0, 'C', $fill);
        
        // Month Change
        $month_change = $row['Month Change (%)'];
        $pdf->Cell($col_widths[6], 6, $month_change, 1, 0, 'C', $fill);
        
        // Status
        $status = $row['Status'];
        $pdf->Cell($col_widths[7], 6, $status, 1, 0, 'C', $fill);
        
        // Data Source
        $source = $row['Data Source'];
        $pdf->Cell($col_widths[8], 6, substr($source, 0, 20), 1, 0, 'L', $fill);
        
        $pdf->Ln();
        $fill = !$fill;
    }
    
    // Add summary at the end
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 10, 'Total Records Exported: ' . count($export_data), 0, 1, 'L');
    
    // Output PDF
    $pdf->Output('market_prices_display_format_'.date('Y-m-d_H-i-s').'.pdf', 'D');
    exit;
}

// If no export parameter, redirect back
header("Location: marketprices_boilerplate.php");
exit;
?>