<?php
session_start();
ob_start();

// Check if the user is logged in
if (!isset($_SESSION['admin_email'])) {
    header("Location: admin.php");
    exit();
}

// Firebase initialization
require_once 'vendor/autoload.php';  // Include the Firebase SDK
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

// Get a reference to the Firebase Realtime Database
$database = $firebase->createDatabase();

// Fetch statistics (total books, users, and fine)
$total_books_ref = $database->getReference('books');
$total_books = $total_books_ref->getSnapshot()->numChildren();  // Counting total books

$total_users_ref = $database->getReference('users');
$total_users = $total_users_ref->getSnapshot()->numChildren();  // Counting total users

// Fetch total fines collected
$sql_total_fine_ref = $database->getReference('issued_books');
$total_fine = 0;
$issued_books = $sql_total_fine_ref->getValue();

if ($issued_books !== null) {
    foreach ($issued_books as $book) {
        if (isset($book['fine']) && $book['fine'] > 0) {
            $total_fine += $book['fine'];
        }
    }
}
$issued_bookss = $database->getReference('issued_books')->getValue();
$total_overdue_books = 0;
$current_date = date("Y-m-d");
if ($issued_bookss) {
    foreach ($issued_bookss as $book) {
        if (!isset($book['return_date']) && isset($book['due_date']) && $book['due_date'] < $current_date) {
            $total_overdue_books++;
        }
    }
}
// Fetch recent activity (e.g., most recent book issues)
$recent_activity_ref = $database->getReference('issued_books')
    ->orderByChild('issue_date')
    ->limitToLast(5);
$recent_activity = $recent_activity_ref->getValue();
// Fetch total users
$total_users = count($database->getReference('users')->getValue());

// Fetch issued books data
$issued_books = $database->getReference('issued_books')->getValue();

// Count issued books that are still pending return
$total_issued_books = 0;
if ($issued_books) {
    foreach ($issued_books as $issued_book) {
        if (!isset($issued_book['return_date'])) { // Book not yet returned
            $total_issued_books++;
        }
    }
}
// Fetch fine details for each user who has been fined
$fine_details = [];
if ($issued_books !== null) {
    foreach ($issued_books as $book) {
        if (isset($book['fine']) && $book['fine'] > 0) {
            // Debugging: Check the current issued book entry
            echo "Processing book issue: <br>";
            echo "User ID: " . $book['user_id'] . "<br>";
            echo "Book ID: " . $book['book_id'] . "<br>";

            // Fetch user details
            $user_ref = $database->getReference('users/' . $book['user_id']);
            $user = $user_ref->getValue();
            // Debugging: Check if user data exists
            if ($user === null) {
                echo "User data not found for user_id: " . $book['user_id'] . "<br>";
                continue;  // Skip to the next book if user not found
            }
            $book_ref = $database->getReference('books/' . $book['book_id']);
            $book_details = $book_ref->getValue();
            // Debugging: Check if book data exists
            if ($book_details === null) {
                echo "Book data not found for book_id: " . $book['book_id'] . "<br>";
                continue;  // Skip to the next fine if book not found
            }

            $book_title = isset($book_details['title']) ? $book_details['title'] : 'Unknown Book Title';
            $username = isset($user['username']) ? $user['username'] : 'Unknown User';

            $fine_details[] = [
                'username' => $username,
                'title' => $book_title,
                'fine' => $book['fine']
            ];
        }
    }
}
// Handle registration request
if (isset($_POST['register'])) {
    $new_email = $_POST['new_email'];
    $new_password = $_POST['new_password'];

    // Sanitize email for Firebase key
    $sanitizedEmail = sanitizeEmail($new_email);

    // Hash the password using password_hash()
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Reference to the Firebase 'admin' node
    $adminRef = $database->getReference('admin');

    // Check if the email already exists
    $adminData = $adminRef->getValue();
    if (isset($adminData[$sanitizedEmail])) {
        echo "<script>alert('Email already exists!');</script>";
    } else {
        // Insert the new admin details (email and hashed password) into Firebase
        $adminRef->getChild($sanitizedEmail)->set([
            'email' => $new_email,
            'password' => $hashed_password
        ]);
        echo "<script>alert('New admin registered successfully!');</script>";
        echo "<script>window.location.href='dashboard.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Library Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap CSS (Add in <head>) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap JS Bundle (Place before </body>) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            height: 100vh;
            background-color: #343a40;
            color: white;
            position: fixed;
            width: 250px;
            top: 0;
            left: 0;
            padding-top: 20px;
        }
        .sidebar a {
            color: white;
            padding: 15px;
            text-decoration: none;
            display: block;
            font-size: 16px;
        }
        .sidebar a:hover {
            background-color: #575d63;
        }
        .content {
            margin-left: 270px;
            padding: 20px;
        }
        .card {
            margin-bottom: 20px;
        }
        .logout-btn {
            position: absolute;
            bottom: 20px;
            left: 20px;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h4 class="text-center text-white">LMS Dashboard</h4>
    <a href="dashboard.php">Home</a>
    <a href="manage_books.php">Manage Books</a>
    <a href="manage_users.php">Manage Users</a>
    <a href="issue_books.php">Issue Books</a>
    <a href="return_books.php">Return Books</a>
    <a href="reports.php">Reports</a>
    <a href="register_user.php">Student Registration</a>
    
    <a href="book_list.php">Book details</a>
    <a href="settings.php">Settings</a>
    <a href="?logout=true" class="btn btn-danger">Logout</a>
</div>

<!-- Main Content -->
<div class="content">
    <h1 class="text-center">Welcome to the Library Management System</h1>

    <div class="row">
        <!-- Total Books Card -->
        <div class="col-md-4">
            <div class="card text-white bg-primary">
                <div class="card-header">Total Books</div>
                <div class="card-body">
                    <h2 class="card-title"><?php echo $total_books; ?></h2>
                </div>
            </div>
        </div>
	<!-- Total Issued Books Card -->
<div class="col-md-4">
    <div class="card text-white bg-warning">
        <div class="card-header">Total Issued Books</div>
        <div class="card-body">
            <h2 class="card-title"><?php echo $total_issued_books; ?></h2>
        </div>
    </div>
</div>

        <!-- Total Users Card -->
        <div class="col-md-4">
            <div class="card text-white bg-success">
                <div class="card-header">Total Users</div>
                <div class="card-body">
                    <h2 class="card-title"><?php echo $total_users; ?></h2>
                </div>
            </div>
        </div>

        <!-- Total Fine Collected Card -->
        <div class="col-md-4">
            <div class="card text-white bg-warning">
                <div class="card-header">Total Fine Collected</div>
                <div class="card-body">
                    <h2 class="card-title">$<?php echo number_format($total_fine, 2); ?></h2>
                </div>
            </div>
        </div>
    </div>
	<!-- Due Date Books Card (Redirects to duedatedbooks.php) -->
<div class="col-md-4">
    <a href="duedatedbooks.php" style="text-decoration: none;">
        <div class="card text-white bg-danger">
            <div class="card-header">Overdue Books</div>
            <div class="card-body">
                <h2 class="card-title"><?php echo $total_overdue_books; ?></h2>
                <p class="card-text">Click to View Overdue Books</p>
            </div>
        </div>
    </a>
</div>
    <!-- Fine Collection Details -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    Fine Collection Details
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Book Title</th>
                                <th>Fine</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($fine_details)) {
                                foreach ($fine_details as $fine_detail) {
                                    echo '<tr>';
                                    echo '<td>' . $fine_detail['username'] . '</td>';
                                    echo '<td>' . $fine_detail['title'] . '</td>';
                                    echo '<td>$' . number_format($fine_detail['fine'], 2) . '</td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="3">No fines collected</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Logout Logic -->
<?php
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit();
}
ob_end_flush(); // End output buffering
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
