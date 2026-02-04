<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../lib/mailer.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(403);
    exit("Forbidden");
}

$name    = trim($_POST["name"] ?? "");
$email   = trim($_POST["email"] ?? "");
$subject = trim($_POST["subject"] ?? "");
$message = trim($_POST["message"] ?? "");

if (!$name || !$email || !$subject || !$message) {
    exit("Missing required fields.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Invalid email address.");
}

$success = cosigo_send_mail(
    'sales@cosigo.io',
    "[Cosigo Contact] " . $subject,
    "Name: $name\nEmail: $email\n\n$message",
    $email
);

if ($success) {
    echo "OK";
} else {
    http_response_code(500);
    echo "Mail failed";
}
