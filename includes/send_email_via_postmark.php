<?php

function send_email_via_postmark($mailing_address, $mailing_title, $mailing_message) {
    
    # Php "include" to supply the "grunt" Postmark interface  

    # bounce if we see local operation - Postmark is only available on the Live server. 

    if ($_SERVER['REMOTE_ADDR'] == '127.0.0.1' or $_SERVER['REMOTE_ADDR'] == '::1') {
        return "";
    }


# Import the Postmark Client Class:
    require_once('vendor/autoload.php');

    $mail_result = true;

    try {
        $client = new Postmark\PostmarkClient("postmark_credentials_key"); //**CONFIG REQUIRED** - set this to your postmark client credentials, eg "ghjk.. - 89ll - ...." etc
        $sendResult = $client->sendEmail(
                "postmark_account_address", $mailing_address, $mailing_title, $mailing_message); //**CONFIG REQUIRED** set this to your Postmark a/c address eg myaddress@gmail.com
    } catch (Postmark\Models\PostmarkException $ex) {
        // If client is able to communicate with the API in a timely fashion,
        // but the message data is invalid, or there's a server error,
        // a PostmarkException can be thrown.
        $mail_result = false;
        $postmarkApiErrorCode = $ex->postmarkApiErrorCode;
        return '%problem% in "send_email_via_postmark" at loc 1 - Postmark code is : ' . $postmarkApiErrorCode;
    } catch (Exception $e) {
        //Boring error messages from anything else!
        $mail_result = false;
        $getMessage = $e->getMessage();
        return '%problem% in "send_email_via_postmark" at loc 2 - Postmark code is : ' . $getMessage;
    }
    
        return 'Mail successfuly despatched';
        
} 
    



