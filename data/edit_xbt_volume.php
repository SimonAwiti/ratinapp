<?php
// edit_xbt_volume.php
include '../admin/includes/config.php';

// Initialize variables
$border_points = [];
$commodities = [];
$categories = [];
$data_sources = [];
$volume_data = [];
$errors = [];

// Fetch all necessary data from database
if (isset($con)) {
    // Get border points
    $border_result = $con->query("SELECT id, name FROM border_points");
    while ($row = $border_result->fetch_assoc()) {
        $border_points[] = $row;
    }

    // Get commodities with varieties
    $commodities_result = $con->query("SELECT id, commodity_name, variety FROM commodities");
    while ($row = $commodities_result->fetch_assoc()) {
        $commodities[] = $row;
    }

    // Get categories
    $categories_result = $con->query("SELECT id, name FROM commodity_categories");
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }

    // Get data sources
    $data_sources_result = $con->query("SELECT id, data_source_name FROM data_sources");
    while ($row = $data_sources_result->fetch_assoc()) {
        $data_sources[] = $row;
    }
}

// Get the ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch the existing volume data
if ($id > 0) {
    $stmt = $con->prepare("SELECT * FROM xbt_volumes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $volume_data = $result->fetch_assoc();
    
    if (!$volume_data) {
        die("Record not found");
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    // Sanitize and validate input
    $country = mysqli_real_escape_string($con, $_POST['country']);
    $border_id = (int)$_POST['border'];
    $commodity_id = (int)$_POST['commodity'];
    $category_id = (int)$_POST['category'];
    $variety = mysqli_real_escape_string($con, $_POST['variety']);
    $volume = (float)$_POST['volume'];
    $source = mysqli_real_escape_string($con, $_POST['source']);
    $destination = mysqli_real_escape_string($con, $_POST['destination']);
    $data_source_id = (int)$_POST['data_source'];

    // Validate required fields
    if (empty($country) || $border_id <= 0 || $commodity_id <= 0 || 
        $category_id <= 0 || $volume <= 0 || empty($source) || 
        empty($destination) || $data_source_id <= 0) {
        $errors[] = "Please fill all required fields with valid values.";
    }

    // If no errors, update the record
    if (empty($errors)) {
        // Get names for foreign key relationships
        $border_name = "";
        $commodity_name = "";
        $category_name = "";
        $data_source_name = "";

        // Get border name
        $stmt = $con->prepare("SELECT name FROM border_points WHERE id = ?");
        $stmt->bind_param("i", $border_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $border_name = $result->fetch_assoc()['name'];
        }

        // Get commodity name
        $stmt = $con->prepare("SELECT commodity_name FROM commodities WHERE id = ?");
        $stmt->bind_param("i", $commodity_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $commodity_name = $result->fetch_assoc()['commodity_name'];
        }

        // Get category name
        $stmt = $con->prepare("SELECT name FROM commodity_categories WHERE id = ?");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $category_name = $result->fetch_assoc()['name'];
        }

        // Get data source name
        $stmt = $con->prepare("SELECT data_source_name FROM data_sources WHERE id = ?");
        $stmt->bind_param("i", $data_source_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $data_source_name = $result->fetch_assoc()['data_source_name'];
        }

        // Update the record
        $stmt = $con->prepare("UPDATE xbt_volumes SET 
            country = ?,
            border_id = ?,
            border_name = ?,
            commodity_id = ?,
            commodity_name = ?,
            category_id = ?,
            category_name = ?,
            variety = ?,
            volume = ?,
            source = ?,
            destination = ?,
            data_source_id = ?,
            data_source_name = ?
            WHERE id = ?");

        $stmt->bind_param("sisisissdssisi", 
            $country, $border_id, $border_name, $commodity_id, $commodity_name,
            $category_id, $category_name, $variety, $volume, $source,
            $destination, $data_source_id, $data_source_name, $id);

        if ($stmt->execute()) {
            echo "<script>alert('XBT Volume updated successfully'); window.location.href='../base/commodities_boilerplate.php';</script>";
        } else {
            $errors[] = "Error updating record: " . $con->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit XBT Volume</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .error {
            color: red;
            font-size: 0.9em;
        }
        .btn-primary {
            background-color: rgba(180, 80, 50, 1);
            border-color: rgba(180, 80, 50, 1);
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Edit XBT Volume</h2>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="country">Country *</label>
                        <select name="country" id="country" class="form-control" required>
                            <option value="">Select Country</option>
                            <option value="Kenya" <?= ($volume_data['country'] ?? '') == 'Kenya' ? 'selected' : '' ?>>Kenya</option>
                            <option value="Uganda" <?= ($volume_data['country'] ?? '') == 'Uganda' ? 'selected' : '' ?>>Uganda</option>
                            <option value="Tanzania" <?= ($volume_data['country'] ?? '') == 'Tanzania' ? 'selected' : '' ?>>Tanzania</option>
                            <option value="Rwanda" <?= ($volume_data['country'] ?? '') == 'Rwanda' ? 'selected' : '' ?>>Rwanda</option>
                            <option value="Burundi" <?= ($volume_data['country'] ?? '') == 'Burundi' ? 'selected' : '' ?>>Burundi</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="border">Border Point *</label>
                        <select name="border" id="border" class="form-control" required>
                            <option value="">Select Border Point</option>
                            <?php foreach ($border_points as $border): ?>
                                <option value="<?= $border['id'] ?>" 
                                    <?= ($volume_data['border_id'] ?? '') == $border['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($border['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="commodity">Commodity *</label>
                        <select name="commodity" id="commodity" class="form-control" required>
                            <option value="">Select Commodity</option>
                            <?php foreach ($commodities as $commodity): ?>
                                <option value="<?= $commodity['id'] ?>" 
                                    <?= ($volume_data['commodity_id'] ?? '') == $commodity['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($commodity['commodity_name']) ?>
                                    <?= !empty($commodity['variety']) ? ' (' . htmlspecialchars($commodity['variety']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select name="category" id="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>" 
                                    <?= ($volume_data['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="variety">Variety</label>
                        <input type="text" name="variety" id="variety" class="form-control" 
                               value="<?= htmlspecialchars($volume_data['variety'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="volume">Volume (MT) *</label>
                        <input type="number" step="0.01" name="volume" id="volume" class="form-control" 
                               value="<?= htmlspecialchars($volume_data['volume'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="source">Source Country *</label>
                        <select name="source" id="source" class="form-control" required>
                            <option value="">Select Source</option>
                            <option value="Kenya" <?= ($volume_data['source'] ?? '') == 'Kenya' ? 'selected' : '' ?>>Kenya</option>
                            <option value="Uganda" <?= ($volume_data['source'] ?? '') == 'Uganda' ? 'selected' : '' ?>>Uganda</option>
                            <option value="Tanzania" <?= ($volume_data['source'] ?? '') == 'Tanzania' ? 'selected' : '' ?>>Tanzania</option>
                            <option value="Rwanda" <?= ($volume_data['source'] ?? '') == 'Rwanda' ? 'selected' : '' ?>>Rwanda</option>
                            <option value="Burundi" <?= ($volume_data['source'] ?? '') == 'Burundi' ? 'selected' : '' ?>>Burundi</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="destination">Destination Country *</label>
                        <select name="destination" id="destination" class="form-control" required>
                            <option value="">Select Destination</option>
                            <option value="Kenya" <?= ($volume_data['destination'] ?? '') == 'Kenya' ? 'selected' : '' ?>>Kenya</option>
                            <option value="Uganda" <?= ($volume_data['destination'] ?? '') == 'Uganda' ? 'selected' : '' ?>>Uganda</option>
                            <option value="Tanzania" <?= ($volume_data['destination'] ?? '') == 'Tanzania' ? 'selected' : '' ?>>Tanzania</option>
                            <option value="Rwanda" <?= ($volume_data['destination'] ?? '') == 'Rwanda' ? 'selected' : '' ?>>Rwanda</option>
                            <option value="Burundi" <?= ($volume_data['destination'] ?? '') == 'Burundi' ? 'selected' : '' ?>>Burundi</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="data_source">Data Source *</label>
                        <select name="data_source" id="data_source" class="form-control" required>
                            <option value="">Select Data Source</option>
                            <?php foreach ($data_sources as $source): ?>
                                <option value="<?= $source['id'] ?>" 
                                    <?= ($volume_data['data_source_id'] ?? '') == $source['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($source['data_source_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group text-end">
                <button type="submit" name="submit" class="btn btn-primary">Update</button>
                <a href="../base/commodities_boilerplate.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        // Auto-select category when commodity is selected
        document.getElementById('commodity').addEventListener('change', function() {
            const commodityId = this.value;
            if (commodityId) {
                fetch(`../data/get_commodity_category.php?id=${commodityId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.category_id) {
                            document.getElementById('category').value = data.category_id;
                        }
                    });
            }
        });

        // Initialize form with existing data
        document.addEventListener('DOMContentLoaded', function() {
            // If editing, ensure dropdowns match the data
            const commoditySelect = document.getElementById('commodity');
            if (commoditySelect.value) {
                // Trigger change event to auto-select category
                commoditySelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>