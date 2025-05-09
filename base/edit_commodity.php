<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../admin/includes/config.php'; // DB connection

// Explicitly set character encoding
mysqli_set_charset($con, "utf8mb4");

// Get commodity ID from query string
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch commodity details
$commodity = null;
if ($id > 0) {
    $stmt = $con->prepare("SELECT * FROM commodities WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $commodity = $result->fetch_assoc();
    $stmt->close();
}

if (!$commodity) {
    die("Commodity not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commodity_name = $_POST['commodity_name'];
    $category_id = (int)$_POST['category']; // Explicitly cast to integer
    $variety = $_POST['variety'];

    // Initialize packaging and unit arrays to empty if not set
    $packaging_array = $_POST['packaging'] ?? [];
    $unit_array = $_POST['unit'] ?? [];

    $hs_code = $_POST['hs_code'];
    $commodity_alias = $_POST['commodity_alias'];
    $country = $_POST['country'];

    $image_url = $commodity['image_url']; // Keep current image if no new upload
    if (isset($_FILES['commodity_image']) && $_FILES['commodity_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        $image_name = basename($_FILES['commodity_image']['name']);
        $image_path = $upload_dir . $image_name;
        if (move_uploaded_file($_FILES['commodity_image']['tmp_name'], $image_path)) {
            $image_url = $image_path;
        }
    }

    $combined_units = [];
    if (is_array($packaging_array) && is_array($unit_array) && count($packaging_array) === count($unit_array)) {
        for ($i = 0; $i < count($packaging_array); $i++) {
            $combined_units[] = [
                'size' => $packaging_array[$i],
                'unit' => $unit_array[$i]
            ];
        }
    }

 
    $units_json = trim(json_encode($combined_units));
    $sql = "UPDATE commodities
            SET commodity_name = ?, category_id = ?, variety = ?, units = CAST(? AS JSON), hs_code = ?, commodity_alias = ?, country = ?, image_url = ?
            WHERE id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param(
        'sisissssi',
        $commodity_name,
        $category_id,
        $variety,
        $units_json,
        $hs_code,
        $commodity_alias,
        $country,
        $image_url,
        $id
    );
    $stmt->execute();

    if ($stmt->errno) {
        echo "MySQL Error: " . $stmt->error . "<br>";
        echo "SQL Query: " . $sql . "<br>";
        echo "Bound Parameters: ";
        var_dump([
            $commodity_name,
            $category_id,
            $variety,
            $units_json,
            $hs_code,
            $commodity_alias,
            $country,
            $image_url,
            $id
        ]);
        exit;
    }

    header('Location: sidebar.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Commodity</title>
    <link rel="stylesheet" href="assets/edit_commodity.css" />

</head>
<body>
<div class="container">
    <button class="close-btn" onclick="window.location.href='commodities.php'">×</button>

    <div class="form-container">
        <h2>Edit Commodity</h2>
        <p>Update the details of the commodity</p>
        <form method="POST" action="edit_commodity.php?id=<?= $id ?>" enctype="multipart/form-data">
            <label for="category">Category *</label>
            <select id="category" name="category" required>
                <option value="1" <?= $commodity['category_id'] === 1 ? 'selected' : '' ?>>Oil seeds</option>
                <option value="2" <?= $commodity['category_id'] === 2 ? 'selected' : '' ?>>Pulses</option>
                <option value="3" <?= $commodity['category_id'] === 3 ? 'selected' : '' ?>>Cereals</option>
                </select>

            <label for="commodity-name">Commodity name *</label>
            <input type="text" id="commodity-name" name="commodity_name" value="<?= htmlspecialchars($commodity['commodity_name'] ?? '') ?>" required>

            <label for="variety">Variety</label>
            <input type="text" id="variety" name="variety" value="<?= htmlspecialchars($commodity['variety'] ?? '') ?>">

            <label>Packaging & Unit</label>
            <?php
            $units = json_decode($commodity['units'], true);
            if ($units && is_array($units)) {
                foreach ($units as $index => $unit) {
                    $size = htmlspecialchars($unit['size'] ?? '');
                    $unit_val = htmlspecialchars($unit['unit'] ?? '');
                    echo '
                    <div class="form-row form-row-3">
                        <div class="packaging-unit-group">
                            <label for="packaging' . $index . '">Size</label>
                            <input type="text" name="packaging[]" id="packaging' . $index . '" value="' . $size . '">
                        </div>
                        <div class="packaging-unit-group">
                            <label for="unit' . $index . '">Unit</label>
                            <input type="text" name="unit[]" id="unit' . $index . '" value="' . $unit_val . '">
                        </div>
                        <button type="button" onclick="this.parentElement.remove()">Remove</button>
                    </div>';
                }
            } else {
                // Display one empty row if no data
                echo '
                <div class="form-row form-row-3">
                    <div class="packaging-unit-group">
                        <label>Size</label>
                        <input type="text" name="packaging[]" value="">
                    </div>
                    <div class="packaging-unit-group">
                        <label>Unit</label>
                        <input type="text" name="unit[]" value="">
                    </div>
                    <button type="button" onclick="this.parentElement.remove()">Remove</button>
                </div>';
            }
            ?>

            <button type="button" id="addUnitBtn" onclick="addUnitRow()">Add More</button>

            <label for="hs_code">HS Code</label>
            <input type="text" name="hs_code" id="hs_code" value="<?= htmlspecialchars($commodity['hs_code'] ?? '') ?>">

            <label for="commodity_alias">Alias</label>
            <input type="text" name="commodity_alias" id="commodity_alias" value="<?= htmlspecialchars($commodity['commodity_alias'] ?? '') ?>">

            <label for="country">Country</label>
            <input type="text" name="country" id="country" value="<?= htmlspecialchars($commodity['country'] ?? '') ?>">

            <label for="commodity_image">Commodity Image</label>
            <input type="file" name="commodity_image" class="file-input">

            <div class="button-container">
                <button type="submit" class="update-btn">Update</button>
                <button type="button" class="cancel-btn" onclick="window.location.href='commodities.php'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function addUnitRow() {
    const row = document.createElement('div');
    row.className = 'form-row form-row-3';
    row.innerHTML = `
        <div class="packaging-unit-group">
            <label>Size</label>
            <input type="text" name="packaging[]" value="">
        </div>
        <div class="packaging-unit-group">
            <label>Unit</label>
            <input type="text" name="unit[]" value="">
        </div>
        <button type="button" onclick="this.parentElement.remove()">Remove</button>
    `;
    document.querySelector('.form-container form').insertBefore(row, document.getElementById('addUnitBtn'));
}
</script>
</body>
</html>