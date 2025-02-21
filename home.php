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
// Logout handling
if (isset($_GET['logout'])) {
    session_unset();  // Clear session variables
    session_destroy(); // Destroy session
    header("Location: admin.php"); // Redirect to login page
    exit();
}
// Get user details
$userId = $_SESSION['user_id'];
$userRef = $database->getReference("users/$userId");
$userData = $userRef->getValue();

if (!$userData) {
    session_destroy();
    header("Location: admin.php");
    exit();
}

$username = $userData['username'];
$email = $userData['email'];

// Fetch books from Firebase
$booksRef = $database->getReference("books");
$booksSnapshot = $booksRef->getSnapshot();

$books = [];

foreach ($booksSnapshot->getValue() as $bookId => $bookData) {
    $books[] = [
        'id' => $bookId,
        'title' => $bookData['title'] ?? 'Unknown',
        'author' => $bookData['author'] ?? 'Unknown',
        'genre' => $bookData['genre'] ?? 'Unknown',
        'publication_year' => $bookData['publication_year'] ?? 'N/A',
        'quantity' => $bookData['quantity'] ?? 0
    ];
}

// Sort books alphabetically by title
usort($books, function ($a, $b) {
    return strcmp($a['title'], $b['title']);
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 600px; margin-top: 50px; }
        .card { padding: 20px; border-radius: 10px; }
        .hidden { display: none; }
        .book-list { list-style-type: none; padding: 0; }
        .book-list li { padding: 10px; background: white; margin-bottom: 10px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        #noResults { display: none; color: red; text-align: center; margin-top: 10px; }
	.popup { position: fixed; top: 20%; left: 50%; transform: translate(-50%, -50%); width: 300px; background: white; padding: 20px; border-radius: 10px; box-shadow: 0px 0px 10px #aaa; text-align: center; display: none; }
        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: none; }
    
    .hidden { display: none; }
    .popup-content { text-align: center; }
    </style>
</head>
<body>

<div class="container">
    <div class="card shadow">
        <h4 class="text-center">Library Management</h4>

        <div class="text-end">
            <img src="images/ic_user.png" alt="User Icon" width="50" height="50" style="cursor: pointer;" id="userIcon">
        </div>

        <!-- Search Books Button -->
        <button class="btn btn-primary w-100 mt-3" id="searchBooksButton">Search Books</button>

        <!-- Search Bar (Initially Hidden) -->
        <input type="text" class="form-control mt-2 hidden" id="searchBar" placeholder="Search by Title or Author" onkeyup="searchBooks()">

        <!-- Book List (Hidden Initially) -->
        <ul class="book-list mt-3 hidden" id="bookList"></ul>

        <!-- No Results Message -->
        <p id="noResults">No books found</p>

        <!-- Buttons -->
        <form action="issued_books.php" method="GET">
            <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
            <button type="submit" class="btn btn-secondary w-100 mt-3">Issued Books</button>
        </form>

        <form action="returned_books.php" method="GET">
            <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
            <button type="submit" class="btn btn-secondary w-100 mt-3">Returned Books</button>
        </form>

        <button class="btn btn-danger w-100 mt-3" id="viewFinesButton">View Fines</button>
    </div>
</div>


<!-- Popup Overlay -->
<div class="overlay" id="popupBackground"></div>

<!-- User Info Popup -->
<div class="popup" id="popupLayout">
    <h5>User Information</h5>
    <p><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
    <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
    <button class="btn btn-warning" id="logoutButton">Logout</button>
</div>

<script>
    let books = <?php echo json_encode($books); ?>; // Convert PHP array to JS
	
    document.getElementById("userIcon").addEventListener("click", function() {
        document.getElementById("popupLayout").style.display = "block";
        document.getElementById("popupBackground").style.display = "block";
    });

    document.getElementById("popupBackground").addEventListener("click", function() {
        document.getElementById("popupLayout").style.display = "none";
        this.style.display = "none";
    });

    document.getElementById("logoutButton").addEventListener("click", function() {
	
        window.location.href = "home.php?logout=true";
    });

    // Toggle search bar
    document.getElementById("searchBooksButton").addEventListener("click", function() {
        let searchBar = document.getElementById("searchBar");
        searchBar.classList.toggle("hidden");
        searchBar.value = ""; // Clear input when reopened
        document.getElementById("bookList").classList.add("hidden");
        document.getElementById("noResults").style.display = "none";

        if (!searchBar.classList.contains("hidden")) {
            searchBar.focus();
        }
    });

    function searchBooks() {
        let input = document.getElementById("searchBar").value.toLowerCase();
        let bookList = document.getElementById("bookList");
        let noResults = document.getElementById("noResults");

        bookList.innerHTML = ""; // Clear previous results

        if (input.trim() === "") {
            bookList.classList.add("hidden");
            noResults.style.display = "none";
            return;
        }

        let found = false;
        books.forEach(book => {
            if (book.title.toLowerCase().includes(input) || book.author.toLowerCase().includes(input)) {
                let listItem = document.createElement("li");
                listItem.className = "book-item";
                listItem.innerHTML = `<strong>${book.title}</strong> by ${book.author} (${book.publication_year})<br>Genre: ${book.genre} | Available: ${book.quantity}`;
                bookList.appendChild(listItem);
                found = true;
            }
        });

        if (found) {
            bookList.classList.remove("hidden");
            noResults.style.display = "none";
        } else {
            bookList.classList.add("hidden");
            noResults.style.display = "block";
        }
    }
</script>

</body>
</html>
