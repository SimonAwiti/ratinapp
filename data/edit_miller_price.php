<?php
// edit_miller_price.php
include '../admin/includes/config.php';

// Initialize variables
$price_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$price_data = [];
$towns = [];
$commodities = [];
$data_sources = [];
$countries = ['Kenya', 'Uganda', 'Tanzania', 'Rwanda', 'Burundi'];

// Fetch miller price data
if ($price_id > 0 && isset($con)) {
    $stmt = $con->prepare("SELECT * FROM miller_prices WHERE id = ?");
    $stmt->bind_param("i", $price_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $price_data = $result->fetch_assoc();
}

// Fetch reference data
if (isset($con)) {
    // Fetch towns from miller_details
    $towns_query = "SELECT DISTINCT miller_name FROM miller_details ORDER BY miller_name";
    $towns_result = $con->query($towns_query);
    while ($row = $towns_result->fetch_assoc()) {
        $towns[] = $row['miller_name'];
    }

    // Fetch commodities with varieties
    $commodities_query = "SELECT id, commodity_name, variety FROM commodities ORDER BY commodity_name";
    $commodities_result = $con->query($commodities_query);
    while ($row = $commodities_result->fetch_assoc()) {
        $commodities[] = $row;
    }

    // Fetch data sources
    $data_sources_query = "SELECT id, data_source_name FROM data_sources ORDER BY data_source_name";
    $data_sources_result = $con->query($data_sources_query);
    while ($row = $data_sources_result->fetch_assoc()) {
        $data_sources[] = $row;
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    // Sanitize input
    $country = mysqli_real_escape_string($con, $_POST['country']);
    $town = mysqli_real_escape_string($con, $_POST['town']);
    $commodity_id = (int)$_POST['commodity'];
    $price = (float)$_POST['price'];
    $data_source_id = (int)$_POST['data_source'];
    $date = mysqli_real_escape_string($con, $_POST['date']);

    // Validate required fields
    if (empty($country) || empty($town) || $commodity_id <= 0 || 
        $price <= 0 || $data_source_id <= 0 || empty($date)) {
        echo "<script>alert('Please fill all required fields with valid values.'); window.history.back();</script>";
        exit;
    }

    // Get commodity name (variety is not stored in miller_prices table)
    $commodity_name = "";
    $stmt = $con->prepare("SELECT commodity_name FROM commodities WHERE id = ?");
    $stmt->bind_param("i", $commodity_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $commodity_name = $result->fetch_assoc()['commodity_name'];
    }

    // Get data source name
    $data_source_name = "";
    $stmt = $con->prepare("SELECT data_source_name FROM data_sources WHERE id = ?");
    $stmt->bind_param("i", $data_source_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $data_source_name = $result->fetch_assoc()['data_source_name'];
    }

    // Prepare date values
    $date_posted = date('Y-m-d H:i:s', strtotime($date));
    $day = date('d', strtotime($date));
    $month = date('m', strtotime($date));
    $year = date('Y', strtotime($date));

    // Update record (without variety field)
    $stmt = $con->prepare("UPDATE miller_prices SET 
                          country = ?,
                          town = ?,
                          commodity_id = ?,
                          commodity_name = ?,
                          price = ?,
                          data_source_id = ?,
                          data_source_name = ?,
                          date_posted = ?,
                          day = ?,
                          month = ?,
                          year = ?
                          WHERE id = ?");
    
    $stmt->bind_param("ssisdsissiii", 
        $country,
        $town,
        $commodity_id,
        $commodity_name,
        $price,
        $data_source_id,
        $data_source_name,
        $date_posted,
        $day,
        $month,
        $year,
        $price_id);
    
    if ($stmt->execute()) {
        echo "<script>alert('Miller price updated successfully'); window.location.href='../base/sidebar.php?page=millerprices';</script>";
    } else {
        echo "<script>alert('Error updating miller price: " . $con->error . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Miller Price</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f8f8;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            width: 100%;
            max-width: 800px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        h2 {
            margin-bottom: 20px;
            color: #8B4513;
        }
        .form-label {
            font-weight: 600;
        }
        .btn-primary {
            background-color: rgba(180, 80, 50, 1);
            border-color: rgba(180, 80, 50, 1);
        }
        .btn-primary:hover {
            background-color: rgba(180, 80, 50, 0.9);
            border-color: rgba(180, 80, 50, 0.9);
        }
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 24px;
            color: #333;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="../base/sidebar.php?page=millerprices" class="close-btn">×</a>
        <h2>Edit Miller Price</h2>
        
        <form method="POST" action="">
            <input type="hidden" name="price_id" value="<?= $price_id ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="country" class="form-label">Country *</label>
                    <select name="country" id="country" class="form-select" required>
                        <option value="">Select Country</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?= htmlspecialchars($country) ?>" <?= isset($price_data['country']) && $price_data['country'] == $country ? 'selected' : '' ?>>
                                <?= htmlspecialchars($country) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="town" class="form-label">Town *</label>
                    <select name="town" id="town" class="form-select" required>
                        <option value="">Select Town</option>
                        <?php foreach ($towns as $town): ?>
                            <option value="<?= htmlspecialchars($town) ?>" <?= isset($price_data['town']) && $price_data['town'] == $town ? 'selected' : '' ?>>
                                <?= htmlspecialchars($town) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="commodity" class="form-label">Commodity *</label>
                    <select name="commodity" id="commodity" class="form-select" required>
                        <option value="">Select Commodity</option>
                        <?php foreach ($commodities as $commodity): ?>
                            <option value="<?= $commodity['id'] ?>" <?= isset($price_data['commodity_id']) && $price_data['commodity_id'] == $commodity['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($commodity['commodity_name']) ?>
                                <?= !empty($commodity['variety']) ? ' (' . htmlspecialchars($commodity['variety']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="price" class="form-label">Price (Local Currency) *</label>
                    <input type="number" step="0.01" name="price" id="price" class="form-control" 
                           value="<?= isset($price_data['price']) ? htmlspecialchars($price_data['price']) : '' ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="data_source" class="form-label">Data Source *</label>
                    <select name="data_source" id="data_source" class="form-select" required>
                        <option value="">Select Data Source</option>
                        <?php foreach ($data_sources as $source): ?>
                            <option value="<?= $source['id'] ?>" <?= isset($price_data['data_source_id']) && $price_data['data_source_id'] == $source['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($source['data_source_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="date" class="form-label">Date *</label>
                    <input type="date" name="date" id="date" class="form-control" 
                           value="<?= isset($price_data['date_posted']) ? date('Y-m-d', strtotime($price_data['date_posted'])) : date('Y-m-d') ?>" required>
                </div>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" name="update" class="btn btn-primary btn-lg">Update Miller Price</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // When country changes, update currency symbol
            document.getElementById('country').addEventListener('change', function() {
                const currencySymbols = {
                    'Kenya': 'KES',
                    'Uganda': 'UGX',
                    'Tanzania': 'TZS',
                    'Rwanda': 'RWF',
                    'Burundi': 'BIF'
                };
                const symbol = currencySymbols[this.value] || '';
                document.querySelector('label[for="price"]').textContent = `Price (${symbol}) *`;
            });

            // Trigger change event to set initial currency symbol
            const countrySelect = document.getElementById('country');
            if (countrySelect.value) {
                countrySelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>