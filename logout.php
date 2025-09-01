<?php
session_start();

// Destroy session
session_destroy();

// Redirect to homepage
header('Location: index.php');
exit;
?>