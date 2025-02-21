<?php
session_start();
ini_set('max_execution_time', 0);
// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin.php");
    exit();
}

// Firebase SDK Setup
require 'vendor/autoload.php';

use Kreait\Firebase\Factory;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Check if Firebase Database URL and credentials path are set in the session
if (!isset($_SESSION['firebase_database_uri']) || !isset($_SESSION['firebase_credentials_path'])) {
    echo "Firebase Database URI or credentials path is not set!";
    exit();
}

// Get the Firebase Database URL and credentials path from the session
$firebase_database_uri = $_SESSION['firebase_database_uri'];
$firebase_credentials_path = $_SESSION['firebase_credentials_path'];

try {
    // Initialize Firebase with the correct path and specify the database URL
    $firebase = (new Factory)
        ->withServiceAccount($firebase_credentials_path)
        ->withDatabaseUri($firebase_database_uri); // Use the dynamic URL from session
} catch (Exception $e) {
    // Catch any errors related to Firebase initialization
    echo 'Error initializing Firebase: ' . $e->getMessage();
    exit();
}


// Access Firebase Database
$database = $firebase->createDatabase();

// Fetch the total count of books
$booksRef = $database->getReference('books')->getValue();
$bookCount = $booksRef ? count($booksRef) : 0; // If books exist, count them

if (isset($_POST['upload_excel'])) {
    $file = $_FILES['excel_file']['tmp_name'];

    if ($file) {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, true);

        // Loop through Excel data and insert into Firebase
        foreach ($data as $rowIndex => $row) {
            if ($rowIndex == 1) continue; // Skip header row

            $database->getReference('books')->push([
                'date' => $row['A'],
                'acc_num' => $row['B'],
                'call_num' => $row['C'],
                'title' => $row['D'],
                'author' => $row['E'],
                'source' => $row['F'],
                'inv_num' => $row['G'],
                'inv_date' => $row['H'],
                'amount' => $row['I'],
                'publisher' => $row['J'],
                'year_pub' => $row['K'],
                'pages' => $row['L'],
                'book_size' => $row['M'],
                'edition' => $row['N'],
                'cost' => $row['O'],
                'remarks' => $row['P'],
            ]);
        }
        $message = "Excel file uploaded successfully!";
    } else {
        $message = "Failed to upload file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books - Library Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
    <h2 class="my-4">Manage Books</h2>

    <!-- Back Arrow to Dashboard -->
    <a href="dashboard.php" class="btn btn-info mb-4"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    <!-- Display Book Count -->
    <div class="alert alert-primary">
        <h4>Total Books: <?php echo $bookCount; ?></h4>
    </div>

    <?php if (isset($message)) echo "<div class='alert alert-info'>$message</div>"; ?>

    <!-- Upload Excel File -->
    <div class="card p-4 mb-4">
        <h4>Upload Excel File</h4>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Select Excel File</label>
                <input type="file" class="form-control" name="excel_file" accept=".xls,.xlsx" required>
            </div>
            <button type="submit" name="upload_excel" class="btn btn-primary">Upload</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
