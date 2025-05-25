<?php
// edit_datasource.php

// Include your database configuration file
include '../admin/includes/config.php';

$data_source_id = 0; // Initialize with a default
$data_source_name = '';
$countries_covered_array = []; // To store selected countries for the dropdown
$message = ''; // To display success/error messages

// Check if an ID is provided in the URL for editing
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $data_source_id = (int)$_GET['id'];

    // Fetch existing data source details
    if ($con) {
        $stmt = $con->prepare("SELECT id, data_source_name, countries_covered FROM data_sources WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $data_source_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $data_source_name = htmlspecialchars($row['data_source_name']);
                // Convert the comma-separated string back to an array for 'selected' attribute
                $countries_covered_array = explode(", ", $row['countries_covered']);
            } else {
                // If data source not found, redirect to the list page
                echo "<script>alert('Data source not found!'); window.location.href='../base/sidebar.php';</script>";
                exit;
            }
            $stmt->close();
        } else {
            error_log("Error preparing select statement: " . $con->error);
            echo "<script>alert('Database error during fetch.'); window.location.href='../data/datasource_boilerplate.php';</script>";
            exit;
        }
    } else {
        echo "<script>alert('Database connection error.'); window.location.href='../data/datasource_boilerplate.php';</script>";
        exit;
    }
} else {
    // If no ID is provided, redirect back to the data sources list
    echo "<script>alert('No Data Source ID provided for editing!'); window.location.href='../data/datasource_boilerplate.php';</script>";
    exit;
}

// Process the form submission for updating
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit']) && isset($con) && $data_source_id > 0) {
    // Sanitize and validate input
    $new_data_source_name = isset($_POST['data_source_name']) ? mysqli_real_escape_string($con, $_POST['data_source_name']) : '';

    // Handle multiple country selection for update
    $new_countries_covered = [];
    if (isset($_POST['countries_covered']) && is_array($_POST['countries_covered'])) {
        $new_countries_covered = array_map([$con, 'real_escape_string'], $_POST['countries_covered']);
    }
    $new_countries_string = implode(", ", $new_countries_covered);

    // Basic validation
    if (empty($new_data_source_name)) {
        $message = "<div class='error-message'>Data Source Name cannot be empty.</div>";
    } else {
        // Prepare the SQL statement for update
        $sql = "UPDATE data_sources SET data_source_name = ?, countries_covered = ? WHERE id = ?";
        $stmt = $con->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("ssi", $new_data_source_name, $new_countries_string, $data_source_id);

            if ($stmt->execute()) {
                $message = "<div class='success-message'>Data Source updated successfully!</div>";
                // Optionally, re-fetch the updated data to reflect immediately on the form
                $data_source_name = htmlspecialchars($new_data_source_name);
                $countries_covered_array = $new_countries_covered;
            } else {
                error_log("Error updating data source: " . $stmt->error);
                $message = "<div class='error-message'>Error updating record: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } else {
            error_log("Error preparing update statement: " . $con->error);
            $message = "<div class='error-message'>Error preparing statement: " . $con->error . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Data Source</title>
  <style>
    /* Embedded styles from addcommodity.css - Adjust paths if necessary */
    body {
      font-family: Arial, sans-serif;
      background-color: #f8f8f8;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }

    .close-btn {
      position: absolute;
      top: 20px;
      right: 20px;
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
      color: #a45c40;
    }

    .steps {
      padding-right: 40px;
      position: relative;
    }

    .steps::before {
      content: '';
      position: absolute;
      left: 22.5px;
      top: 45px;
      height: calc(250px - 45px + 45px); /* Adjusted for consistency, though only one step for now */
      width: 1px;
      background-color: #ccc;
    }

    .step {
      display: flex;
      align-items: center;
      margin-bottom: 250px; /* Adjusted for consistency, though only one step for now */
      position: relative;
    }

    .step:last-child {
      margin-bottom: 0;
    }

    .step-circle {
      width: 45px;
      height: 45px;
      border-radius: 70%;
      display: flex;
      justify-content: center;
      align-items: center;
      margin-right: 20px;
      font-size: 20px;
      z-index: 1;
      background-color: #d3d3d3;
      color: white;
      position: relative;
    }

    .step-circle::before {
      content: '✓';
      display: none;
    }

    .step-circle.active::before {
      display: block;
    }

    .step-circle.inactive::before {
      content: '';
    }

    .step-circle.active {
      background-color: #a45c40;
    }

    .form-container {
      flex-grow: 1;
      display: flex;
      flex-direction: column;
      gap: 15px;
      height: 100%;
    }

    label {
      font-weight: bold;
      display: block;
      margin-top: 0;
      margin-bottom: 8px;
    }

    input,
    select {
      width: 100%;
      padding: 12px;
      margin-top: 0;
      border: 1px solid #ccc;
      border-radius: 5px;
      font-size: 16px;
      box-sizing: border-box;
    }

    /* .packaging is not needed here as per add_datasource.php */

    .next-btn {
      background-color: #a45c40;
      color: white;
      border: none;
      padding: 12px 20px;
      margin-top: 10px;
      width: 100%;
      cursor: pointer;
      border-radius: 5px;
    }

    /* Custom layout styling */
    .container {
      background: white;
      padding: 60px;
      border-radius: 8px;
      width: 800px; /* Matches add_datasource.php */
      height: 700px; /* Matches add_datasource.php */
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
      display: flex;
      position: relative;
    }

    h2 {
      margin: 0 0 5px 0;
      color: #333;
    }

    p {
      margin: 0 0 15px 0;
      color: #666;
    }

    .form-row {
      display: flex;
      gap: 20px;
      margin-bottom: 15px;
    }

    .form-row .form-group {
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .next-btn:hover {
      background-color: rgba(180, 80, 50, 1);
    }

    .text-muted {
      font-size: 12px;
      color: #777;
      display: block;
      margin-top: 5px;
    }

    .success-message {
      color: green;
      margin-bottom: 15px;
    }

    .error-message {
      color: red;
      margin-bottom: 15px;
    }
  </style>
</head>
<body>
  <div class="container">
    <button class="close-btn" onclick="window.location.href='../base/sidebar.php'">×</button>

    <div class="steps">
      <div class="step">
        <div class="step-circle active"></div>
        <span>Step 1</span>
      </div>
    </div>

    <div class="form-container">
      <h2>Edit Data Source</h2>
      <p>Modify the details below to update the Data Source</p>

      <?php
      // Display messages after processing, if any
      if (!empty($message)) {
          echo $message;
      }
      ?>

      <form method="POST" action="">
        <div class="form-row">
          <div class="form-group">
            <label for="data_source_name">Data Source *</label>
            <input
              type="text"
              name="data_source_name"
              id="data_source_name"
              value="<?php echo $data_source_name; ?>"
              placeholder="e.g., FAO, National Bureau of Statistics"
              required
            />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="countries_covered">Countries Covered *</label>
            <select name="countries_covered[]" id="countries_covered" multiple required>
              <option value="" disabled>Select Countries</option>
              <?php
              $all_countries = ["Ethiopia", "Kenya", "Rwanda", "Tanzania", "Uganda"];
              foreach ($all_countries as $country) {
                  $selected = (in_array($country, $countries_covered_array)) ? 'selected' : '';
                  echo "<option value=\"". htmlspecialchars($country) . "\" $selected>". htmlspecialchars($country) . "</option>";
              }
              ?>
            </select>
            <small class="text-muted">Hold Ctrl/Cmd to select multiple countries.</small>
          </div>
        </div>

        <button type="submit" name="submit" class="next-btn">Update Data Source →</button>
      </form>
    </div>
  </div>
</body>
</html>