<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailHelper {

    private static function mailer(string $fromEmail, string $fromName): PHPMailer {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'malak.mhmdd.17@gmail.com';
        $mail->Password   = 'zxsb mcda xysk xrkj';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($fromEmail, $fromName);
        return $mail;
    }

    public static function sendCompanyStatusEmail(
        string $toEmail,
        string $companyName,
        string $status
    ): void {
        try {
            $mail = self::mailer('malak.mhmdd.17@gmail.com', 'Forsa Platform');
            $mail->addAddress($toEmail, $companyName);

            if ($status === 'approved') {
                $mail->Subject = 'Your Company Has Been Approved – Forsa';
                $mail->Body    = "Hello $companyName,\n\nCongratulations! Your company registration on Forsa has been approved.\nYou can now post jobs and manage applications.\n\nBest regards,\nForsa Team";
            } else {
                $mail->Subject = 'Company Registration Update – Forsa';
                $mail->Body    = "Hello $companyName,\n\nWe regret to inform you that your company registration on Forsa has been rejected.\nIf you have questions, please contact us.\n\nBest regards,\nForsa Team";
            }

            $mail->send();
        } catch (\Exception $e) {
            error_log('Email error (company status): ' . $e->getMessage());
        }
    }

    public static function sendApplicationStatusEmail(
        string $toEmail,
        string $studentName,
        string $jobTitle,
        string $companyName,
        string $companyEmail,
        string $status
    ): void {
        try {
            $mail = self::mailer('malak.mhmdd.17@gmail.com', $companyName);
            $mail->addAddress($toEmail, $studentName);

            if ($status === 'accepted') {
                $mail->Subject = "Application Accepted – $jobTitle";
                $mail->Body    = "Hello $studentName,\n\nGreat news! Your application for \"$jobTitle\" at $companyName has been accepted.\n\nBest regards,\n$companyName";
            } else {
                $mail->Subject = "Application Update – $jobTitle";
                $mail->Body    = "Hello $studentName,\n\nThank you for applying for \"$jobTitle\" at $companyName.\nUnfortunately, your application was not selected at this time.\n\nBest regards,\n$companyName";
            }

            $mail->send();
        } catch (\Exception $e) {
            error_log('Email error (application status): ' . $e->getMessage());
        }
    }
}