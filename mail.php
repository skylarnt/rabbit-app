<?php

use PHPMailer\PHPMailer\PHPMailer;
require ("/home/rabbjjvr/PHPMailer/src/PHPMailer.php");
require ("/home/rabbjjvr/PHPMailer/src/SMTP.php");
require ("/home/rabbjjvr/PHPMailer/src/Exception.php");
require("/home/rabbjjvr/PHPMailer/src/OAuth.php");


function rabbit_mailer($email, $name, $otp, $mode){
    
    if($mode == "verify"){
        $subject = "Account Verification";
        $content = "<h3>Hello! $name </h3><p>You registered an account on Rabbit Africa.</p><p> please use this number <b><u> $otp </u></b> to verify your account</p>";
    } else {
        $subject = "Password Reset";
        $content = "<h3>Hi! $name </h3><p>There was a request to change your password!</p><p>Kindly use this OTP <b><u> $otp </u></b> to reset your account password.</p><br>Regards<br>Rabbit Africa.";
    }
    
    $mail = new PHPMailer();
    $mail->isSMTP();
    // $mail->SMTPDebug = 2;
    $mail->SMTPAuth   = "true"; 
    $mail->SMTPSecure = "tls"; 
    
    $mail->Host = 'rabbit.africa';
    $mail->SMTPAuth = true;
    $mail->Port = 587;
    $mail->Username = 'rabbit-support@rabbit.africa';
    $mail->Password = 'Hub-one2022';
    
    $mail->setFrom('rabbit-support@rabbit.africa', 'Rabbit Africa');
    $mail->addAddress($email, $name);
    
    $mail->Subject = $subject;
    $mail->isHTML(true);
    $mail->Body = $content;
    
    
    if($mail->send()){
        echo json_encode(['status' => "success", 'subject' => $subject, 'content' => $content]);
    }else{
        echo json_encode(['status' => "error", 'reason' => $mail->ErrorInfo]);
    }
    exit();
    
}





