<?php
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'malak.mhmdd.17@gmail.com';
    $mail->Password   = 'zxsb mcda xysk xrkj';
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom('malak.mhmdd.17@gmail.com', 'Forsa Platform');
    $mail->addAddress('alidevloper76@gmail.com');
    $mail->Subject = 'Test Email';
    $mail->Body    = 'This is a test email from Forsa Platform.';
    $mail->send();
    echo 'Email sent successfully!';
} catch (Exception $e) {
    echo 'Error: ' . $mail->ErrorInfo;
}