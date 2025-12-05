<?php
// export_current_page_sources.php
include '../admin/includes/config.php';

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Access denied. This script only accepts POST requests.');
}

// Get parameters
$limit = isset($_POST['limit']) ? intval($_POST['limit']) : 7;
$offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
$filters = isset($_POST['filters']) ? json_decode($_POST['filters'], true) : [];

// Function to fetch current page data
function getSourcesDataForExport($con, $limit, $offset, $filters) {
    $query = "
        SELECT
            id,
            admin0_country,
            admin1_county_district,
            DATE(created_at) as created_date
        FROM
            commodity_sources
        WHERE 1=1
    ";
    
    $params = [];
    $types = '';
    
    if (!empty($filters['id'])) {
        $query .= " AND id LIKE ?";
        $params[] = '%' . $filters['id'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['admin0'])) {
        $query .= " AND admin0_country LIKE ?";
        $params[] = '%' . $filters['admin0'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['admin1'])) {
        $query .= " AND admin1_county_district LIKE ?";
        $params[] = '%' . $filters['admin1'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['created_at'])) {
        $query .= " AND DATE(created_at) LIKE ?";
        $params[] = '%' . $filters['created_at'] . '%';
        $types .= 's';
    }
    
    $query .= " ORDER BY admin0_country ASC, admin1_county_district ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $con->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
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
$sources_data = getSourcesDataForExport($con, $limit, $offset, $filters);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="commodity_sources_current_page_' . date('Y-m-d_H-i-s') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 compatibility with Excel
fwrite($output, "\xEF\xBB\xBF");

// Add CSV headers
fputcsv($output, ['ID', 'Admin-0 (Country)', 'Admin-1 (County/District)', 'Created Date']);

// Add data rows
foreach ($sources_data as $source) {
    fputcsv($output, [
        $source['id'],
        $source['admin0_country'],
        $source['admin1_county_district'],
        $source['created_date']
    ]);
}

// Add summary
fputcsv($output, []); // Empty row
fputcsv($output, ['Summary:']);
fputcsv($output, ['Total Records Exported:', count($sources_data)]);
fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
fputcsv($output, ['Page Limit:', $limit]);
fputcsv($output, ['Page Offset:', $offset]);

if (!empty($filters)) {
    fputcsv($output, ['Filters Applied:']);
    foreach ($filters as $key => $value) {
        if (!empty($value)) {
            fputcsv($output, [ucfirst($key) . ':', $value]);
        }
    }
}

fclose($output);
exit();