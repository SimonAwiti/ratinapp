<?php
// bulk_export_countries.php
// This script exports ALL records from the 'countries' table to a CSV file.

// Include the configuration file to get the database connection ($con)
include '../admin/includes/config.php';

// Check if the connection is available
if (!isset($con) || $con->connect_error) {
    die("Database connection failed: " . $con->connect_error);
}

// 1. Set headers for file download
$filename = 'all_countries_export_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// 2. Open output stream
$output = fopen('php://output', 'w');

// 3. Define the SQL query to fetch ALL records
$sql = "SELECT
            c.id,
            c.country_name,
            c.currency_code,
            c.date_created
        FROM
            countries c
        ORDER BY
            c.country_name ASC";

$result = $con->query($sql);

if (!$result) {
    // If query fails, log the error and stop execution
    error_log("Error fetching all countries data for export: " . $con->error);
    exit;
}

// 4. Write the CSV column headers (Field Names)
$header_row = ['ID', 'Country Name', 'Currency Code', 'Date Created'];
fputcsv($output, $header_row);

// 5. Write data rows
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Format the date for better readability in the CSV
        $row['date_created'] = date('Y-m-d', strtotime($row['date_created']));
        
        // Write the data row to the CSV output
        fputcsv($output, $row);
    }
}

// 6. Clean up
$result->free();
$con->close();
fclose($output);

exit;
?>