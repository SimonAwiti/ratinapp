<?php
// export_enumerators.php

// Include the configuration file
include '../admin/includes/config.php';

// Check if export request is valid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_format'])) {
    $format = $_POST['export_format'];
    $selected_ids = isset($_POST['selected_ids']) ? $_POST['selected_ids'] : [];
    
    // Validate selected IDs
    if (empty($selected_ids)) {
        die('No enumerators selected for export.');
    }
    
    // Sanitize IDs
    $sanitized_ids = array_map('intval', $selected_ids);
    $placeholders = implode(',', array_fill(0, count($sanitized_ids), '?'));
    
    // Fetch selected enumerators data
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
            latitude,
            longitude,
            tradepoints
        FROM enumerators
        WHERE id IN ($placeholders)
        ORDER BY name ASC
    ";
    
    $stmt = $con->prepare($query);
    if ($stmt) {
        $types = str_repeat('i', count($sanitized_ids));
        $stmt->bind_param($types, ...$sanitized_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        $enumerators = $result->fetch_all(MYSQLI_ASSOC);
        
        if ($format === 'excel') {
            exportToExcel($enumerators);
        } elseif ($format === 'pdf') {
            exportToPDF($enumerators);
        } else {
            die('Invalid export format.');
        }
    } else {
        die('Database error: ' . $con->error);
    }
} else {
    die('Invalid request.');
}

function exportToExcel($enumerators) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="enumerators_export_' . date('Y-m-d_H-i-s') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "<table border='1'>";
    echo "<tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Gender</th>
            <th>Country</th>
            <th>Region</th>
            <th>Username</th>
            <th>Latitude</th>
            <th>Longitude</th>
            <th>Assigned Tradepoints</th>
          </tr>";
    
    foreach ($enumerators as $enumerator) {
        // Format tradepoints
        $tradepoints_display = 'None';
        if (!empty($enumerator['tradepoints'])) {
            $tradepoints = json_decode($enumerator['tradepoints'], true) ?: [];
            $tp_list = [];
            foreach ($tradepoints as $tp) {
                if (isset($tp['type']) && isset($tp['id'])) {
                    $tp_list[] = $tp['type'] . ':' . $tp['id'];
                }
            }
            $tradepoints_display = implode(', ', $tp_list);
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($enumerator['id']) . "</td>";
        echo "<td>" . htmlspecialchars($enumerator['name']) . "</td>";
        echo "<td>" . htmlspecialchars($enumerator['email']) . "</td>";
        echo "<td>" . htmlspecialchars($enumerator['phone']) . "</td>";
        echo "<td>" . htmlspecialchars($enumerator['gender']) . "</td>";
        echo "<td>" . htmlspecialchars($enumerator['country']) . "</td>";
        echo "<td>" . htmlspecialchars($enumerator['county_district']) . "</td>";
        echo "<td>" . htmlspecialchars($enumerator['username']) . "</td>";
        echo "<td>" . htmlspecialchars($enumerator['latitude']) . "</td>";
        echo "<td>" . htmlspecialchars($enumerator['longitude']) . "</td>";
        echo "<td>" . htmlspecialchars($tradepoints_display) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    exit;
}

function exportToPDF($enumerators) {
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { text-align: center; margin-bottom: 20px; }
            tr:nth-child(even) { background-color: #f9f9f9; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Enumerators Export</h1>
            <p>Generated on: " . date('Y-m-d H:i:s') . "</p>
            <p>Total Records: " . count($enumerators) . "</p>
        </div>
        <table>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Country</th>
                <th>Region</th>
                <th>Assigned Tradepoints</th>
            </tr>
    ";
    
    foreach ($enumerators as $enumerator) {
        // Format tradepoints
        $tradepoints_display = 'None';
        if (!empty($enumerator['tradepoints'])) {
            $tradepoints = json_decode($enumerator['tradepoints'], true) ?: [];
            $tp_list = [];
            foreach ($tradepoints as $tp) {
                if (isset($tp['type']) && isset($tp['id'])) {
                    $tp_list[] = $tp['type'] . ':' . $tp['id'];
                }
            }
            $tradepoints_display = implode(', ', $tp_list);
        }
        
        $html .= "<tr>";
        $html .= "<td>" . htmlspecialchars($enumerator['name']) . "</td>";
        $html .= "<td>" . htmlspecialchars($enumerator['email']) . "</td>";
        $html .= "<td>" . htmlspecialchars($enumerator['phone']) . "</td>";
        $html .= "<td>" . htmlspecialchars($enumerator['country']) . "</td>";
        $html .= "<td>" . htmlspecialchars($enumerator['county_district']) . "</td>";
        $html .= "<td>" . htmlspecialchars($tradepoints_display) . "</td>";
        $html .= "</tr>";
    }
    
    $html .= "</table></body></html>";
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="enumerators_export_' . date('Y-m-d_H-i-s') . '.pdf"');
    
    echo $html;
    exit;
}
?>