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


$database = $firebase->createDatabase();

$bookDetails = null;
$message = "";

// Handle Book Search by Accession Number
if (isset($_POST['search_book'])) {
    $acc_num = trim($_POST['acc_num']);
    
    // Fetch books and find book with matching accession number
    $booksData = $database->getReference('books')->getValue();

    foreach ($booksData as $book_id => $book) {
        if (isset($book['acc_num']) && $book['acc_num'] == $acc_num) {
            $bookDetails = $book;
            $bookDetails['book_id'] = $book_id;
            break;
        }
    }

    if (!$bookDetails) {
        $message = "No book found with this accession number!";
    }
}

// Handle Book Assignment
if (isset($_POST['assign_book'])) {
    $acc_num = $_POST['acc_num'];
    $user_id = $_POST['user_id'];
    $book_id = $_POST['book_id'];
    $issue_date = date("Y-m-d");
    $due_date = date("Y-m-d", strtotime("+7 days"));

    // Update Firebase to mark the book as assigned
    $database->getReference('issued_books')->push([
        'book_id' => $book_id,
        'user_id' => $user_id,
        'acc_num' => $acc_num,
        'issue_date' => $issue_date,
        'due_date' => $due_date
    ]);

    // Update book status in Firebase
    $database->getReference("books/$book_id")->update(['remark' => 'Assigned']);

    $message = "Book assigned successfully!";
    // Fetch updated book details after assignment
	$bookDetails = $database->getReference("books/$book_id")->getValue();
	$bookDetails['book_id'] = $book_id; // Ensure book_id remains accessible
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Book - Library Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container my-4">
    <h2>Issue Book</h2>
    <?php if (!empty($message)) echo "<div class='alert alert-info'>$message</div>"; ?>

    <a href="dashboard.php" class="btn btn-info mb-4">⬅ Back to Dashboard</a>

    <!-- Search Book by Accession Number -->
    <form method="POST">
        <div class="mb-3">
            <label for="acc_num" class="form-label">Enter Accession Number</label>
            <input type="text" class="form-control" name="acc_num" required placeholder="Enter Accession Number">
        </div>
        <button type="submit" name="search_book" class="btn btn-primary">Search</button>
    </form>

    <?php if ($bookDetails): ?>
        <hr>
        <h4>Book Details</h4>
        <p><strong>Title:</strong> <?php echo isset($bookDetails['title']) ? $bookDetails['title'] : 'N/A'; ?></p>
<p><strong>Author:</strong> <?php echo isset($bookDetails['author']) ? $bookDetails['author'] : 'N/A'; ?></p>
<p><strong>Publisher:</strong> <?php echo isset($bookDetails['publisher']) ? $bookDetails['publisher'] : 'N/A'; ?></p>
<p><strong>Year:</strong> <?php echo isset($bookDetails['year_pub']) ? $bookDetails['year_pub'] : 'N/A'; ?></p>

        <p><strong>Status:</strong> 
            <span class="<?php echo isset($bookDetails['remark']) && $bookDetails['remark'] === 'Assigned' ? 'text-danger' : 'text-success'; ?>">
                <?php echo isset($bookDetails['remark']) ? $bookDetails['remark'] : 'Available'; ?>
            </span>
        </p>

        <?php if (!isset($bookDetails['remark']) || $bookDetails['remark'] !== 'Assigned'): ?>
            <!-- Assign to User -->
            <form method="POST">
                <input type="hidden" name="acc_num" value="<?php echo $bookDetails['acc_num']; ?>">
                <input type="hidden" name="book_id" value="<?php echo $bookDetails['book_id']; ?>">

                <div class="mb-3">
                    <label for="user_id" class="form-label">Select User</label>
                    <select class="form-select" name="user_id" required>
                        <option value="" disabled selected>Select a user</option>
                        <?php 
                        $users = $database->getReference('users')->getValue();
                        foreach ($users as $user_id => $user): ?>
                            <option value="<?php echo $user_id; ?>"><?php echo $user['username']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="assign_book" class="btn btn-success">Assign Book</button>
            </form>
        <?php else: ?>
            <p class="text-danger"><strong>This book has already been assigned.</strong></p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
