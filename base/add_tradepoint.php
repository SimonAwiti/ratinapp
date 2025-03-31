
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Tradepoint</title>
    <link rel="stylesheet" href="assets/add_commodity.css" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f8f8;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background: white;
            padding: 60px;
            border-radius: 8px;
            width: 850px;
            height: 700px; /* Fixed height */
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            position: relative; /* Required for absolute positioning of the close button */
        }
        h2 {
            margin-bottom: 10px; /* Reduce gap below heading */
        }
        p {
            margin-bottom: 10px; /* Reduce gap below paragraph */
        }
        form label:first-of-type {
            margin-top: 10px; /* Ensure label isn't too close to the paragraph */
        }

        .form-container {
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* Ensure spacing between form elements */
            height: 100%;
        }
        .packaging-unit-container {
            flex-grow: 1;
            max-height: 200px; /* Reduce height slightly for better spacing */
            overflow-y: auto; 
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px; /* Ensure spacing between fields */
        }

        .packaging-unit-group {
            display: flex; /* Use flexbox to align items horizontally */
            gap: 10px; /* Add spacing between fields */
            margin-bottom: 15px;
            align-items: flex-end; /* Align fields at the bottom */
        }
        .packaging-unit-group label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .packaging-unit-group input,
        .packaging-unit-group select {
            flex: 1; /* Allow fields to grow and fill available space */
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }
        .remove-btn {
            background-color: #f8d7da;
            color: red;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
        }
        .remove-btn:hover {
            background-color: #f5c6cb;
        }
        .add-more-btn {
            background-color: #d9f5d9;
            color: green;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 5px;
            margin-top: 10px;
        }
        .add-more-btn:hover {
            background-color: #c4e6c4;
        }
        #variety {
            margin-bottom: 15px; /* Adjust spacing as needed */
        }
        .selection {
            background: #f7f7d8;
            padding: 10px;
            border-radius: 20px;
            display: flex;
            justify-content: center;
            align-items: center; /* Ensures proper vertical alignment */
            gap: 120px; /* Adds space between elements */
            flex-wrap: nowrap; /* Ensures all items remain in one row */
        }

        .selection label {
            display: flex;
            align-items: center;
            gap: 8px; /* Adds space between the radio button and text */
        }
        .steps::before {
            content: '';
            position: absolute;
            left: 22.5px; /* Align with step circles */
            top: 45px; /* Start from bottom of Step 1 */
            height: calc(100% - 45px - 100px); /* Adjust height to stop at Step 3 */
            width: 1px;
            background-color: #ccc; /* Line color */
        }


        .selection input[type="radio"] {
    margin: 0;
    width: 16px;
    height: 16px;
    vertical-align: middle;
}



    </style>
</head>
<body>
    <div class="container">
        <!-- Close button on the top right -->
        <button class="close-btn" onclick="window.location.href='dashboard.php'">×</button>

        <div class="steps">
            <div class="step">
                <div class="step-circle active"></div>
                <span>Step 1</span>
            </div>
            <div class="step">
                <div class="step-circle inactive"></div>
                <span>Step 2</span>
            </div>
            <div class="step">
                <div class="step-circle inactive"></div>
                <span>Step 3</span>
            </div>
        </div>
        <div class="form-container">
            <h2>Add Tradepoint</h2>
            <p>Provide the necessary details to add a new tradepoint</p>
            <div class="selection">
                <label><input type="radio" name="tradepoint" checked> Markets</label>
                <label><input type="radio" name="tradepoint"> Border Points</label>
                <label><input type="radio" name="tradepoint"> Millers</label>
            </div>
            <form method="POST" action="add_commodity.php">
                <label for="market-name">Name of market *</label>
                <input type="text" id="market_name" name="market_name" required>
                <label for="category">Market category *</label>
                <select id="category" name="category" required>
                    <option value="">Select category</option>
                    <option value="Oil seeds">Oil seeds</option>
                    <option value="Pulses">Pulses</option>
                    <option value="Cereals">Cereals</option>
                </select>
                <label for="type">Market type *</label>
                <select id="type" name="type" required>
                    <option value="">Select type</option>
                    <option value="Oil seeds">Oil seeds</option>
                    <option value="Pulses">Pulses</option>
                    <option value="Cereals">Cereals</option>
                </select>
                <label for="country">Country (Admin 0) *</label>
                <input type="text" id="country" name="country" required>
                <label for="county/district">County/ District (Admin 1) *</label>
                <input type="text" id="county/district" name="county/district" required>

                <button type="submit" class="next-btn">Next &rarr;</button>
            </form>
        </div>
    </div>

</body>
</html>