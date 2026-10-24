<?php

namespace Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Mailer
{
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        $config = Config::get('mail');
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            $mail->SMTPSecure = $config['encryption'];
            $mail->Port = $config['port'];

            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log('Mailer error: ' . $mail->ErrorInfo);
            return false;
        }
    }
}
