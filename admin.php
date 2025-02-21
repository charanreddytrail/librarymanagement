<?php
session_start();

// Firebase initialization
require_once 'vendor/autoload.php';  // Include the Firebase SDK
use Kreait\Firebase\Factory;

// Load Firebase credentials from Railway environment variables
$firebaseJson = getenv('FIREBASE_KEY');

if (!$firebaseJson) {
    die("Error: Firebase key not found in environment variables.");
}

// Save Firebase credentials to a temporary file
$firebaseKeyPath = sys_get_temp_dir() . '/firebase-key.json';
file_put_contents($firebaseKeyPath, $firebaseJson);
try {
    // Initialize Firebase with the correct path and specify the database URL
     $firebase = (new Factory)
        ->withServiceAccount($firebaseKeyPath)
        ->withDatabaseUri('https://library-management-syste-d7b1f-default-rtdb.asia-southeast1.firebasedatabase.app/');

    // Store Firebase details in session
    $_SESSION['firebase_database_uri'] = 'https://library-management-syste-d7b1f-default-rtdb.asia-southeast1.firebasedatabase.app/';
    $_SESSION['firebase_credentials_path'] = $firebaseKeyPath; // Temporary file path
} catch (Exception $e) {
    // Catch any errors related to Firebase initialization
    echo 'Error initializing Firebase: ' . $e->getMessage();
    exit();
}

// Get a reference to the Firebase Realtime Database
$database = $firebase->createDatabase();  // Corrected to use createDatabase()


// ✅ Function to sanitize email for Firebase admin keys
function sanitizeEmail($email) {
    return str_replace(['@', '.'], ['_at_', '_dot_'], $email);
}

// ✅ Function to hash the password (SHA-256)
function hashPassword($password) {
    return hash('sha256', $password);
}

// Handle login request
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $hashedPassword = hashPassword($password); // Hash password before comparison

    // ✅ Admin Login (Uses sanitized email as key)
    $sanitizedEmail = sanitizeEmail($email);

    // Reference to the Firebase 'admin' node
    $adminRef = $database->getReference('admin');

    // Fetch the admin details from Firebase
    $adminData = $adminRef->getValue();

     // Check if the admin email exists
    if (isset($adminData[$sanitizedEmail])) {
        // Verify the entered password against the stored hashed password
        if (password_verify($password, $adminData[$sanitizedEmail]['password'])) {
            // If passwords match, log in the user and redirect to dashboard
            $_SESSION['admin_id'] = $sanitizedEmail;
            $_SESSION['admin_email'] = $email;  // Store email in session
            header("Location: dashboard.php");  // Redirect to dashboard
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "Email not found!";
    }

    // ✅ User Login (Users have auto-generated keys)
    $usersRef = $database->getReference('users');
    $usersData = $usersRef->getValue();

    if ($usersData) {
        foreach ($usersData as $key => $user) {
            if ($user['email'] === $email && $user['password'] === $hashedPassword) {
                $_SESSION['user_id'] = $key;
                $_SESSION['user_email'] = $email;
                $_SESSION['username'] = $user['username']; // Store username in session
                header("Location: home.php");
                exit();
            }
        }
    }

    // ❌ If No Match Found
    $error = "Invalid email or password!";
}
/*// Handle registration request
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
        $error = "Email already exists!";
    } else {
        // Insert the new admin details (email and hashed password) into Firebase
        $adminRef->getChild($sanitizedEmail)->set([
            'email' => $new_email,
            'password' => $hashed_password
        ]);
        $success = "New admin registered successfully!";
    }
}*/
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            background-image: url('images/college.jpg'); /* Path to your image in the project folder */
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            background-repeat: no-repeat;
        }
    </style>
</head>
<body class="d-flex justify-content-center align-items-center vh-100 bg-light">

<div class="card p-4 shadow" style="width: 350px;">
    <h4 class="text-center">LMS Login</h4>
    <?php if (isset($error)) echo "<p class='text-danger text-center'>$error</p>"; ?>
    <?php if (isset($success)) echo "<p class='text-success text-center'>$success</p>"; ?>

    <!-- Login Form -->
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" name="password" required>
        </div>
        <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
    </form>
	<?php /*<div class="text-center mt-3">
        <a href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#registerForm">New Admin? Register Here</a>
    </div>
   <!-- Link to Registration Form -->
  <div class="collapse mt-3" id="registerForm">
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">New Email</label>
                <input type="email" class="form-control" name="new_email" required>
            </div>
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" class="form-control" name="new_password" required>
            </div>
            <button type="submit" name="register" class="btn btn-success w-100">Register</button>
        </form>
    </div> */ ?>
</div>

<!-- Bootstrap JS (for Collapse functionality) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>