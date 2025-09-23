<?php
// export_commodities.php

// Include the configuration file
include '../admin/includes/config.php';

// Check if export request is valid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_format'])) {
    $format = $_POST['export_format'];
    $selected_ids = isset($_POST['selected_ids']) ? $_POST['selected_ids'] : [];
    
    // Validate selected IDs
    if (empty($selected_ids)) {
        die('No commodities selected for export.');
    }
    
    // Sanitize IDs
    $sanitized_ids = array_map('intval', $selected_ids);
    $placeholders = implode(',', array_fill(0, count($sanitized_ids), '?'));
    
    // Fetch selected commodities data
    $query = "
        SELECT
            c.hs_code,
            cc.name AS category,
            c.commodity_name,
            c.variety,
            c.units,
            c.commodity_alias,
            c.country,
            c.image_url
        FROM
            commodities c
        JOIN
            commodity_categories cc ON c.category_id = cc.id
        WHERE
            c.id IN ($placeholders)
    ";
    
    $stmt = $con->prepare($query);
    if ($stmt) {
        // Bind parameters dynamically
        $types = str_repeat('i', count($sanitized_ids));
        $stmt->bind_param($types, ...$sanitized_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        $commodities = $result->fetch_all(MYSQLI_ASSOC);
        
        if ($format === 'excel') {
            exportToExcel($commodities);
        } elseif ($format === 'pdf') {
            exportToPDF($commodities);
        } else {
            die('Invalid export format.');
        }
    } else {
        die('Database error: ' . $con->error);
    }
} else {
    die('Invalid request.');
}

function exportToExcel($commodities) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="commodities_export_' . date('Y-m-d_H-i-s') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "<table border='1'>";
    echo "<tr>
            <th>HS Code</th>
            <th>Category</th>
            <th>Commodity Name</th>
            <th>Variety</th>
            <th>Packaging Units</th>
            <th>Aliases & Countries</th>
            <th>Countries</th>
            <th>Image URL</th>
          </tr>";
    
    foreach ($commodities as $commodity) {
        // Decode JSON fields for better readability
        $units = json_decode($commodity['units'], true) ?: [];
        $aliases = json_decode($commodity['commodity_alias'], true) ?: [];
        $countries = json_decode($commodity['country'], true) ?: [];
        
        // Format units
        $units_display = [];
        foreach ($units as $unit) {
            if (isset($unit['size']) && isset($unit['unit'])) {
                $units_display[] = $unit['size'] . $unit['unit'];
            }
        }
        
        // Format aliases
        $aliases_display = [];
        foreach ($aliases as $alias) {
            if (isset($alias['alias']) && isset($alias['country'])) {
                $aliases_display[] = $alias['alias'] . ':' . $alias['country'];
            }
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($commodity['hs_code']) . "</td>";
        echo "<td>" . htmlspecialchars($commodity['category']) . "</td>";
        echo "<td>" . htmlspecialchars($commodity['commodity_name']) . "</td>";
        echo "<td>" . htmlspecialchars($commodity['variety']) . "</td>";
        echo "<td>" . htmlspecialchars(implode(', ', $units_display)) . "</td>";
        echo "<td>" . htmlspecialchars(implode(', ', $aliases_display)) . "</td>";
        echo "<td>" . htmlspecialchars(implode(', ', $countries)) . "</td>";
        echo "<td>" . htmlspecialchars($commodity['image_url']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    exit;
}

function exportToPDF($commodities) {
    // For PDF export, we'll use a simple HTML to PDF approach
    // You might want to use a proper PDF library like TCPDF or Dompdf in production
    
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .header { text-align: center; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Commodities Export</h1>
            <p>Generated on: " . date('Y-m-d H:i:s') . "</p>
            <p>Total Records: " . count($commodities) . "</p>
        </div>
        <table>
            <tr>
                <th>HS Code</th>
                <th>Category</th>
                <th>Commodity Name</th>
                <th>Variety</th>
                <th>Packaging Units</th>
                <th>Aliases & Countries</th>
                <th>Countries</th>
            </tr>
    ";
    
    foreach ($commodities as $commodity) {
        // Decode JSON fields
        $units = json_decode($commodity['units'], true) ?: [];
        $aliases = json_decode($commodity['commodity_alias'], true) ?: [];
        $countries = json_decode($commodity['country'], true) ?: [];
        
        // Format units
        $units_display = [];
        foreach ($units as $unit) {
            if (isset($unit['size']) && isset($unit['unit'])) {
                $units_display[] = $unit['size'] . $unit['unit'];
            }
        }
        
        // Format aliases
        $aliases_display = [];
        foreach ($aliases as $alias) {
            if (isset($alias['alias']) && isset($alias['country'])) {
                $aliases_display[] = $alias['alias'] . ':' . $alias['country'];
            }
        }
        
        $html .= "<tr>";
        $html .= "<td>" . htmlspecialchars($commodity['hs_code']) . "</td>";
        $html .= "<td>" . htmlspecialchars($commodity['category']) . "</td>";
        $html .= "<td>" . htmlspecialchars($commodity['commodity_name']) . "</td>";
        $html .= "<td>" . htmlspecialchars($commodity['variety']) . "</td>";
        $html .= "<td>" . htmlspecialchars(implode(', ', $units_display)) . "</td>";
        $html .= "<td>" . htmlspecialchars(implode(', ', $aliases_display)) . "</td>";
        $html .= "<td>" . htmlspecialchars(implode(', ', $countries)) . "</td>";
        $html .= "</tr>";
    }
    
    $html .= "</table></body></html>";
    
    // For now, we'll output as HTML that can be printed as PDF
    // In production, you'd use a proper PDF library
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="commodities_export_' . date('Y-m-d_H-i-s') . '.pdf"');
    
    // Simple PDF generation using browser's print to PDF functionality
    echo $html;
    exit;
}
?>