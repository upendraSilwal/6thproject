<?php
session_start();
require_once 'config/database.php';

// Only unset user session variables, preserve admin session
unset($_SESSION['user_id']);
unset($_SESSION['user_name']);

// Redirect to home page
header("Location: index.php");
exit();
?> 