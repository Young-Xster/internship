<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../lib/PHPMailer-6.8.0/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer-6.8.0/src/SMTP.php';
require_once __DIR__ . '/../lib/PHPMailer-6.8.0/src/Exception.php';

function sendNewMaterielEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    try {
        // SMTP server configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.mailersend.net';
        $mail->SMTPAuth = true;
        $mail->Username = 'MS_GBPJuK@test-eqvygm0o5r8l0p7w.mlsender.net';
        $mail->Password = 'mssp.11BSy93.neqvygmy8pdg0p7w.68ztHd2';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Enable SMTP debugging and log to error.log
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            file_put_contents(__DIR__ . '/../error.log', date('Y-m-d H:i:s') . " PHPMailer SMTP: $str\n", FILE_APPEND);
        };

        // Sender and recipient
        $mail->setFrom('MS_GBPJuK@test-eqvygm0o5r8l0p7w.mlsender.net', 'No Reply');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('MailerSend SMTP error: ' . $mail->ErrorInfo);
        return false;
    }
} 