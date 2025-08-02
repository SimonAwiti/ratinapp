<?php
// post_xbtvol_targeted_fix.php

// Include your database configuration file
include '../../admin/includes/config.php';

header('Content-Type: application/json');

// Check for POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the raw POST data
    $json_data = file_get_contents("php://input");
    $data = json_decode($json_data, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['tradepoint_id']) || !isset($data['submissions']) || !is_array($data['submissions'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload or missing required fields.']);
        http_response_code(400);
        exit;
    }

    $tradepoint_id = (int)$data['tradepoint_id'];
    $submissions = $data['submissions'];

    // Fetch border point details
    $border_name = "";
    $country = "";
    $stmt_border = $con->prepare("SELECT name, country FROM border_points WHERE id = ?");
    if ($stmt_border) {
        $stmt_border->bind_param("i", $tradepoint_id);
        $stmt_border->execute();
        $border_result = $stmt_border->get_result();
        if ($border_result && $border_result->num_rows > 0) {
            $border_row = $border_result->fetch_assoc();
            $border_name = $border_row['name'];
            $country = $border_row['country'] ?? 'Unknown';
        }
        $stmt_border->close();
    }

    if (empty($border_name)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid tradepoint_id provided.']);
        http_response_code(404);
        exit;
    }

    $successful_inserts = 0;
    $errors = [];

    // Prepare the insert statement
    $sql = "INSERT INTO xbt_volumes (country, border_id, border_name, commodity_id, commodity_name,
            category_id, category_name, variety, data_type, volume, source, destination, data_source_id,
            data_source_name, comments, date_posted, status, day, month, year)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt_insert = $con->prepare($sql);

    if (!$stmt_insert) {
        echo json_encode(['status' => 'error', 'message' => 'Error preparing statement: ' . $con->error]);
        http_response_code(500);
        exit;
    }

    foreach ($submissions as $index => $submission) {
        $commodity_id = isset($submission['commodity_id']) ? (int)$submission['commodity_id'] : 0;
        $data_type = isset($submission['data_type']) ? $submission['data_type'] : '';
        $data_source_id = isset($submission['data_source_id']) ? (int)$submission['data_source_id'] : 0;
        $source_id = isset($submission['source']['id']) ? (int)$submission['source']['id'] : 0;
        $destination_id = isset($submission['destination']['id']) ? (int)$submission['destination']['id'] : 0;
        $volume = isset($submission['volume']) ? (float)$submission['volume'] : 0;
        $comments = isset($submission['comments']) ? $submission['comments'] : '';

        // Validate required fields
        if ($commodity_id <= 0 || $volume <= 0 || $source_id <= 0 || $destination_id <= 0 || empty($data_type) || $data_source_id <= 0) {
            $errors[] = ['submission' => $submission, 'message' => 'Missing or invalid required fields.'];
            continue;
        }

        // Fetch commodity details
        $commodity_name = "";
        $commodity_variety = "";
        $category_id = 0;
        $category_name = "";
        $stmt_commodity = $con->prepare("SELECT commodity_name, variety, category_id FROM commodities WHERE id = ?");
        if ($stmt_commodity) {
            $stmt_commodity->bind_param("i", $commodity_id);
            $stmt_commodity->execute();
            $commodity_result = $stmt_commodity->get_result();
            if ($commodity_result && $commodity_result->num_rows > 0) {
                $commodity_row = $commodity_result->fetch_assoc();
                $commodity_name = $commodity_row['commodity_name'];
                $commodity_variety = $commodity_row['variety'] ?? '';
                $category_id = $commodity_row['category_id'];

                if ($category_id > 0) {
                    $stmt_category = $con->prepare("SELECT name FROM commodity_categories WHERE id = ?");
                    if ($stmt_category) {
                        $stmt_category->bind_param("i", $category_id);
                        $stmt_category->execute();
                        $category_result = $stmt_category->get_result();
                        if ($category_result && $category_result->num_rows > 0) {
                            $category_row = $category_result->fetch_assoc();
                            $category_name = $category_row['name'];
                        }
                        $stmt_category->close();
                    }
                }
            }
            $stmt_commodity->close();
        }

        // HANDLE DESTINATION FIRST (since it's working)
        $destination_country_name = "";
        $stmt_dest = $con->prepare("SELECT country_name FROM countries WHERE id = ?");
        if ($stmt_dest) {
            $stmt_dest->bind_param("i", $destination_id);
            $stmt_dest->execute();
            $dest_result = $stmt_dest->get_result();
            if ($dest_result && $dest_result->num_rows > 0) {
                $dest_row = $dest_result->fetch_assoc();
                $destination_country_name = $dest_row['country_name'];
            }
            $stmt_dest->close();
        }
        
        error_log("Destination processing - ID: $destination_id, Name: '$destination_country_name'");

        // NOW HANDLE SOURCE EXACTLY THE SAME WAY AS DESTINATION
        $source_country_name = "";
        $stmt_src = $con->prepare("SELECT country_name FROM countries WHERE id = ?");
        if ($stmt_src) {
            $stmt_src->bind_param("i", $source_id);
            $stmt_src->execute();
            $src_result = $stmt_src->get_result();
            if ($src_result && $src_result->num_rows > 0) {
                $src_row = $src_result->fetch_assoc();
                $source_country_name = $src_row['country_name'];
            }
            $stmt_src->close();
        }
        
        error_log("Source processing - ID: $source_id, Name: '$source_country_name'");

        if (empty($source_country_name) || empty($destination_country_name)) {
            $errors[] = ['submission' => $submission, 'message' => 'Could not retrieve source or destination country names.'];
            continue;
        }

        // Fetch data source name
        $data_source_name = "";
        $stmt_data_source = $con->prepare("SELECT data_source_name FROM data_sources WHERE id = ?");
        if ($stmt_data_source) {
            $stmt_data_source->bind_param("i", $data_source_id);
            $stmt_data_source->execute();
            $data_source_result = $stmt_data_source->get_result();
            if ($data_source_result && $data_source_result->num_rows > 0) {
                $data_source_row = $data_source_result->fetch_assoc();
                $data_source_name = $data_source_row['data_source_name'];
            }
            $stmt_data_source->close();
        }

        // Get current date details
        $date_posted = date('Y-m-d H:i:s');
        $status = 'pending';
        $day = date('d');
        $month = date('m');
        $year = date('Y');

        error_log("About to insert - Source: '$source_country_name', Destination: '$destination_country_name'");

        // Use the same variable names for both source and destination
        $final_source = $source_country_name;
        $final_destination = $destination_country_name;
        
        error_log("Final variables - Source: '$final_source', Destination: '$final_destination'");

        // Bind and execute
        $stmt_insert->bind_param(
            "sisississsdssssssiii",
            $country, $tradepoint_id, $border_name, $commodity_id, $commodity_name,
            $category_id, $category_name, $commodity_variety, $data_type, $volume,
            $final_source, $final_destination, $data_source_id,
            $data_source_name, $comments, $date_posted, $status, $day, $month, $year
        );

        if ($stmt_insert->execute()) {
            $successful_inserts++;
            $insert_id = $con->insert_id;
            error_log("Successfully inserted record ID: $insert_id");
            
            // Immediately verify what was inserted
            $verify_stmt = $con->prepare("SELECT source, destination FROM xbt_volumes WHERE id = ?");
            $verify_stmt->bind_param("i", $insert_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            if ($verify_result && $verify_result->num_rows > 0) {
                $verify_row = $verify_result->fetch_assoc();
                error_log("VERIFICATION - Record $insert_id: Source='{$verify_row['source']}', Destination='{$verify_row['destination']}'");
            }
            $verify_stmt->close();
        } else {
            error_log("Insert failed: " . $stmt_insert->error);
            $errors[] = ['submission' => $submission, 'message' => 'Error inserting record: ' . $stmt_insert->error];
        }
    }

    $stmt_insert->close();

    if ($successful_inserts > 0) {
        $response = ['status' => 'success', 'message' => $successful_inserts . ' XBT volume records created successfully.'];
        if (!empty($errors)) {
            $response['partial_errors'] = $errors;
            http_response_code(206);
        } else {
            http_response_code(201);
        }
        echo json_encode($response);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No records were inserted.', 'errors' => $errors]);
        http_response_code(400);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests are allowed.']);
    http_response_code(405);
}

if (isset($con)) {
    $con->close();
}
?>