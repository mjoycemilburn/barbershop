<?php

namespace Listener;

// Copied from example_usage_advanced.php in https://github.com/paypal/ipn-code-samples/tree/master/php.
// It has been extensively tailored and commented. Two version of the code exist - sandbox_listener
// and live_listener. They differ only in the setting of $enable_sndbox on line 10
// Set this to true to use the sandbox endpoint during testing:

$enable_sandbox = false;

//error_log("entered listener_sandbox " . date("Y-m-d H:i:s"));
// Specification of all of the paypal business addresses that can legitimately call the 
// listener and activate the "back-office" processes in paypal_transaction_consequentials
  
$source_root = "website_root_domain/"; //**CONFIG REQUIRED** - set this to your website root domain - eg https://mywebsite.com

// Connect to the database

require ('includes/booker_functions.php');
connect();

$sql = "SELECT 
            paypal_business_address
        FROM ecommerce_barbershops;";

$result = mysqli_query($con, $sql);

if (!$result) {
    echo "Oops - database access %failed% in Sandbox_Listener Loc 1. Error details follow<br><br> " . mysqli_error($con);
    diconnect();
    exit(1);
}

$my_email_addresses = array();

while ($row = mysqli_fetch_array($result, MYSQLI_BOTH)) {

    $my_email_addresses[] = $row['paypal_business_address'];
}

// Set this to true to send a confirmation email:
$send_confirmation_email = true;
$confirmation_email_address = "your_testing_email_address"; //**CONFIG REQUIRED** - your testing email address, eg myaddress@gmail.com
$from_email_address = "Martin J";

require('paypalIPN.php'); // insert the Paypal interface validation code

use PaypalIPN; // confirm the namespace

require ('includes/send_email_via_postmark.php'); // add the postmark interface

$ipn = new PaypalIPN();

if ($enable_sandbox) {
    $ipn->useSandbox(); // Execute the useSandbox function int PaypalIPN - see 
    // tutorial_on_arrow_syntax.php for background on this
}
$verified = $ipn->verifyIPN(); // execute the verifyIPN function and put the return value in $verified

$data_text = "";
foreach ($_POST as $key => $value) { // read all the keys in the $_Post object passed to the listener
    // by Paypal, placing the keyname in $key and its value in $value.
    // Build a string for despatch via email to the account address in
    // the event that Paypal verifies the transation 
    $data_text .= $key . " = " . $value . "\r\n";
}

$test_text = "";
if ($_POST["test_ipn"] == 1) {
    $test_text = "Test ";
}

// Check the receiver email address(the "business" address specified in the paypal button to which
// payment has been credited  to see if it matches thelist of legitime paypal email addresses
// for paypal_conseuential_processing attentions

$receiver_email_found = false;
foreach ($my_email_addresses as $a) {
    if (strtolower($_POST["receiver_email"]) == strtolower($a)) {
        $receiver_email_found = true;
        break;
    }
}

date_default_timezone_set("Europe/London"); // origianl American spec over-ridden here
list($year, $month, $day, $hour, $minute, $second, $timezone) = explode(":", date("Y:m:d:H:i:s:T"));
$date = $year . "-" . $month . "-" . $day;
$timestamp = $date . " " . $hour . ":" . $minute . ":" . $second . " " . $timezone;
$dated_log_file_dir = $log_file_dir . "/" . $year . "/" . $month;

$paypal_ipn_status = "VERIFICATION FAILED";
if ($verified) {

    // If we get here, Paypal has verified the transaction as the one it sent - see 
    // https://developer.paypal.com/docs/ipn/integration-guide/IPNIntro/#ipn-protocol-and-architecture

    /* Your listener HTTPS POSTs the complete, unaltered message back to PayPal; the message must 
     * contain the same fields (in the same order) as the original message and be encoded in the same 
     * way as the original message.
     * 
     * PayPal sends a single word back - either VERIFIED (if the message matches the original) or I
     * NVALID (if the message does not match the original). */

    $paypal_ipn_status = "RECEIVER EMAIL MISMATCH";
    if ($receiver_email_found) {

        // If we get here, we're prepared to accept this transaction and pass 
        // it to process_paypal_consequentials to start an asynchronous execution
        // (so tht we don't hold Paypal up getting the response tag back to it.
        // 
        // Build POST variables for the transaction data as name/value pairs 
        // separated by &
        // 
        // curl is a php extension that allows you to communicate via http - ie talk
        // to a url and pass paramteres - POST parms in this case. You could transfer 
        // control using headers, but curl is the only way to pass POST params
        //
        // A list of the possible variables are available here:
        // https://developer.paypal.com/webapps/developer/docs/classic/ipn/integration-guide/IPNandPDTVariables/

        $postData = '';

        foreach ($_POST as $k => $v) {
            $postData .= $k . '=' . $v . '&';
        }
        $postData = rtrim($postData, '&');
        $url = $source_root . "booker_shared_code/php/process_paypal_consequentials.php"; //**CONFIG REQUIRED**
        $ch2 = curl_init();

        curl_setopt($ch2, CURLOPT_URL, $url);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HEADER, false);
        curl_setopt($ch2, CURLOPT_POST, count($postData));
        curl_setopt($ch2, CURLOPT_POSTFIELDS, $postData);

        $output = curl_exec($ch2);

        curl_close($ch2);

        $paypal_ipn_status = "Completed Successfully";
    }
} elseif ($enable_sandbox) {
    if ($_POST["test_ipn"] != 1) {
        $paypal_ipn_status = "RECEIVED FROM LIVE WHILE SANDBOXED";
    }
} elseif ($_POST["test_ipn"] == 1) {
    $paypal_ipn_status = "RECEIVED FROM SANDBOX WHILE LIVE";
}

// In any event - verified by Paypal or not, legitimate email addess or not, email
// the administrator so he knows what's going on - note this is essentially a debugging-only
// arrangement - you'd remove this for live operation. Also we don't use postmark here - it's
// just too difficult to get the directory references and certificates loaded)

if ($send_confirmation_email) {
    $mailing_address = $confirmation_email_address;
    $mailing_title = "Leaving Booker Listener";
    $mailing_message = "$test_text \r\n PayPal IPN_status  = $paypal_ipn_status \r\n paypal_ipn_date = $timestamp \r\n $data_text \r\n From = $from_email_address \r\n";

    $mailing_result = send_email_via_postmark($mailing_address, $mailing_title, $mailing_message);
//error_log("$mailing_address $mailing_title $mailing_message $mailing_result");
// nothing to be done if this fails
}

// nothing to be done if this fails

header("HTTP/1.1 200 OK"); // send a response tag to paypal
