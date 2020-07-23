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
        $client = new Postmark\PostmarkClient("f7c9f92f-89d5-4fc3-91c3-4ea6f8e2ecb1"); //**CONFIG REQUIRED**
        $sendResult = $client->sendEmail(
                "administrat0r@applebyarchaeology.org.uk", $mailing_address, $mailing_title, $mailing_message); //**CONFIG REQUIRED**
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
    



