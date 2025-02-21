<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin.php");
    exit();
}

// Firebase SDK Setup
require 'vendor/autoload.php'; // Ensure Firebase PHP SDK is installed

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

// Fetch all users for display
$usersReference = $database->getReference('users');
$users = $usersReference->getValue();

// Delete user if 'Delete' button is clicked
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $user_id_to_delete = $_POST['delete_user_id'];
    
    // Delete user from Firebase
    $usersReference->getChild($user_id_to_delete)->remove();
    
    // Redirect to refresh the page after deletion
    header("Location: manage_users.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Library Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container">
    <h2 class="my-4">Manage Users</h2>
    
    <!-- Back Arrow to Dashboard -->
    <a href="dashboard.php" class="btn btn-info mb-4"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    <!-- Users List -->
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($users): ?>
                <?php foreach ($users as $user_id => $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo isset($user['role']) ? ucfirst($user['role']) : 'Not Set'; ?></td>
                        <td><?php echo isset($user['status']) ? ucfirst($user['status']) : 'Not Set'; ?></td>
                        <td>
                            <a href="edit_user.php?user_id=<?php echo $user_id; ?>" class="btn btn-warning">Edit</a>
                            <form action="manage_users.php" method="POST" style="display:inline;">
                                <input type="hidden" name="delete_user_id" value="<?php echo $user_id; ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                            </form>
                            <a href="activate_user.php?user_id=<?php echo $user_id; ?>" class="btn btn-success">Activate</a>
                            <a href="deactivate_user.php?user_id=<?php echo $user_id; ?>" class="btn btn-secondary">Deactivate</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5">No users available</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
