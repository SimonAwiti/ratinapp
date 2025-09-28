<?php
// export_market_prices.php
session_start();
// Include the configuration file first
include '../admin/includes/config.php';

// Handle Export functionality
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $selected_ids = isset($_GET['ids']) ? json_decode($_GET['ids']) : [];
    
    if ($export_type === 'excel') {
        exportToExcel($con, $selected_ids);
    } elseif ($export_type === 'pdf') {
        exportToPDF($con, $selected_ids);
    }
    exit;
}

function exportToExcel($con, $selected_ids = []) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="market_prices_'.date('Y-m-d').'.xls"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: public');
    
    // Create query with optional ID filtering
    $where_clause = '';
    if (!empty($selected_ids)) {
        $ids_string = implode(',', array_map('intval', $selected_ids));
        $where_clause = "WHERE p.id IN ($ids_string)";
    }
    
    $sql = "SELECT
                p.id,
                p.market,
                c.commodity_name,
                p.price_type,
                p.Price,
                p.date_posted,
                p.status,
                p.data_source,
                p.variety,
                p.weight,
                p.unit,
                p.country_admin_0,
                p.supplied_volume,
                p.comments,
                p.supply_status
            FROM market_prices p
            LEFT JOIN commodities c ON p.commodity = c.id
            $where_clause
            ORDER BY p.date_posted DESC";
    
    $result = $con->query($sql);
    
    echo "Market Prices Export\n\n";
    echo "ID\tMarket\tCommodity\tPrice Type\tPrice\tDate Posted\tStatus\tData Source\tVariety\tWeight\tUnit\tCountry\tSupplied Volume\tComments\tSupply Status\n";
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo $row['id'] . "\t";
            echo $row['market'] . "\t";
            echo $row['commodity_name'] . "\t";
            echo $row['price_type'] . "\t";
            echo $row['Price'] . "\t";
            echo $row['date_posted'] . "\t";
            echo $row['status'] . "\t";
            echo $row['data_source'] . "\t";
            echo $row['variety'] . "\t";
            echo $row['weight'] . "\t";
            echo $row['unit'] . "\t";
            echo $row['country_admin_0'] . "\t";
            echo ($row['supplied_volume'] ?: '') . "\t";
            echo str_replace(["\t", "\n", "\r"], " ", $row['comments']) . "\t";
            echo $row['supply_status'] . "\n";
        }
    }
    exit;
}

function exportToPDF($con, $selected_ids = []) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    require_once('tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Market Prices System');
    $pdf->SetAuthor('Market Prices System');
    $pdf->SetTitle('Market Prices Export');
    $pdf->SetSubject('Market Prices Data');
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Create query with optional ID filtering
    $where_clause = '';
    if (!empty($selected_ids)) {
        $ids_string = implode(',', array_map('intval', $selected_ids));
        $where_clause = "WHERE p.id IN ($ids_string)";
    }
    
    $sql = "SELECT
                p.id,
                p.market,
                c.commodity_name,
                p.price_type,
                p.Price,
                p.date_posted,
                p.status,
                p.data_source
            FROM market_prices p
            LEFT JOIN commodities c ON p.commodity = c.id
            $where_clause
            ORDER BY p.date_posted DESC";
    
    $result = $con->query($sql);
    
    // Add title
    $pdf->Cell(0, 10, 'Market Prices Export - ' . date('Y-m-d'), 0, 1, 'C');
    $pdf->Ln(5);
    
    // Create table header
    $header = array('Market', 'Commodity', 'Type', 'Price', 'Date', 'Status', 'Source');
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('helvetica', 'B', 9);
    
    foreach ($header as $col) {
        $pdf->Cell(27, 7, $col, 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Add data
    $pdf->SetFont('helvetica', '', 8);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $pdf->Cell(27, 6, $row['market'], 1);
            $pdf->Cell(27, 6, substr($row['commodity_name'], 0, 15), 1);
            $pdf->Cell(27, 6, $row['price_type'], 1);
            $pdf->Cell(27, 6, $row['Price'], 1);
            $pdf->Cell(27, 6, date('m/d/Y', strtotime($row['date_posted'])), 1);
            $pdf->Cell(27, 6, $row['status'], 1);
            $pdf->Cell(27, 6, substr($row['data_source'], 0, 15), 1);
            $pdf->Ln();
        }
    }
    
    // Output PDF
    $pdf->Output('market_prices_'.date('Y-m-d').'.pdf', 'D');
    exit;
}

// If no export parameter, redirect back
header("Location: marketprices_boilerplate.php");
exit;
?>