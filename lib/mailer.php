<?php
require_once __DIR__ . '/../app/_config/smtp.php';

function cosigo_send_mail($to, $subject, $body, $replyTo) {
    $headers = [];
    $headers[] = "From: Cosigo Contact <sales@cosigo.io>";
    $headers[] = "Reply-To: $replyTo";
    $headers[] = "Content-Type: text/plain; charset=UTF-8";

    return mail($to, $subject, $body, implode("\r\n", $headers));
}
