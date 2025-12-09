<?php
// export_current_page_enumerators.php
include '../admin/includes/config.php';

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Access denied. This script only accepts POST requests.');
}

// Get parameters
$limit = isset($_POST['limit']) ? intval($_POST['limit']) : 7;
$offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

// Function to fetch current page data
function getEnumeratorsDataForExport($con, $limit, $offset) {
    $query = "
        SELECT
            id,
            name,
            email,
            phone,
            gender,
            country,
            county_district,
            username,
            tradepoints,
            latitude,
            longitude,
            token,
            created_at
        FROM enumerators
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
$enumerators_data = getEnumeratorsDataForExport($con, $limit, $offset);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="enumerators_current_page_' . date('Y-m-d_H-i-s') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 compatibility with Excel
fwrite($output, "\xEF\xBB\xBF");

// Add CSV headers
fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Gender', 'Country', 'County/District', 'Username', 'Tradepoints (JSON)', 'Latitude', 'Longitude', 'Token', 'Created At']);

// Add data rows
foreach ($enumerators_data as $enumerator) {
    fputcsv($output, [
        $enumerator['id'],
        $enumerator['name'],
        $enumerator['email'],
        $enumerator['phone'],
        $enumerator['gender'],
        $enumerator['country'],
        $enumerator['county_district'],
        $enumerator['username'],
        $enumerator['tradepoints'],
        $enumerator['latitude'],
        $enumerator['longitude'],
        $enumerator['token'],
        $enumerator['created_at']
    ]);
}

// Add summary
fputcsv($output, []); // Empty row
fputcsv($output, ['Summary:']);
fputcsv($output, ['Total Records Exported:', count($enumerators_data)]);
fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
fputcsv($output, ['Page Limit:', $limit]);
fputcsv($output, ['Page Offset:', $offset]);

fclose($output);
exit();