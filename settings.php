<?php
session_start();
ob_start();

// Check if the user is logged in
if (!isset($_SESSION['admin_email'])) {
    header("Location: admin.php");
    exit();
}

// Firebase initialization
require_once 'vendor/autoload.php';
use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\AuthException;
use Kreait\Firebase\Exception\DatabaseException;

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
$auth = $firebase->createAuth();

// Function to sanitize email for Firebase keys
function sanitizeEmail($email) {
    return str_replace(['@', '.'], ['_at_', '_dot_'], $email);
}

// Get the logged-in admin's email
$admin_email = $_SESSION['admin_email'];
$sanitizedEmail = sanitizeEmail($admin_email);

// Firebase references
$adminRef = $database->getReference('admin/' . $sanitizedEmail);
$transactionRef = $database->getReference('transaction');

// Fetch admin details
$adminData = $adminRef->getValue();
$transactionData = $transactionRef->getValue(); // Get existing transaction data

// Default values if no data is found
$email = $adminData['email'] ?? '';
$password = $adminData['password'] ?? '';
$upi_id = $adminData['upi_id'] ?? '';
$qr_code_url = $adminData['qr_code_url'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['upi_id'])) {
        $upi_id = $_POST['upi_id'];

        // Store in admin details
        $adminRef->update(['upi_id' => $upi_id]);

        // Create "transaction" node if not exists
        if (!$transactionData) {
            $transactionRef->set([
                'upi' => $upi_id
            ]);
        } else {
            $transactionRef->update(['upi' => $upi_id]);
        }

        echo "<script>alert('UPI ID updated successfully!');</script>";
    }

    if (!empty($_POST['qr_code_url'])) {
        $qr_code_url = $_POST['qr_code_url'];

        // Store QR code link in Firebase
        $adminRef->update(['qr_code_url' => $qr_code_url]);

        // Create "transaction" node if not exists
        if (!$transactionData) {
            $transactionRef->set([
                'qr_code' => $qr_code_url
            ]);
        } else {
            $transactionRef->update(['qr_code' => $qr_code_url]);
        }

        echo "<script>alert('QR Code link updated successfully!');</script>";
    }

    if (isset($_POST['deselect'])) {
        // Remove transaction root when deselected
        if ($transactionData) {
            $transactionRef->remove();
            echo "<script>alert('UPI & QR Code deselected successfully!');</script>";
        } else {
            echo "<script>alert('Transaction data not found.');</script>";
        }
    }
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
</head>
<body>
    <a href="dashboard.php" style="position: absolute; top: 10px; left: 10px; padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
        ← Go to Dashboard
    </a>
    <h2>Admin Settings</h2>

    <form method="POST">
        <label for="upi_id">UPI ID:</label>
        <input type="text" name="upi_id" value="<?php echo htmlspecialchars($upi_id); ?>" required>
        <button type="submit">Update UPI ID</button>
    </form>

    <form method="POST">
        <label for="qr_code_url">QR Code Link:</label>
        <input type="text" name="qr_code_url" value="<?php echo htmlspecialchars($qr_code_url); ?>" placeholder="Enter QR Code link here" required>
        <button type="submit">Update QR Code</button>
    </form>

    <?php if (!empty($qr_code_url)) : ?>
        <h3>Current QR Code:</h3>
        <img src="<?php echo htmlspecialchars($qr_code_url); ?>" alt="QR Code" width="150">

        <form method="POST">
            <input type="hidden" name="deselect" value="1">
            <button type="submit">Deselect UPI & QR</button>
        </form>
    <?php endif; ?>

</body>
</html>
