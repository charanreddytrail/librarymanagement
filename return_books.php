<?php
session_start();
require_once 'vendor/autoload.php';
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
$userDetails = null;
$message = "";

if (isset($_POST['fetch_details'])) {
    $acc_num = $_POST['acc_num'];
    
    $booksRef = $database->getReference('books');
    $books = $booksRef->getValue();
    $bookFound = null;
    
    if ($books) {
        foreach ($books as $book_id => $book) {
            if ($book['acc_num'] == $acc_num) {
                $bookFound = $book;
                $bookFound['book_id'] = $book_id;
                break;
            }
        }
    }
    
    if ($bookFound) {
        $issuedBooksRef = $database->getReference('issued_books');
        $issuedBooks = $issuedBooksRef->getValue();
        
        $issuedBookFound = null;
        if ($issuedBooks) {
            foreach ($issuedBooks as $issue_id => $issuedBook) {
                if ($issuedBook['acc_num'] == $acc_num && empty($issuedBook['return_date'])) {
                    $issuedBookFound = $issuedBook;
                    $issuedBookFound['issue_id'] = $issue_id;
                    break;
                }
            }
        }
        
        if ($issuedBookFound) {
            $userRef = $database->getReference('users/' . $issuedBookFound['user_id']);
            $userDetails = $userRef->getValue();
            
            $bookDetails = $bookFound;
            $bookDetails['due_date'] = $issuedBookFound['due_date'];
            $bookDetails['issue_date'] = $issuedBookFound['issue_date'];
            $bookDetails['issue_id'] = $issuedBookFound['issue_id'];
            $bookDetails['user_id'] = $issuedBookFound['user_id']; // Store user ID for reference
        } else {
            $message = "Book not issued.";
        }
    } else {
        $message = "No book found with this accession number.";
    }
}

if (isset($_POST['return_book'])) {
    $issue_id = $_POST['issue_id'];
    $return_date = date("Y-m-d");
    
    $issuedBooksRef = $database->getReference('issued_books/' . $issue_id);
    $issuedBook = $issuedBooksRef->getValue();
    
    if ($issuedBook && empty($issuedBook['return_date'])) {
        $due_date = $issuedBook['due_date'];
        $fine = 0;
        
        if ($return_date > $due_date) {
            $overdue_days = (strtotime($return_date) - strtotime($due_date)) / (60 * 60 * 24);
            $fine = $overdue_days * 1; // Fine per day
        }
        
        $issuedBooksRef->update([
            'return_date' => $return_date,
            'fine' => $fine
        ]);
        
        $bookRef = $database->getReference('books/' . $issuedBook['book_id']);
        $bookRef->update(['remark' => null]);
        
        $message = "Book returned successfully! Fine: $$fine";
    } else {
        $message = "Issued book not found or already returned!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Book</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container my-4">
    <h2>Return Book</h2>
    <?php if (!empty($message)) echo "<div class='alert alert-info'>$message</div>"; ?>
    <!-- Back Arrow to Dashboard -->
    <a href="dashboard.php" class="btn btn-info mb-4"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    <form method="POST">
        <div class="mb-3">
            <label for="acc_num" class="form-label">Enter Accession Number</label>
            <input type="text" class="form-control" name="acc_num" required>
        </div>
        <button type="submit" name="fetch_details" class="btn btn-primary">Fetch Details</button>
    </form>
    
    <?php if ($bookDetails && $userDetails): ?>
    <h3 class="mt-4">Book Details</h3>
    <ul>
        <li><strong>Title:</strong> <?php echo $bookDetails['title']; ?></li>
        <li><strong>Author:</strong> <?php echo $bookDetails['author']; ?></li>
        <li><strong>Issue Date:</strong> <?php echo $bookDetails['issue_date']; ?></li>
        <li><strong>Due Date:</strong> <?php echo $bookDetails['due_date']; ?></li>
    </ul>
    
    <h3 class="mt-4">Assigned User Details</h3>
    <ul>
        <li><strong>Username:</strong> <?php echo $userDetails['username']; ?></li>
        <li><strong>Email:</strong> <?php echo $userDetails['email']; ?></li>
	<li><strong>Phone:</strong> <?php echo isset($userDetails['phone']) ? $userDetails['phone'] : 'N/A'; ?></li>

        <li><strong>User ID:</strong> <?php echo $bookDetails['user_id']; ?></li>
    </ul>

    <form method="POST">
        <input type="hidden" name="issue_id" value="<?php echo $bookDetails['issue_id']; ?>">
        <button type="submit" name="return_book" class="btn btn-success">Return Book</button>
    </form>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
