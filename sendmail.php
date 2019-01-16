<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function GetMailSettings()
{
   switch ($_SERVER["HTTP_HOST"]) {
   case "xxx":
   case "xxx":
      $l_MailSetting = new StdClass;
      $l_MailSettings->Exceptions = true;                                      // Passing `true` enables exceptions
      $l_MailSettings->SMTPDebug = 0;                                          // Enable verbose debug output
      $l_MailSettings->isSMTP = true;                                          // Set mailer to use SMTP
      $l_MailSettings->Host = '***';                                           // Specify main and backup SMTP servers
      $l_MailSettings->SMTPAuth = true;                                        // Enable SMTP authentication
      $l_MailSettings->Username = '***';                                       // SMTP username
      $l_MailSettings->Password = '***';                                       // SMTP password
      $l_MailSettings->SMTPSecure = 'tls';
      $l_MailSettings->Port = 587;                                             // TCP port to connect to
      break;
   case "yyy":
   case "yyy":
      $l_MailSetting = new StdClass;
      $l_MailSettings->Exceptions = true;                                      // Passing `true` enables exceptions
      $l_MailSettings->SMTPDebug = 0;                                          // Enable verbose debug output
      $l_MailSettings->isSMTP = true;                                          // Set mailer to use SMTP
      $l_MailSettings->Host = '***';                                           // Specify main and backup SMTP servers
      $l_MailSettings->SMTPAuth = true;                                        // Enable SMTP authentication
      $l_MailSettings->Username = '***';                                       // SMTP username
      $l_MailSettings->Password = '***';                                       // SMTP password
      $l_MailSettings->SMTPSecure = 'tls';
      $l_MailSettings->Port = 587;                                             // TCP port to connect to
      break;
   }
   return ($l_MailSettings);
}

function SendMail($i_Mail, $i_ToOffice = false, $i_Attachments = null)
{
   $l_IncludeDir = sprintf ("%s/lib", getcwd ());
   set_include_path(get_include_path () . PATH_SEPARATOR . $l_IncludeDir);

   require_once ("PHPMailer/src/Exception.php");
   require_once ("PHPMailer/src/PHPMailer.php");
   require_once ("PHPMailer/src/SMTP.php");

   $l_Error = new StdClass ();
   $l_Error->ErrNo = -1;
   $l_Error->Message = "";

   $l_MailSettings = GetMailSettings ();

   $mail = new PHPMailer ($l_MailSettings->Exceptions);
   try {
       //-------------------------------
       // Server Settings
       //-------------------------------
       $mail->SMTPDebug = $l_MailSettings->SMTPDebug;
       if ($l_MailSettings->IsSMTP) $mail->isSMTP ();
       $mail->Host = $l_MailSettings->Host;
       $mail->SMTPAuth = $l_MailSettings->SMTPAuth;
       $mail->Username = $l_MailSettings->Username;
       $mail->Password = $l_MailSettings->Password;
       $mail->SMTPSecure = $l_MailSettings->SMTPSecure;
       $mail->Port = $l_MailSettings->Port;

       //-------------------------------
       // Send mail to form submitter
       //-------------------------------
       if ($i_ToOffice) {
          $mail->setFrom ($i_Mail->From, $i_Mail->FromString);
          $mail->addAddress ($i_Mail->From);
          $mail->addReplyTo ($i_Mail->From, $i_Mail->FromString);
       } else {
          $mail->setFrom ($i_Mail->From, $i_Mail->FromString);
          $mail->addAddress ($i_Mail->To);
          $mail->addReplyTo ($i_Mail->From, $i_Mail->FromString);
       }

       //-------------------------------
       // Attachments
       //-------------------------------
       if ($i_ToOffice && is_object ($i_Attachments)) {
          foreach ($i_Attachments as $l_FieldName => $l_FieldArray) {
             foreach ($l_FieldArray as $l_ArrayIndex => $l_InfoArray) {
                if (isset ($l_InfoArray->path_on_disk) && $l_InfoArray->size > 0) {
                   $mail->addAttachment ($l_InfoArray->path_on_disk, $l_InfoArray->name);
                }
             }
          }
       }

       //-------------------------------
       // Content
       //-------------------------------
       if ($i_ToOffice) {
          $mail->isHTML (true);
          $mail->Subject = sprintf ("Backoffice - %s", $i_Mail->RequestDescription);
          $mail->Body    = $i_Mail->Message;
          $mail->AltBody = $i_Mail->PlainMessage;
       } else {
          $mail->isHTML (true);
          $mail->Subject = $i_Mail->Subject;
          $mail->Body    = $i_Mail->Message;
          $mail->AltBody = $i_Mail->PlainMessage;
       }

       //-------------------------------
       // Send mail
       //-------------------------------
       $mail->send ();

       $l_Error->ErrNo = 0;
       $l_Error->Message = "The mail has been sent successfully";
   } catch (Exception $e) {
       $l_Error->ErrNo = 1;
       $l_Error->Message = "An error occured: " . $mail->ErrorInfo;
   }

   return ($l_Error);
}
?>
