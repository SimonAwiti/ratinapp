<?php
// export_miller_prices.php

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include configuration
include '../admin/includes/config.php';

// Check if export request is valid
if (isset($_GET['export']) && isset($_GET['ids'])) {
    $export_type = $_GET['export'];
    $ids_json = $_GET['ids'];
    
    // Validate export type
    if (!in_array($export_type, ['excel', 'pdf'])) {
        die('Invalid export type');
    }
    
    // Parse IDs
    $ids = json_decode($ids_json, true);
    if (!is_array($ids) || empty($ids)) {
        die('No items selected for export');
    }
    
    // Sanitize IDs
    $sanitized_ids = array_map('intval', $ids);
    $id_list = implode(',', $sanitized_ids);
    
    // Fetch miller prices data
    $sql = "SELECT
                mp.id,
                mp.country,
                mp.town,
                c.commodity_name,
                c.variety,
                CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS commodity_display,
                mp.price,
                mp.price_usd,
                mp.day_change,
                mp.month_change,
                mp.date_posted,
                mp.status,
                ds.data_source_name AS data_source,
                mp.created_at
            FROM
                miller_prices mp
            LEFT JOIN
                commodities c ON mp.commodity_id = c.id
            LEFT JOIN
                data_sources ds ON mp.data_source_id = ds.id
            WHERE
                mp.id IN ($id_list)
            ORDER BY
                mp.date_posted DESC";

    $result = $con->query($sql);
    $miller_prices_data = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $miller_prices_data[] = $row;
        }
    }
    
    if (empty($miller_prices_data)) {
        die('No data found for export');
    }
    
    // Export based on type
    switch ($export_type) {
        case 'excel':
            exportToExcel($miller_prices_data);
            break;
        case 'pdf':
            exportToPDF($miller_prices_data);
            break;
    }
} else {
    die('Invalid export request');
}

function exportToExcel($data) {
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="miller_prices_export_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Start output
    echo "<table border='1'>";
    
    // Header row
    echo "<tr>";
    echo "<th>ID</th>";
    echo "<th>Country</th>";
    echo "<th>Town</th>";
    echo "<th>Commodity</th>";
    echo "<th>Price (Local)</th>";
    echo "<th>Price (USD)</th>";
    echo "<th>Day Change %</th>";
    echo "<th>Month Change %</th>";
    echo "<th>Date Posted</th>";
    echo "<th>Status</th>";
    echo "<th>Data Source</th>";
    echo "<th>Created At</th>";
    echo "</tr>";
    
    // Data rows
    foreach ($data as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['country']) . "</td>";
        echo "<td>" . htmlspecialchars($row['town']) . "</td>";
        echo "<td>" . htmlspecialchars($row['commodity_display']) . "</td>";
        echo "<td>" . htmlspecialchars($row['price']) . "</td>";
        echo "<td>" . htmlspecialchars($row['price_usd']) . "</td>";
        echo "<td>" . ($row['day_change'] !== null ? htmlspecialchars($row['day_change']) . '%' : 'N/A') . "</td>";
        echo "<td>" . ($row['month_change'] !== null ? htmlspecialchars($row['month_change']) . '%' : 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['date_posted']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['data_source']) . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    exit;
}

function exportToPDF($data) {
    require_once('../admin/includes/tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('AGRA System');
    $pdf->SetAuthor('AGRA System');
    $pdf->SetTitle('Miller Prices Export');
    $pdf->SetSubject('Miller Prices Data');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'Miller Prices Export', 'Generated on ' . date('Y-m-d H:i:s'));
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Set margins
    $pdf->SetMargins(15, 25, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Create HTML content
    $html = '<h2>Miller Prices Export</h2>';
    $html .= '<p>Generated on: ' . date('Y-m-d H:i:s') . '</p>';
    $html .= '<p>Total Records: ' . count($data) . '</p>';
    
    $html .= '<table border="1" cellpadding="4">';
    $html .= '<thead><tr style="background-color:#f2f2f2;">';
    $html .= '<th><b>ID</b></th>';
    $html .= '<th><b>Country</b></th>';
    $html .= '<th><b>Town</b></th>';
    $html .= '<th><b>Commodity</b></th>';
    $html .= '<th><b>Price (Local)</b></th>';
    $html .= '<th><b>Price (USD)</b></th>';
    $html .= '<th><b>Day Change %</b></th>';
    $html .= '<th><b>Month Change %</b></th>';
    $html .= '<th><b>Date Posted</b></th>';
    $html .= '<th><b>Status</b></th>';
    $html .= '<th><b>Data Source</b></th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    
    foreach ($data as $row) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($row['id']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['country']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['town']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['commodity_display']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['price']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['price_usd']) . '</td>';
        $html .= '<td>' . ($row['day_change'] !== null ? htmlspecialchars($row['day_change']) . '%' : 'N/A') . '</td>';
        $html .= '<td>' . ($row['month_change'] !== null ? htmlspecialchars($row['month_change']) . '%' : 'N/A') . '</td>';
        $html .= '<td>' . htmlspecialchars($row['date_posted']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['status']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['data_source']) . '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
    
    // Output HTML content
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Close and output PDF document
    $pdf->Output('miller_prices_export_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}
?>