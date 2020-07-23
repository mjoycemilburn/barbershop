<?php

// This is where we deal with the follow-up from a Booking purchase. Firstly, now that the booking
// has been confirmed by the paypal purchase, we need to update the status of the booking to "paid"
// and send an email with the reference code
// Don't forget to provide a php/certs folder with your paypal credentials;

$page_title = 'paypal_consequentials';

# set headers to NOT cache the page
header("Cache-Control: no-cache, must-revalidate"); //HTTP 1.1
header("Pragma: no-cache"); //HTTP 1.0
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

date_default_timezone_set('Europe/London');

# Connect to the database

require ('/home/qfgavcxt/connect_ecommerce_prototype.php');
require ('../includes/send_email_via_postmark.php');
require ('../includes/booker_functions.php');
require ('../includes/booker_constants.php');

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
$date = "$year-$month-$day";
$reservation_slot = $pieces[3];
$reservation_number = $pieces[4];
$reserver_id = $pieces[5];
$number_of_slots_per_hour = $pieces[6];

// get the stylist-assignment for the appointment

$sql = "SELECT 
            chair_owner
        FROM ecommerce_work_patterns wps
        NATURAL JOIN  ecommerce_reservations rs
        WHERE 
            rs.reservation_number = '$reservation_number' AND
            wps.chair_number = rs.assigned_chair_number;";

$result = mysqli_query($con, $sql);

if (!$result) {
    error_log("Oops - database access %failed%. $page_title Loc 1. Error details follow<br><br> " . mysqli_error($con));
    require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
    exit(1);
}

$row = mysqli_fetch_array($result, MYSQLI_BOTH);

$chair_owner = $row['chair_owner'];

$appointment_string = slot_date_to_string($reservation_slot, $date, $number_of_slots_per_hour);

$mailing_message = "Thank you for your reservation with $chair_owner. We look forward to seeing you at " .
        $appointment_string .
        ". Your booking reference is " . $reservation_number;

$mailing_title = "Your reservation at " . SHOP_NAME;

$mailing_address = $reserver_id;

$mailing_result = send_email_via_postmark($mailing_address, $mailing_title, $mailing_message);

//error_log("$mailing_message $mailing_title $mailing_address $mailing_result"); 
// If postmark didn't manage to send the customer a confirmation mail we have a bit of a problem
// because the customer has paid now but has no proof. There's an unconfirmed reservation on the
// database though, so best confirm that before it disappears. What's really tricky here is that
// if Postmark can't send messages to the customer it may not be able to send messages to the 
// system management either - need to think about this. ANyway, for the present, best just carry on..
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
}

require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');



