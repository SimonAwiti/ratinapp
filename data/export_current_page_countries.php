<?php
// export_current_page_countries.php
include '../admin/includes/config.php';

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Access denied. This script only accepts POST requests.');
}

// Get parameters from POST
$limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
$offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
$search_country = isset($_POST['search_country']) ? trim($_POST['search_country']) : '';
$search_currency = isset($_POST['search_currency']) ? trim($_POST['search_currency']) : '';

// Validate parameters
if ($limit < 1) $limit = 10;
if ($offset < 0) $offset = 0;

// Function to fetch current page data
function getCountriesDataForExport($con, $limit, $offset, $search_country, $search_currency) {
    $sql = "SELECT
                c.id,
                c.country_name,
                c.currency_code,
                DATE(c.date_created) as date_created
            FROM
                countries c
            WHERE 1=1";
    
    $params = [];
    $types = '';
    
    if (!empty($search_country)) {
        $sql .= " AND c.country_name LIKE ?";
        $params[] = '%' . $search_country . '%';
        $types .= 's';
    }
    
    if (!empty($search_currency)) {
        $sql .= " AND c.currency_code LIKE ?";
        $params[] = '%' . $search_currency . '%';
        $types .= 's';
    }
    
    $sql .= " ORDER BY c.country_name ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $con->prepare($sql);
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
    } else {
        error_log("Error preparing export query: " . $con->error);
        return [];
    }
}

// Get the data
$countries_data = getCountriesDataForExport($con, $limit, $offset, $search_country, $search_currency);

// If no data found, provide a helpful message
if (empty($countries_data)) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h3>No data to export</h3>';
    echo '<p>No countries found matching your criteria.</p>';
    echo '<p><a href="javascript:history.back()">Go back</a></p>';
    exit();
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="countries_page_export_' . date('Y-m-d_H-i-s') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 compatibility with Excel
fwrite($output, "\xEF\xBB\xBF");

// Add CSV headers
fputcsv($output, ['ID', 'Country Name', 'Currency Code', 'Date Created']);

// Add data rows
foreach ($countries_data as $country) {
    fputcsv($output, [
        $country['id'],
        $country['country_name'],
        $country['currency_code'],
        $country['date_created']
    ]);
}

// Also add a summary row
fputcsv($output, []); // Empty row
fputcsv($output, ['Summary:']);
fputcsv($output, ['Total Records Exported:', count($countries_data)]);
fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
if (!empty($search_country)) {
    fputcsv($output, ['Country Filter:', $search_country]);
}
if (!empty($search_currency)) {
    fputcsv($output, ['Currency Filter:', $search_currency]);
}

fclose($output);
exit();