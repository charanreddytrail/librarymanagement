<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: admin.php");
    exit();
}

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

// Get current logged-in user ID
$userId = $_SESSION['user_id'];

// Fetch issued books
$issuedBooksRef = $database->getReference('issued_books');
$issuedBooksSnapshot = $issuedBooksRef->getSnapshot();

$issuedBooks = [];

foreach ($issuedBooksSnapshot->getValue() as $issuedBookId => $issuedBookData) {
    if (isset($issuedBookData['user_id']) && $issuedBookData['user_id'] === $userId) {
        $returnDate = $issuedBookData['return_date'] ?? 'Not Returned';

        if ($returnDate === 'Not Returned') {
            // Fetch book details
            $bookId = $issuedBookData['book_id'] ?? null;
            $bookData = ($bookId) ? $database->getReference("books/$bookId")->getValue() : [];

            $issuedBooks[] = [
                'title' => $bookData['title'] ?? 'Unknown Book',
                'issue_date' => $issuedBookData['issue_date'] ?? 'N/A',
                'due_date' => $issuedBookData['due_date'] ?? 'N/A',
                'return_date' => $returnDate,
                'fine' => $issuedBookData['fine'] ?? 0
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issued Books</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2 class="text-center">Issued Books</h2>
    <a href="home.php" class="btn btn-primary mb-3">Go to Home</a>
    
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Title</th>
                <th>Issue Date</th>
                <th>Due Date</th>
                <th>Return Date</th>
                <th>Fine</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($issuedBooks)) : ?>
                <?php foreach ($issuedBooks as $book) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($book['title']); ?></td>
                        <td><?php echo htmlspecialchars($book['issue_date']); ?></td>
                        <td><?php echo htmlspecialchars($book['due_date']); ?></td>
                        <td class="text-danger fw-bold"><?php echo htmlspecialchars($book['return_date']); ?></td>
                        <td>₹<?php echo htmlspecialchars($book['fine']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="5" class="text-center text-danger">No issued books found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
