<?php
session_start();

if (file_exists('includes/config.php')) {
    include 'includes/config.php';
} elseif (file_exists('../admin/includes/config.php')) {
    include '../admin/includes/config.php';
}
mysqli_set_charset($con, "utf8mb4");

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../admin/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['add_tradepoint'])) {
    header('Location: tradepoints_boilerplate.php');
    exit;
}

$type       = $_POST['tradepoint_type'] ?? '';
$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

function handleMultiUpload($fileField, $upload_dir) {
    $paths = [];
    if (isset($_FILES[$fileField]) && !empty($_FILES[$fileField]['name'][0])) {
        foreach ($_FILES[$fileField]['tmp_name'] as $key => $tmp_name) {
            if ($_FILES[$fileField]['error'][$key] === UPLOAD_ERR_OK) {
                $image_name = basename($_FILES[$fileField]['name'][$key]);
                $image_path = $upload_dir . time() . '_' . uniqid() . '_' . $image_name;
                if (move_uploaded_file($tmp_name, $image_path)) {
                    $paths[] = $image_path;
                }
            }
        }
    }
    return $paths;
}

$currency_map = [
    'Kenya' => 'KES', 'Uganda' => 'UGX', 'Tanzania' => 'TZS', 'Rwanda' => 'RWF',
    'Burundi' => 'BIF', 'South Sudan' => 'SSP', 'Ethiopia' => 'ETB', 'Somalia' => 'SOS',
    'Democratic Republic of Congo' => 'CDF', 'DRC' => 'CDF',
];

$flash_message = 'Invalid tradepoint type submitted.';
$flash_type    = 'error';

// ============================================================
// MARKETS
// ============================================================
if ($type === 'Markets') {

    $market_name           = trim($_POST['market_name'] ?? '');
    $category              = trim($_POST['category'] ?? '');
    $market_type            = trim($_POST['type'] ?? '');
    $country                = trim($_POST['country'] ?? '');
    $county_district        = trim($_POST['county_district'] ?? '');
    $longitude               = $_POST['longitude'] ?? '';
    $latitude                = $_POST['latitude'] ?? '';
    $radius                  = $_POST['radius'] ?? '';
    $primary_commodity       = trim($_POST['primary_commodity'] ?? '');
    $additional_datasource   = trim($_POST['additional_datasource'] ?? '');
    $currency                = $currency_map[$country] ?? 'USD';

    if ($market_name === '' || $category === '' || $market_type === '' || $country === '' ||
        $county_district === '' || $longitude === '' || $latitude === '' || $radius === '') {
        $flash_message = 'All required market fields must be filled in.';
    } else {
        $stmt = $con->prepare("SELECT id FROM markets WHERE market_name = ? AND category = ? AND type = ?");
        $stmt->bind_param('sss', $market_name, $category, $market_type);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $flash_message = "A market named \"$market_name\" with that category and type already exists.";
        } else {
            $image_paths = handleMultiUpload('marketImages', $upload_dir);
            $image_urls  = json_encode($image_paths);
            $created_at  = date('Y-m-d H:i:s');

            $stmt = $con->prepare("INSERT INTO markets
                (market_name, category, type, country, county_district, longitude, latitude, radius, currency, primary_commodity, additional_datasource, image_urls, tradepoint, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'Markets',?)");
            $stmt->bind_param('sssssdddsssss',
                $market_name, $category, $market_type, $country, $county_district,
                $longitude, $latitude, $radius, $currency,
                $primary_commodity, $additional_datasource, $image_urls, $created_at
            );

            if ($stmt->execute()) {
                $flash_message = "Market \"$market_name\" added successfully!";
                $flash_type    = 'success';
            } else {
                $flash_message = "Failed to add market: " . $stmt->error;
                foreach ($image_paths as $p) if (file_exists($p)) unlink($p);
            }
            $stmt->close();
        }
    }

// ============================================================
// BORDER POINTS
// ============================================================
} elseif ($type === 'Border Points') {

    $name      = trim($_POST['border_name'] ?? '');
    $country   = trim($_POST['border_country'] ?? '');
    $county    = trim($_POST['border_county'] ?? '');
    $longitude = $_POST['border_longitude'] ?? '';
    $latitude  = $_POST['border_latitude'] ?? '';
    $radius    = $_POST['border_radius'] ?? '';

    if ($name === '' || $country === '' || $county === '' || $longitude === '' || $latitude === '' || $radius === '') {
        $flash_message = 'All required border point fields must be filled in.';
    } else {
        $stmt = $con->prepare("SELECT id FROM border_points WHERE name = ?");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $flash_message = "A border point named \"$name\" already exists.";
        } else {
            $image_paths = handleMultiUpload('borderImages', $upload_dir);
            $images_json = json_encode($image_paths);
            $created_at  = date('Y-m-d H:i:s');

            $stmt = $con->prepare("INSERT INTO border_points
                (name, country, county, longitude, latitude, radius, tradepoint, images, created_at)
                VALUES (?,?,?,?,?,?,'Border Points',?,?)");
            $stmt->bind_param('sssdddss',
                $name, $country, $county, $longitude, $latitude, $radius, $images_json, $created_at
            );

            if ($stmt->execute()) {
                $flash_message = "Border point \"$name\" added successfully!";
                $flash_type    = 'success';
            } else {
                $flash_message = "Failed to add border point: " . $stmt->error;
                foreach ($image_paths as $p) if (file_exists($p)) unlink($p);
            }
            $stmt->close();
        }
    }

// ============================================================
// MILLERS
// ============================================================
} elseif ($type === 'Millers') {

    $miller_name     = trim($_POST['miller_name'] ?? '');
    $country         = trim($_POST['miller_country'] ?? '');
    $county_district = trim($_POST['miller_county_district'] ?? '');
    $currency        = trim($_POST['miller_currency'] ?? ($currency_map[$country] ?? ''));

    // The step-3 "Select Millers" list in the modal isn't populated by any JS yet,
    // so selected_millers will usually be empty. We store whatever was submitted
    // (or an empty array) rather than blocking submission on it.
    $selected_millers_raw = $_POST['selected_millers'] ?? '';
    $millers_array = [];
    if ($selected_millers_raw !== '') {
        $decoded = json_decode($selected_millers_raw, true);
        $millers_array = is_array($decoded) ? $decoded : array_map('trim', explode(',', $selected_millers_raw));
    }
    $millers_json = json_encode($millers_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($miller_name === '' || $country === '' || $county_district === '') {
        $flash_message = 'All required miller fields must be filled in.';
    } else {
        $stmt = $con->prepare("SELECT id FROM miller_details WHERE miller_name = ?");
        $stmt->bind_param('s', $miller_name);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $flash_message = "A miller entry for \"$miller_name\" already exists. Edit it instead.";
        } else {
            $created_at = date('Y-m-d H:i:s');

            $stmt = $con->prepare("INSERT INTO miller_details
                (miller_name, miller, country, county_district, currency, tradepoint, created_at)
                VALUES (?,?,?,?,?,'Millers',?)");
            $stmt->bind_param('ssssss',
                $miller_name, $millers_json, $country, $county_district, $currency, $created_at
            );

            if ($stmt->execute()) {
                $flash_message = "Miller \"$miller_name\" added successfully!";
                $flash_type    = 'success';
            } else {
                $flash_message = "Failed to add miller: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

$_SESSION['flash_message'] = $flash_message;
$_SESSION['flash_type']    = $flash_type;

$con->close();
header('Location: tradepoints_boilerplate.php');
exit;