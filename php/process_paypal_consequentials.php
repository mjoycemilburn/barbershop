<?php

// This is where we deal with the follow-up from a Booking purchase. Firstly, now that the booking
// has been confirmed by the paypal purchase, we need to update the status of the booking to "paid"
// and send an email with the reference code
//error_log("entered process consequentials " . date("Y-m-d H:i:s"));

$page_title = 'paypal_consequentials';

# set headers to NOT cache the page
header("Cache-Control: no-cache, must-revalidate"); //HTTP 1.1
header("Pragma: no-cache"); //HTTP 1.0
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

date_default_timezone_set('Europe/London');

# Connect to the database

require ('/home/qfgavcxt/connect_ecommerce_prototype.php');

// Import the Postmark Client Class:
require_once('../vendor/autoload.php');

use Postmark\PostmarkClient;
use Postmark\Models\PostmarkException;

$custom = $_POST['custom'];

// Huge problems with extracting the constituent elements of the custom variable. There's
// no magic here, the custom variable is just a character string, eg custom="1,2,3". In this case
// the variable might be taken to represent to represent 3 parameters with values 1, 2 and 3
// You would access the conents of custom, by first grabbing the whole string with $_POST['custom']
// and then unpacking its contents. Received wisdom is that you should construct it as an associative
// structure - eg year=2020&month=6&day=4 etc and use parse_str($_POST['custom'],$array);
// to get your individual parameters as associative array elements - $array['year'], 
// $array['month'] and so on. For some reason, this wouldn't work in this situation. What seemed
// to happen was that the string was getting mangled and only the name of the first parameter
// was getting back - ie in the above eg, $_POST custom was just "year". It might have been that 
// because the & characters were being transmitted as html entities (&amp;) - never really
// got to the bottom of it. The method used below is nowhere near as clear - but at least
// it works!!!! Here, the constituent parameters are just separated by commas - no fancy 
// index names - we work out what's what from its position in the string.

$pattern = ",";
$pieces = explode($pattern, $custom); // Create an array of "pieces" from the comma-separated strings in $custom

$year = $pieces[0];
$month = $pieces[1];
$day = $pieces[2];
$reservation_slot = $pieces[3];
$reservation_number = $pieces[4];
$reserver_id = $pieces[5];

$reservation_slot_time = 100 * floor($reservation_slot / 4) + 15 * ($reservation_slot % 4);

$ampm = "am";
if ($reservation_slot_time == 1200)
    $ampm = " noon";
if ($reservation_slot_time == 00)
    $ampm = " midnight";
if ($reservation_slot_time > 1200)
    $ampm = "pm";
if ($reservation_slot_time >= 1300) {
    $reservation_slot_time = $reservation_slot_time - 1200;
}

$first_bit = floor($reservation_slot_time / 100);
$second_bit = strval($reservation_slot_time % 100);
if ($second_bit == "0")
    $second_bit = "00";

$date_string = $year . "-" . $month . "-" . $day;
$date = strtotime($date_string);

$message = "Thank you for your reservation. We look forward to seeing you at " .
        $first_bit . ":" . $second_bit . $ampm . " on " .
        date('l jS F Y', $date) .
        ". Your booking reference is " . $reservation_number;

try {
    $client = new PostmarkClient("*CONFIG REQUIRED");
    $sendResult = $client->sendEmail(
            "*CONFIG REQUIRED", $reserver_id, "Your reservation at O'Neils ", $message);
} catch (PostmarkException $ex) {
// If client is able to communicate with the API in a timely fashion,
// but the message data is invalid, or there's a server error,
// a PostmarkException can be thrown.
    $httpStatusCode = $ex->httpStatusCode;
    $postmarkApiErrorCode = $ex->postmarkApiErrorCode;

    error_log("Postmark failed - httpStatusCode = $httpStatusCode :  ApiErrorCode = $postmarkApiErrorCode", 0);
    exit(1);
} catch (Exception $e) {
// A general exception is thrown if the API
// was unreachable or times out.
    $getMessage = $e->getMessage();
    error_log("General Exception e : $getMessage", 0);
    exit(1);
}

// change the status of the reservation to "C" on the ecommerce_reservations database

$sql = "UPDATE ecommerce_reservations SET
                    reservation_status = 'C'                                                                                          
                WHERE
                    reservation_number = '$reservation_number';";

$result = mysqli_query($con, $sql);

if (!$result) {
    error_log("Oops - database access %failed%. $page_title Loc 1. Error details follow<br><br> " . mysqli_error($con));
    require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
    exit(1);
} else {
    echo "Save successful";
    require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
}


