<?php
require 'vendor/autoload.php';

use Kreait\Firebase\Factory;

// Load Firebase credentials from Railway environment variable
$firebaseJson = getenv('FIREBASE_KEY');

if (!$firebaseJson) {
    die("Error: Firebase key not found in environment variables.");
}

// Create a temporary JSON file for Firebase credentials
$firebaseKeyPath = sys_get_temp_dir() . '/firebase-key.json';
file_put_contents($firebaseKeyPath, $firebaseJson);

// Initialize Firebase
$factory = (new Factory)
    ->withServiceAccount($firebaseKeyPath)
    ->withDatabaseUri('https://library-management-syste-d7b1f-default-rtdb.firebaseio.com/');

$database = $factory->createDatabase();
?>
