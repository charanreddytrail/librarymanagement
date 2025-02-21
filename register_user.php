  <?php
session_start();

// Make sure to include the Firebase SDK and its dependencies
require 'vendor/autoload.php';  // Correctly include the Composer autoload file

// Use the Firebase namespace after including the necessary files
use Kreait\Firebase\Factory; // Make sure this line is at the top, directly after the PHP opening tag

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

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


    // Get the Firebase Database reference
    $database = $firebase->createDatabase();

    // Collect user input
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = hash('sha256', $_POST['password']); // Using SHA-256 to hash the password

    // Reference to 'users' node
    $users_reference = $database->getReference('users')->getValue();

    $email_exists = false;

    // Check if email already exists
    if ($users_reference) {
        foreach ($users_reference as $user) {
            if (isset($user['email']) && $user['email'] === $email) {
                $email_exists = true;
                break;
            }
        }
    }

    if ($email_exists) {
        $message = "Error: Email already exists. Please use another email.";
    } else {
        // Proceed with registration
        $user_data = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'status' => 'active'
        ];

        $user_reference = $database->getReference('users')->push($user_data);

        if ($user_reference) {
            $message = "User registered successfully!";
        } else {
            $message = "Error: Unable to register user.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration - Library Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container my-4">
    <h2>User Registration</h2>
    <!-- Back Arrow to Dashboard -->
    <a href="dashboard.php" class="btn btn-info mb-4"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    <?php if (isset($message)) echo "<div class='alert alert-info'>$message</div>"; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" name="username" required>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" name="email" required>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" name="password" required>
        </div>

        <button type="submit" class="btn btn-primary">Register</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
