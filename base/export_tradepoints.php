<?php
// export_tradepoints.php

// Include the configuration file
include '../admin/includes/config.php';

// Check if export request is valid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_format'])) {
    $format = $_POST['export_format'];
    $selected_ids = isset($_POST['selected_ids']) ? $_POST['selected_ids'] : [];
    
    // Validate selected IDs
    if (empty($selected_ids)) {
        die('No tradepoints selected for export.');
    }
    
    // Sanitize IDs
    $sanitized_ids = array_map('intval', $selected_ids);
    $placeholders = implode(',', array_fill(0, count($sanitized_ids), '?'));
    
    // Fetch selected tradepoints data - FIXED QUERY
    $query = "
        (SELECT
            id,
            market_name AS name,
            'Markets' AS tradepoint_type,
            category,
            type,
            country AS admin0,
            county_district AS admin1,
            longitude,
            latitude,
            radius,
            currency,
            primary_commodity,
            additional_datasource,
            '' AS miller_details
        FROM markets WHERE id IN ($placeholders))
        
        UNION ALL
        
        (SELECT
            id,
            name AS name,
            'Border Points' AS tradepoint_type,
            '' AS category,
            '' AS type,
            country AS admin0,
            county AS admin1,
            longitude,
            latitude,
            0 AS radius,
            '' AS currency,
            '' AS primary_commodity,
            '' AS additional_datasource,
            '' AS miller_details
        FROM border_points WHERE id IN ($placeholders))
        
        UNION ALL
        
        (SELECT
            id,
            miller_name AS name,
            'Millers' AS tradepoint_type,
            '' AS category,
            '' AS type,
            country AS admin0,
            county_district AS admin1,
            0 AS longitude,
            0 AS latitude,
            0 AS radius,
            currency,
            '' AS primary_commodity,
            '' AS additional_datasource,
            miller AS miller_details
        FROM miller_details WHERE id IN ($placeholders))
        
        ORDER BY name ASC
    ";
    
    $stmt = $con->prepare($query);
    if ($stmt) {
        // Bind parameters dynamically - need to bind multiple times for each table
        $types = str_repeat('i', count($sanitized_ids));
        
        // Create an array with the IDs repeated 3 times (for each table in the UNION)
        $bound_params = [];
        for ($i = 0; $i < 3; $i++) {
            $bound_params = array_merge($bound_params, $sanitized_ids);
        }
        
        $stmt->bind_param(str_repeat($types, 3), ...$bound_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $tradepoints = $result->fetch_all(MYSQLI_ASSOC);
        
        if ($format === 'excel') {
            exportToExcel($tradepoints);
        } elseif ($format === 'pdf') {
            exportToPDF($tradepoints);
        } else {
            die('Invalid export format.');
        }
    } else {
        die('Database error: ' . $con->error);
    }
} else {
    die('Invalid request.');
}

function exportToExcel($tradepoints) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="tradepoints_export_' . date('Y-m-d_H-i-s') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "<table border='1'>";
    echo "<tr>
            <th>ID</th>
            <th>Name</th>
            <th>Type</th>
            <th>Category</th>
            <th>Market Type</th>
            <th>Country</th>
            <th>Region</th>
            <th>Longitude</th>
            <th>Latitude</th>
            <th>Radius</th>
            <th>Currency</th>
            <th>Primary Commodities</th>
            <th>Data Source</th>
            <th>Miller Details</th>
          </tr>";
    
    foreach ($tradepoints as $tradepoint) {
        // Format miller details if present
        $miller_details = '';
        if (!empty($tradepoint['miller_details'])) {
            $millers = json_decode($tradepoint['miller_details'], true) ?: [];
            $miller_details = implode(', ', $millers);
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($tradepoint['id']) . "</td>";
        echo "<td>" . htmlspecialchars($tradepoint['name']) . "</td>";
        echo "<td>" . htmlspecialchars($tradepoint['tradepoint_type']) . "</td>";
        echo "<td>" . htmlspecialchars($tradepoint['category']) . "</td>";
        echo "<td>" . htmlspecialchars($tradepoint['type']) . "</td>";
        echo "<td>" . htmlspecialchars($tradepoint['admin0']) . "</td>";
        echo "<td>" . htmlspecialchars($tradepoint['admin1']) . "</td>";
        echo "<td>" . htmlspecialchars($tradepoint['longitude']) . "</td>";
        echo "<td>" . htmlspecialchars($tradepoint['latitude']) . "</td>";
        echo "<td>" . htmlspecialchars($tradepoint['radius']) . "</td>";
        echo "<td>" . htmlspecialchars($tradepoint['currency']) . "</td>";
        echo "<td>" . htmlspecialchars($tradepoint['primary_commodity']) . "</td>";
        echo "<td>" . htmlspecialchars($tradepoint['additional_datasource']) . "</td>";
        echo "<td>" . htmlspecialchars($miller_details) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    exit;
}

function exportToPDF($tradepoints) {
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { text-align: center; margin-bottom: 20px; }
            .page-break { page-break-after: always; }
            tr:nth-child(even) { background-color: #f9f9f9; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Tradepoints Export</h1>
            <p>Generated on: " . date('Y-m-d H:i:s') . "</p>
            <p>Total Records: " . count($tradepoints) . "</p>
        </div>
        <table>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Country</th>
                <th>Region</th>
                <th>Longitude</th>
                <th>Latitude</th>
                <th>Currency</th>
            </tr>
    ";
    
    foreach ($tradepoints as $tradepoint) {
        // Format miller details if present
        $miller_details = '';
        if (!empty($tradepoint['miller_details'])) {
            $millers = json_decode($tradepoint['miller_details'], true) ?: [];
            $miller_details = implode(', ', $millers);
        }
        
        $html .= "<tr>";
        $html .= "<td>" . htmlspecialchars($tradepoint['name']) . "</td>";
        $html .= "<td>" . htmlspecialchars($tradepoint['tradepoint_type']) . "</td>";
        $html .= "<td>" . htmlspecialchars($tradepoint['admin0']) . "</td>";
        $html .= "<td>" . htmlspecialchars($tradepoint['admin1']) . "</td>";
        $html .= "<td>" . htmlspecialchars($tradepoint['longitude']) . "</td>";
        $html .= "<td>" . htmlspecialchars($tradepoint['latitude']) . "</td>";
        $html .= "<td>" . htmlspecialchars($tradepoint['currency']) . "</td>";
        $html .= "</tr>";
    }
    
    $html .= "</table></body></html>";
    
    // For now, we'll output as HTML that can be printed as PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="tradepoints_export_' . date('Y-m-d_H-i-s') . '.pdf"');
    
    echo $html;
    exit;
}
?>