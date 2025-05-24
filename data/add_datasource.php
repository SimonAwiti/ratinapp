<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Add Market Price Data</title>
  <style>
    /* Embedded styles from addcommodity.css */
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
      height: calc(250px - 45px + 45px);
      width: 1px;
      background-color: #ccc;
    }

    .step {
      display: flex;
      align-items: center;
      margin-bottom: 250px;
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

    .packaging {
      display: flex;
      align-items: center;
      margin-top: 30px;
    }

    .packaging input {
      flex-grow: 1;
    }

    .add-btn,
    .remove-btn {
      margin-left: 10px;
      cursor: pointer;
      padding: 5px;
      border-radius: 50%;
      font-size: 16px;
      border: none;
      width: 30px;
      height: 30px;
      text-align: center;
    }

    .add-btn {
      background-color: #d9f5d9;
      color: green;
    }

    .remove-btn {
      background-color: #f8d7da;
      color: red;
    }

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
      width: 800px;
      height: 700px;
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
      <h2>Add New Data Source</h2>
      <p>Provide the details below to add new Data Source</p>

      <?php
      // Database configuration
      include '../admin/includes/config.php';
      

      
      // Check connection
      if ($con->connect_error) {
          die("Connection failed: " . $con->connect_error);
      }
      
      // Create table if not exists (run this only once)
      $createTable = "CREATE TABLE IF NOT EXISTS data_sources (
          id INT(11) AUTO_INCREMENT PRIMARY KEY,
          data_source_name VARCHAR(255) NOT NULL,
          countries_covered TEXT NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )";
      
      if (!$con->query($createTable)) {
          echo "<div class='error-message'>Error creating table: " . $con->error . "</div>";
      }
      
      // Process form submission
      if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
          $data_source_name = $con->real_escape_string($_POST['data_source_name']);
          
          // Handle multiple country selection
          $countries_covered = [];
          if (isset($_POST['countries_covered']) && is_array($_POST['countries_covered'])) {
              $countries_covered = array_map([$con, 'real_escape_string'], $_POST['countries_covered']);
          }
          
          $countries_string = implode(", ", $countries_covered);
          
          // Insert data into database
          $sql = "INSERT INTO data_sources (data_source_name, countries_covered) 
                  VALUES ('$data_source_name', '$countries_string')";
          
          if ($con->query($sql)) {
              echo "<div class='success-message'>Data source added successfully!</div>";
          } else {
              echo "<div class='error-message'>Error: " . $sql . "<br>" . $con->error . "</div>";
          }
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
              value="<?php echo isset($_POST['data_source_name']) ? htmlspecialchars($_POST['data_source_name']) : ''; ?>"
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
              <option value="Ethiopia" <?php echo (isset($_POST['countries_covered']) && in_array('Ethiopia', $_POST['countries_covered'])) ? 'selected' : ''; ?>>Ethiopia</option>
              <option value="Kenya" <?php echo (isset($_POST['countries_covered']) && in_array('Kenya', $_POST['countries_covered'])) ? 'selected' : ''; ?>>Kenya</option>
              <option value="Rwanda" <?php echo (isset($_POST['countries_covered']) && in_array('Rwanda', $_POST['countries_covered'])) ? 'selected' : ''; ?>>Rwanda</option>
              <option value="Tanzania" <?php echo (isset($_POST['countries_covered']) && in_array('Tanzania', $_POST['countries_covered'])) ? 'selected' : ''; ?>>Tanzania</option>
              <option value="Uganda" <?php echo (isset($_POST['countries_covered']) && in_array('Uganda', $_POST['countries_covered'])) ? 'selected' : ''; ?>>Uganda</option>
            </select>
            <small class="text-muted">Hold Ctrl/Cmd to select multiple countries.</small>
          </div>
        </div>

        <button type="submit" name="submit" class="next-btn">Done →</button>
      </form>
    </div>
  </div>
</body>
</html>