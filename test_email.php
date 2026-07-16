<?php
require_once __DIR__ . '/vendor/autoload.php';

$mail = new \PHPMailer\PHPMailer\PHPMailer(true);

try {
    $mail->SMTPDebug = 3; // Enable verbose debug output
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'tecnologia.arrastao@gmail.com';
    $mail->Password   = 'xjadihsbebkssjzh'; // Sem espaços
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('tecnologia.arrastao@gmail.com', 'CetusG Support');
    $mail->addAddress('tecnologia.arrastao@gmail.com');

    $mail->isHTML(true);
    $mail->Subject = 'Teste Diagnóstico de Email';
    $mail->Body    = 'Este é um teste de diagnóstico.';

    $mail->send();
    echo "Message has been sent successfully.\n";
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}\n";
}
