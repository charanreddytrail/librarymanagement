<?php
session_start();
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


$database = $firebase->createDatabase();

// Fetch issued books and users from Firebase
$issued_books = $database->getReference('issued_books')->getValue();
$users = $database->getReference('users')->getValue();

$outdated_books_list = [];
$current_date = date("Y-m-d");
$fine_per_day = 1; // Fine amount per overdue day (modify if needed)

// Collect overdue books with usernames and fine calculation
if ($issued_books) {
    foreach ($issued_books as $book) {
        if (!isset($book['return_date']) && isset($book['due_date']) && $book['due_date'] < $current_date) {
            $user_id = $book['user_id'];
            $username = isset($users[$user_id]) ? $users[$user_id]['username'] : "Unknown User";

            // Calculate fine
            $due_date = new DateTime($book['due_date']);
            $today = new DateTime($current_date);
            $days_overdue = $due_date->diff($today)->days;
            $fine_amount = $days_overdue * $fine_per_day;

            $book['username'] = $username;
            $book['fine_amount'] = $fine_amount;
            $outdated_books_list[] = $book;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overdue Books</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container my-4">
    <h2 class="text-danger">Overdue Books & Pending Fines</h2>
    <a href="dashboard.php" class="btn btn-primary mb-3">⬅ Back to Dashboard</a>

    <?php if (!empty($outdated_books_list)) { ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Accession Number</th>
                    <th>Issue Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Fine (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($outdated_books_list as $book) { ?>
                    <tr class="table-danger">
                        <td><?php echo $book['username']; ?></td>
                        <td><?php echo $book['acc_num']; ?></td>
                        <td><?php echo $book['issue_date']; ?></td>
                        <td><?php echo $book['due_date']; ?></td>
                        <td><span class="text-danger">Overdue</span></td>
                        <td><strong>₹<?php echo $book['fine_amount']; ?></strong></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } else { ?>
        <p class="text-center">No overdue books.</p>
    <?php } ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
