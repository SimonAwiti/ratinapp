<?php
// export_current_page_tradepoints.php
include '../admin/includes/config.php';

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Access denied. This script only accepts POST requests.');
}

// Get parameters
$limit = isset($_POST['limit']) ? intval($_POST['limit']) : 7;
$offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

// Function to fetch current page data
function getTradepointsDataForExport($con, $limit, $offset) {
    $query = "
        SELECT
            id,
            name,
            tradepoint_type,
            admin0,
            admin1
        FROM (
            SELECT
                id,
                market_name AS name,
                'Markets' AS tradepoint_type,
                country AS admin0,
                county_district AS admin1
            FROM markets
            
            UNION ALL
            
            SELECT
                id,
                name AS name,
                'Border Points' AS tradepoint_type,
                country AS admin0,
                county AS admin1
            FROM border_points
            
            UNION ALL
            
            SELECT
                id,
                miller_name AS name,
                'Millers' AS tradepoint_type,
                country AS admin0,
                county_district AS admin1
            FROM miller_details
        ) AS combined
        ORDER BY name ASC
        LIMIT ? OFFSET ?
    ";

    $stmt = $con->prepare($query);
    if ($stmt) {
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        $stmt->close();
        return $data;
    }
    return [];
}

// Get the data
$tradepoints_data = getTradepointsDataForExport($con, $limit, $offset);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="tradepoints_current_page_' . date('Y-m-d_H-i-s') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 compatibility with Excel
fwrite($output, "\xEF\xBB\xBF");

// Add CSV headers
fputcsv($output, ['ID', 'Name', 'Type', 'Country (Admin-0)', 'Region (Admin-1)']);

// Add data rows
foreach ($tradepoints_data as $tradepoint) {
    fputcsv($output, [
        $tradepoint['id'],
        $tradepoint['name'],
        $tradepoint['tradepoint_type'],
        $tradepoint['admin0'],
        $tradepoint['admin1']
    ]);
}

// Add summary
fputcsv($output, []); // Empty row
fputcsv($output, ['Summary:']);
fputcsv($output, ['Total Records Exported:', count($tradepoints_data)]);
fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
fputcsv($output, ['Page Limit:', $limit]);
fputcsv($output, ['Page Offset:', $offset]);

fclose($output);
exit();