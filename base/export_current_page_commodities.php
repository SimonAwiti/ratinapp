<?php
// bulk_export_commodities.php
include '../admin/includes/config.php';

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Access denied. This script only accepts POST requests.');
}

// Function to fetch ALL data
function getAllCommoditiesData($con) {
    $query = "
        SELECT
            c.id,
            c.hs_code,
            cc.name AS category,
            c.commodity_name,
            c.variety,
            c.image_url,
            c.units,
            c.commodity_alias,
            c.country,
            c.created_at
        FROM
            commodities c
        JOIN
            commodity_categories cc ON c.category_id = cc.id
        ORDER BY c.id ASC
    ";

    $result = $con->query($query);
    $data = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

// Get all data
$all_commodities_data = getAllCommoditiesData($con);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="commodities_all_' . date('Y-m-d_H-i-s') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 compatibility with Excel
fwrite($output, "\xEF\xBB\xBF");

// Add CSV headers
fputcsv($output, ['ID', 'HS Code', 'Category', 'Commodity Name', 'Variety', 'Image URL', 'Units (JSON)', 'Aliases (JSON)', 'Countries (JSON)', 'Created At']);

// Add data rows
foreach ($all_commodities_data as $commodity) {
    fputcsv($output, [
        $commodity['id'],
        $commodity['hs_code'],
        $commodity['category'],
        $commodity['commodity_name'],
        $commodity['variety'],
        $commodity['image_url'],
        $commodity['units'],
        $commodity['commodity_alias'],
        $commodity['country'],
        $commodity['created_at']
    ]);
}

// Add summary
fputcsv($output, []); // Empty row
fputcsv($output, ['Summary:']);
fputcsv($output, ['Total Records Exported:', count($all_commodities_data)]);
fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
fputcsv($output, ['Type:', 'BULK EXPORT (ALL RECORDS)']);

fclose($output);
exit();