<?php
// Database initialization script
// Run this once to set up the database with the provided schema

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'velvet_vogue';

try {
    // Create connection
    $conn = new mysqli($host, $username, $password);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS velvet_vogue";
    if ($conn->query($sql) === TRUE) {
        echo "Database created successfully or already exists<br>";
    } else {
        echo "Error creating database: " . $conn->error . "<br>";
    }

    // Select the database
    $conn->select_db($database);

    // Read and execute the SQL file content
    $sql_content = file_get_contents('attached_assets/velvet_vogue (1)_1752682302315.sql');
    
    if ($sql_content === false) {
        die("Error reading SQL file");
    }

    // Split SQL statements and execute them
    $statements = explode(';', $sql_content);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !preg_match('/^(--|\/\*|\*)/', $statement)) {
            if ($conn->query($statement) === FALSE) {
                echo "Error executing statement: " . $conn->error . "<br>";
                echo "Statement: " . $statement . "<br><br>";
            }
        }
    }

    echo "Database initialized successfully!<br>";
    echo "You can now delete this file for security.<br>";

    $conn->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
