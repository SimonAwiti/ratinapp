<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../admin/includes/config.php'; // DB connection

// Explicitly set character encoding
mysqli_set_charset($con, "utf8mb4");

// Get source ID from query string
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch commodity source details
$source = null;
if ($id > 0) {
    $stmt = $con->prepare("SELECT id, admin0_country, admin1_county_district FROM commodity_sources WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $source = $result->fetch_assoc();
    $stmt->close();
}

if (!$source) {
    echo "<script>alert('Commodity Source not found'); window.location.href='../base/commodity_sources_boilerplate.php';</script>";
    exit;
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin0_country = trim($_POST['admin0_country']);
    $admin1_county_district = trim($_POST['admin1_county_district']);

    // Validate inputs
    if (empty($admin0_country) || empty($admin1_county_district)) {
        $error_message = "Both Country (Admin-0) and County/District (Admin-1) are required.";
    } else {
        // Check for duplicate source (same country and county/district) excluding current source
        $duplicate_check_sql = "SELECT id FROM commodity_sources WHERE admin0_country = ? AND admin1_county_district = ? AND id != ?";
        $duplicate_stmt = $con->prepare($duplicate_check_sql);
        $duplicate_stmt->bind_param('ssi', $admin0_country, $admin1_county_district, $id);
        $duplicate_stmt->execute();
        $duplicate_result = $duplicate_stmt->get_result();
        
        if ($duplicate_result->num_rows > 0) {
            $error_message = "A commodity source with the same country and county/district already exists. Please choose different details.";
        } else {
            $sql = "UPDATE commodity_sources
                    SET admin0_country = ?, admin1_county_district = ?
                    WHERE id = ?";
            $stmt = $con->prepare($sql);
            if ($stmt === false) {
                $error_message = "Failed to prepare statement: " . $con->error;
            } else {
                $stmt->bind_param(
                    'ssi',
                    $admin0_country,
                    $admin1_county_district,
                    $id
                );
                $stmt->execute();

                if ($stmt->errno) {
                    $error_message = "MySQL Error: " . $stmt->error;
                } else {
                    $success_message = "Commodity Source updated successfully!";
                    // Update the $source array with new values to display immediately
                    $source['admin0_country'] = $admin0_country;
                    $source['admin1_county_district'] = $admin1_county_district;
                    // In a real scenario, you might redirect after success, or refresh the data.
                    // For now, we'll keep it on the page to show the success message.
                    // echo "<script>alert('Commodity Source updated successfully'); window.location.href='../base/commodity_sources_boilerplate.php';</script>";
                    // exit;
                }
                $stmt->close();
            }
        }
        $duplicate_stmt->close();
    }
}
$con->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Commodity Source</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f8f8;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            max-width: 700px; /* Slightly narrower than commodity form */
            margin: 0 auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        h2 {
            margin-bottom: 10px;
            color: #333;
        }
        p {
            margin-bottom: 30px;
            color: #666;
        }
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 30px;
            border: none;
            background: transparent;
            cursor: pointer;
            color: #333;
        }
        .close-btn:hover {
            color: rgba(180, 80, 50, 1);
        }
        
        /* Form styling */
        .form-group-full {
            width: 100%;
            display: flex;
            flex-direction: column;
            margin-bottom: 20px;
        }
        label {
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        input {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 15px;
        }
        input:focus {
            outline: none;
            border-color: rgba(180, 80, 50, 0.5);
            box-shadow: 0 0 5px rgba(180, 80, 50, 0.3);
        }
        
        /* Current source info */
        .source-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            border-left: 4px solid rgba(180, 80, 50, 1);
        }
        .source-info h5 {
            margin-bottom: 15px;
            color: rgba(180, 80, 50, 1);
        }
        .source-info p {
            margin: 8px 0;
            color: #666;
            font-size: 14px;
        }
        
        /* Message styling */
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        /* Update button */
        .update-btn {
            background-color: rgba(180, 80, 50, 1);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            width: 100%;
            margin-top: 20px;
        }
        .update-btn:hover {
            background-color: rgba(160, 60, 30, 1);
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
                margin: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="close-btn" onclick="window.location.href='../base/commodity_sources_boilerplate.php'">×</button>
        
        <h2>Edit Commodity Source</h2>
        <p>Update the geographical origin details below</p>
        
        <div class="source-info">
            <h5>Current Source Information</h5>
            <p><strong>ID:</strong> <?= htmlspecialchars($source['id']) ?></p>
            <p><strong>Country (Admin-0):</strong> <?= htmlspecialchars($source['admin0_country'] ?? 'N/A') ?></p>
            <p><strong>County/District (Admin-1):</strong> <?= htmlspecialchars($source['admin1_county_district'] ?? 'N/A') ?></p>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="edit_commodity_source.php?id=<?= $id ?>">
            <div class="form-group-full">
                <label for="admin0_country">Country (Admin-0) *</label>
                <input type="text" id="admin0_country" name="admin0_country" 
                       value="<?= htmlspecialchars($source['admin0_country'] ?? '') ?>" required>
            </div>

            <div class="form-group-full">
                <label for="admin1_county_district">County/District (Admin-1) *</label>
                <input type="text" id="admin1_county_district" name="admin1_county_district" 
                       value="<?= htmlspecialchars($source['admin1_county_district'] ?? '') ?>" required>
            </div>

            <button type="submit" class="update-btn">
                <i class="fa fa-save"></i> Update Source
            </button>
        </form>
    </div>

    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const country = document.getElementById('admin0_country').value.trim();
            const county = document.getElementById('admin1_county_district').value.trim();

            if (!country || !county) {
                e.preventDefault();
                alert('Country (Admin-0) and County/District (Admin-1) are required fields.');
                return false;
            }

            // Confirm update
            const confirmUpdate = confirm('Are you sure you want to update this commodity source?');
            if (!confirmUpdate) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>