<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $to = $_POST['to'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];

    $headers = "From: noreply@example.com\r\n";
    $headers .= "Reply-To: noreply@example.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    if (mail($to, $subject, $message, $headers)) {
        echo "Email sent successfully!";
    } else {
        echo "Failed to send email.";
    }
}
?>