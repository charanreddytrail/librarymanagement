<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin.php");
    exit();
}

// Firebase SDK Setup
require 'vendor/autoload.php';

use Kreait\Firebase\Factory;

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
$booksRef = $database->getReference('books')->getValue();

// Convert books data to an array
$books = $booksRef ? array_values($booksRef) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book List - Library Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
    <h2 class="my-4">Library Book List</h2>

    <!-- Back Arrow to Dashboard -->
    <a href="dashboard.php" class="btn btn-info mb-3"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    <?php if (empty($books)): ?>
        <div class="alert alert-warning">No books available.</div>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Publisher</th>
                    <th>Year</th>
                    <th>Pages</th>
                    <th>Edition</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($books as $index => $book): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo $book['title'] ?? 'N/A'; ?></td>
                        <td><?php echo $book['author'] ?? 'N/A'; ?></td>
                        <td><?php echo $book['publisher'] ?? 'N/A'; ?></td>
                        <td><?php echo $book['year_pub'] ?? 'N/A'; ?></td>
                        <td><?php echo $book['pages'] ?? 'N/A'; ?></td>
                        <td><?php echo $book['edition'] ?? 'N/A'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
