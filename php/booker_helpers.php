<?php

# Notes on reservation_status
# 
#   U ("unconfirmed")   - Initial setting for online ("email") booking pending completion of Paypal payment
#   C ("confirmed")     - Setting when Paypal payment has completed or when an (unpaid) ("telephone")
#                       - reservation has been created by management
#   P ("pending")       - Indicates that an online ("email") booking has been cancelled by management
#                         (using the "Send Email cancellation messages" button following up unplanned
#                         staff absences) and that we aare waiting for the online booker to use the
#                         link contained in the message to rebook the reservation)

# As directed by helper_type :
# 
# 'build_calendar_month_display'    -  create display table for supplied year (yyyy), month (1 - 12)
#
# 'build_calendar_day_display'      -   create display table for supplied year (yyyy), month (1 - 12) and
#                                       day (01 - 31)
#                                   
# 'get_reservation_status'          -   return existence status (true/false) of reservation for reservation)_date
#                                       (yyyy-mm-dd) and $reservation_slot (0 - 23 *$number_of_slots_per_hour)
#
# 'insert_reservation'              -   create a reservations record for the given year, month, day,slot
#                                       and reserver_id
#                                       
# 'change_reservation'              -   replace the reservations record for the given reservation_number and rebook it
#                                       for the given year, month, day and slot                                   
#                                   
# 'build_slot_reservations_display' -   create a display popup for the reservation s for the specified slot                                 
#                                   
# 'abort_reservation'               -   delete the reservation for supplied reservation_number (online booker aborting paypal) 
# 
# 'cancel_reservation'              -   delete the reservation for supplied reservation_number (management receiving telephone instructions)
#                                       note this will send confirmation email to an "email" reservation                                    
#
# 'delete_unconfirmed_reservations' -   delete any unconfirmed reservations more than 1 hour old
#
# 'build_bank_holiday_table_for_month_display'   -   create display table for supplied year (yyyy), month (1 - 12)
#                                                    showing bank holidays
#                                                    
# 'toggle_bank_holiday'             -   toggle bank holiday record  in and out of existence for supplied date                                               
#                                                    
# 'build_staff_holiday_table_for_month_display'   -   create display table for supplied year (yyyy), month and chair_number
#                                                    showing staff absence  
#                                                    
#                                                      
# 'toggle_staff_holiday'            -   toggle bstaff_holiday' record  in and out of existence for supplied date and chair_number                                                                                                                                                      
#  
# 'build_work_pattern_table_for_week_display'   -   create display table for supplied chair_number showing hours
#                                                   worked in a standard week. If there is no work_pattern for
#                                                   the chair number, it creates one
#
# 'save_pattern'                    -   Update the pattern json and chair_owner name for the supplied chair_number
#
# 'build_check_display'             - Return a list of slots (in the upcoming three months) that are
#                                     unresourced give current staffing, work-patterns and holidays
#
# 'issue_reservation_apologies'     - issue reservation apologies for given range of slots - set the first email booking in
#                                     each of the affected slots to "postponed" and despatch an email conataining a link
#                                     inviting rebooking

$page_title = 'booker_helpers';

# set headers to NOT cache the page
header("Cache-Control: no-cache, must-revalidate"); //HTTP 1.1
header("Pragma: no-cache"); //HTTP 1.0
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

date_default_timezone_set('Europe/London');

# Connect to the database

require ('/home/qfgavcxt/connect_ecommerce_prototype.php');
require ('../includes/booker_functions.php');
require ('../includes/booker_constants.php');
require ('../includes/send_email_via_postmark.php');

$helper_type = $_POST['helper_type'];

// Useful parameters

$month_name_array = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

#####################  'build_calendar_month_display' ####################

if ($helper_type == 'build_calendar_month_display') {

    $year = $_POST['year'];
    $month = $_POST['month'];
    $mode = $_POST['mode'];
    $number_of_slots_per_hour = $_POST['number_of_slots_per_hour'];

    $slot_length = 60 / $number_of_slots_per_hour;

    $length_of_month = date("t", strtotime("$year-$month"));

    $return = '<p>Availability for <strong>' . $month_name_array[$month - 1] . '</strong> ' . $year . '</p>
                    <p><img style="height: 1.25em;" src = "img/media-stop-green.svg"> = days with free slots</p>';

    $return .= '
    <table class="table" style="border-collapse: separate; border-spacing: .5vw;">
        <thead>
            <th>Sun</th>
            <th>Mon</th>        
            <th>Tue</th>
            <th>Wed</th>
            <th>Thu</th>
            <th>Fri</th>
            <th>Sat</th>
        </thead>
        <tbody>
        <tr>';

    build_availability_variables_for_month($year, $month);

// Only show rows referencing current dates. Get day of month for $today - date("j")- (1-31)
// But bearing in mind that this function may be called for months other than "today" set
// today so we show the whole month

    $today_day = date("j");
    $today_month = date("n");
    if ($today_month != $month)
        $today_day = 1;

    for ($i = $today_day; $i <= $length_of_month; $i++) {

        $date = $year . "-" . $month . "-" . $i;
        $date_array_coordinates = date_to_display_coordinates($date);

        $date_row = $date_array_coordinates[0];
        $date_col = $date_array_coordinates[1];

        if ($i == $today_day) {
            for ($j = 0; $j < $date_col; $j++) {
                $return .= '<td></td>';
            }
        }

        if ($day_availability_array[$i - 1] == 1) {
            $background = "aquamarine";
        } else {
            $background = "red";
        }

        if ($mode == "viewer") {
            $onclick = 'onclick = "getClickPosition(event); displayDay(' . $year . ',' . $month . ',' . $i . ');"';
        } else {
            if ($day_availability_array[$i - 1] == 1) {
                $onclick = 'onclick = "getClickPosition(event); displayDay(' . $year . ',' . $month . ',' . $i . ');"';
            } else {
                $onclick = '';
            }
        }

        $return .= '<td style="background: ' . $background . '" ' . $onclick . '>' . $i . '</td>';

        if ($date_col == 6) {
            $return .= '</tr>';
            if ($i < $length_of_month) {
                $return .= '<tr>';
            }
        }
    }

    $return .= '
            </tbody>
        </table>';

    echo $return;
}

#####################  'build_calendar_day_display' ####################

if ($helper_type == 'build_calendar_day_display') {

    $year = $_POST['year'];
    $month = $_POST['month'];
    $day = $_POST['day'];
    $mode = $_POST['mode'];
    $number_of_slots_per_hour = $_POST['number_of_slots_per_hour'];

    $slot_length = 60 / $number_of_slots_per_hour;

    $reservation_date = $year . "-" . $month . "-" . $day;

# build 'availability' array for the slots for this date from the ecommerce_reservations,
# day_override and slot_override tables

    build_availability_variables_for_month($year, $month);

    $date_string = $year . "-" . $month . "-" . $day;
    $date = strtotime($date_string);

    $requested_date = date("Y-m-d", $date);
    $now_date = date("Y-m-d");
    $now_hour = date("G"); // (0-23)
    $now_minutes = date("i"); // (00-59);
    $next_available_slot = $now_hour * $number_of_slots_per_hour + floor($now_minutes / (60 / $number_of_slots_per_hour)) + 1;

    $return = '<p>Availability for ' . date("l", $date) . " " . date("j", $date) . date("S", $date) . " " . date("F", $date) . " " . $year . '</p> 
                    <p>
                        <img  style="height: 1.25em;" src = "img/media-stop-green.svg"> - avble
                        <img style = "height: 1.25em; margin-left: 2em;" src = "img/media-stop-partial-green.svg"> - limited
                        <img style = "height: 1.25em; margin-left: 2em;" src = "img/media-stop-red.svg"> - unavble
                    </p>';

    $return .= '
    <table class="table" style="border-collapse: separate; border-spacing: .5vw;">
        <thead>
            <th></th>';

    $return .= '<th>+ .00</th>';

    for ($i = 1; $i < $number_of_slots_per_hour; $i++) {

        $return .= '<th>+ .' . $i * $slot_length . '</th>';
    }

    $return .= '</thead>
                    <tbody>
                    <tr>';

// We need to trim off unavailable working hours at the beginning and end of the day
// to stop it flooding the screen with unavailable days. Use the $earliest_working_slot
// and $latest_working_slot global variables from build_availability_variables_for_month 

    $earliest_working_hour = floor($earliest_working_slot / $number_of_slots_per_hour);
    $latest_working_hour = floor($latest_working_slot / $number_of_slots_per_hour);


    for ($i = $earliest_working_hour; $i <= $latest_working_hour; $i++) {

        $return .= '<tr><td style="background: gainsboro;">' . $i . '.00</td>';
        for ($j = 0; $j < $number_of_slots_per_hour; $j++) {

            $reservation_slot = $i * $number_of_slots_per_hour + $j;

            if ($requested_date == $now_date && $reservation_slot < $next_available_slot) {
// block out expired slots
                $return .= '<td></td>';
            } else {

                $availability = $slot_availability_array[$reservation_slot][$day - 1];
                $takeup = $bookings_taken_array[$reservation_slot][$day - 1];
                $headroom = $availability - $takeup;

                if ($headroom <= 0) {
                    $background = "red";
                } else {
                    if ($availability == 1) {
                        $background = "aquamarine";
                    } else {
                        if ($takeup > 0) {
                            $background = "palegreen";
                        } else {
                            $background = "aquamarine";
                        }
                    }
                }

                if ($mode == "viewer") {
                        $onclick = 'onclick = "viewSlot(' . $year . ',' . $month . ',' . $day . ',' . $reservation_slot . ');"';
                } else {
                    if ($headroom == 0) {
                        $onclick = "";
                    } else {
                        $onclick = 'onclick = "bookSlot(' . $year . ',' . $month . ',' . $day . ',' . $reservation_slot . ');"';
                    }
                }
                $return .= '<td style = "background: ' . $background . ';" ' . $onclick . '</td>';
            }
        }
        $return .= '</tr>';
    }

    $return .= '
            </tbody>
        </table>
        <button id = "dayviewcancelbutton" style="margin-bottom: 1em;" onclick="displayMonth(' . $year . ',' . $month . ');">Cancel</button>';

    echo $return;
}

#####################  'insert_reservation' ####################

if ($helper_type == 'insert_reservation') {

    $year = $_POST['year'];
    $month = $_POST['month'];
    $day = $_POST['day'];
    $reservation_slot = $_POST['reservation_slot'];
    $reserver_id = $_POST['reserver_id'];
    $mode = $_POST['mode'];
    $number_of_slots_per_hour = $_POST['number_of_slots_per_hour'];

    $slot_length = 60 / $number_of_slots_per_hour;

    $reservation_date = $year . "-" . $month . "-" . $day;
    $reservation_time_stamp = date('Y-m-d H:i:s');

// at this stage, for an email booking, the reservation has not been confirmed (paypal payment
// is pending), so put an "unconfirmed" status against it. We'll update this to "confirmed" if the 
// payment is completed and control returns to the application. In the meanwhile, anybody
// looking at this slot in "day-view" will see that it is reserved. A "sweepup" routine that
// deletes unconfirmed reservations (anything that has been on the d/b for more than 3 hours
//  - plenty of time for users to find their password, surely) will be performed every time
// the applcation starts
// There's another wrinkle to this. It's possible that two people may see the same single
// slot vacant and both attempt to book it. The approach taken here is as follows. One of 
// the two will get in first of course and the routine below starts a transaction whoese
// first task is to see if the slot is still free. Only if this is still the case is the
// new reservation (with its unconfirmed status) and the transaction concluded. The second 
// booker will go throught the same process (once the locks are removed), but the "slot still
// free check will fail and they will be given a "sorry - not quick enough message"

    $sql = "START TRANSACTION;";

    $result = mysqli_query($con, $sql);
    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 8. Error details follow<br><br> " . mysqli_error($con);
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    build_availability_variables_for_month($year, $month);

// check the availability for this slot

    if ($slot_availability_array[$reservation_slot][$day - 1] - $bookings_taken_array[$reservation_slot][$day - 1] <= 0) {
        echo "Sorry - this slot has just been booked by another customer - please choose another";
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

// OK - now clear to create a new reservation

    $sql = "INSERT INTO ecommerce_reservations (
                reservation_date,
                reservation_slot,
                reservation_status,
                reserver_id,
                reservation_type,
                reservation_time_stamp)
            VALUES (
                '$reservation_date', 
                '$reservation_slot',";

    if ($mode == "email") {
        $sql .= "'U',";
    } else {
        $sql .= "'C',";
    }

    $sql .= "'$reserver_id',
                '$mode',
                '$reservation_time_stamp');";


    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 9. Error details follow<br><br> " . mysqli_error($con);
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

// get the reservation_number that's just been allocated

    $reservation_number = mysqli_insert_id($con);

// confirmation of booking for email bookers will be sent by process_paypal_consequentials

    $sql = "COMMIT;";

    $result = mysqli_query($con, $sql);
    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 10. Error details follow<br><br> " . mysqli_error($con);
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }
    echo $reservation_number;
}

#####################  'change_reservation' ####################

if ($helper_type == 'change_reservation') {

// This may be called either by a customer after receiving a cancellation email
// ("change" mode) or by management in consultation with the customer over the phone
// ("rebook" mode)

    $outgoing_reservation_number = $_POST['outgoing_reservation_number'];

    $year = $_POST['year'];
    $month = $_POST['month'];
    $day = $_POST['day'];
    $reservation_slot = $_POST['reservation_slot'];
    $mode = $_POST['mode'];
    $number_of_slots_per_hour = $_POST['number_of_slots_per_hour'];

    $slot_length = 60 / $number_of_slots_per_hour;

    $reservation_date = $year . "-" . $month . "-" . $day;
    $reservation_time_stamp = date('Y-m-d H:i:s');

    $sql = "START TRANSACTION;";

    $result = mysqli_query($con, $sql);
    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 11. Error details follow<br><br> " . mysqli_error($con);
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    build_availability_variables_for_month($year, $month);

// check the availability for this slot

    if ($slot_availability_array[$reservation_slot][$day - 1] == 0) {
        echo "Sorry - this slot has just been booked by another customer - please choose another";
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

// OK - now need to check the status of the outgoing reservation:
// 
// for change mode, the type needs to be email and the status needs to be "P" (postponed)
// for "rebook" mode, the type can be anything and the status need to be "C"  

    $sql = "SELECT
                reservation_status,
                reserver_id,
                reservation_type
            FROM ecommerce_reservations
            WHERE
                reservation_number = '$outgoing_reservation_number';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 12. Error details follow<br><br> " . mysqli_error($con);
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    $row = mysqli_fetch_array($result);

    $reservation_status = $row['reservation_status'];
    $reserver_id = $row['reserver_id'];
    $reservation_type = $row['reservation_type'];
    $reservation_status = $row['reservation_status'];

    if (($mode == "change" && $reservation_status == "P") ||
            ($mode == "rebook" && $reservation_status == "C")) {
        
    } else {
        echo "Oops - reservation status %failed%. $page_title Loc 12a. mode = $mode, status = $reservation_status";
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

// OK - now clear to delete the outgoing reservation and create the new one

    $sql = "DELETE FROM ecommerce_reservations
            WHERE
                reservation_number = '$outgoing_reservation_number';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 13. Error details follow<br><br> " . mysqli_error($con);
        $sql = "ROLLBACK;";
        $result = mysqli_query($con, $sql);
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    $sql = "INSERT INTO ecommerce_reservations (
                reservation_date,
                reservation_slot,
                reservation_status,
                reserver_id,
                reservation_type,
                reservation_time_stamp)
            VALUES (
                '$reservation_date', 
                '$reservation_slot',
                'C',
                '$reserver_id',
                '$reservation_type',
                '$reservation_time_stamp')";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 14. Error details follow<br><br> " . mysqli_error($con);
        $sql = "ROLLBACK;";
        $result = mysqli_query($con, $sql);
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

// get the reservation_number that's just been allocated

    $reservation_number = mysqli_insert_id($con);

// finally send a confirmation email for the new slot

    $appointment_string = slot_date_to_string($reservation_slot, $reservation_date, $number_of_slots_per_hour);

    $mailing_address = $reserver_id;
    $mailing_title = "Your re-booked reservation at " . SHOP_NAME;
    $mailing_message = "Thank you for your re-booked reservation. We look forward to seeing you at " .
            $appointment_string .
            ". Your booking reference is " . $reservation_number;

    $mailing_result = send_email_via_postmark($mailing_address, $mailing_title, $mailing_message);
    
// If postmark didn't manage to send the customer a confirmation mail we have a bit of a problem
// because the customer has paid now but has no proof. There's a confirmed reservation on the
// database thoughs. What's really tricky here is that if Postmark can't send messages to the
// customer it may not be able to send messages to the system management either - need to think
// about this. Anyway, for the present, best just carry on..

    $sql = "COMMIT;";

    $result = mysqli_query($con, $sql);
    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 15. Error details follow<br><br> " . mysqli_error($con);
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }
    echo $reservation_number;
}

#####################  'build_slot_reservations_display' ####################

if ($helper_type == 'build_slot_reservations_display') {

    $year = $_POST['year'];
    $month = $_POST['month'];
    $day = $_POST['day'];
    $reservation_slot = $_POST['reservation_slot'];
    $number_of_slots_per_hour = $_POST['number_of_slots_per_hour'];

    $slot_length = 60 / $number_of_slots_per_hour;

    $reservation_date = $year . "-" . $month . "-" . $day;

# build html code to display the details of all reservation for the selected slot

    $sql = "SELECT
                reservation_number,
                reservation_status,
                reserver_id,
                reservation_type,
                reservation_time_stamp
            FROM ecommerce_reservations
            WHERE
                reservation_date = '$reservation_date' AND
                reservation_slot = '$reservation_slot';";

    $result = mysqli_query($con, $sql);

    if ($result) {

        if (($reservation_slot % $number_of_slots_per_hour) == 0) {
            $reservation_time = round($reservation_slot / $number_of_slots_per_hour) . ":00";
        } else {
            $reservation_time = floor($reservation_slot / $number_of_slots_per_hour) . ":" . ($reservation_slot % $number_of_slots_per_hour) * $slot_length;
        }

        $return = "<p style='padding-top: .5em;'>Reservations for $reservation_time on $year-$month-$day</p>";
        $count = 0;

        while ($row = mysqli_fetch_array($result)) {

            $count++;
            $reservation_number = $row['reservation_number'];
            $reservation_status = $row['reservation_status'];
            $reserver_id = $row['reserver_id'];
            $reservation_type = $row['reservation_type'];
            $reservation_time_stamp = $row['reservation_time_stamp'];

            $source = "Online Booker (prepaid)";
            if ($reservation_type == "telephone")
                $source = "Telephone Booker (unpaid)";

            $booking_time_object = strtotime($reservation_time_stamp);
            $booking_time = date('Y-m-d at H:i:s', $booking_time_object);

            $return .= "
                        <strong>Reservation $count:</strong>
                        <p>Contact details : $reserver_id</p>
                        <p>Reservation Reference : $reservation_number</p>
                        <p>Source : $source</p>
                        <p>    
                        <button class='btn btn-primary' onclick = 'rebookReservation(" . $reservation_number . ");'>Re-book Res</button>&nbsp;&nbsp;&nbsp;
                        <button class='btn btn-danger' onclick = 'cancelReservation(" . $reservation_number . ");'>Cancel Res</button>
                        </p>";
        }
    } else {
        echo "Oops - database access %failed%. $page_title Loc 16. Error details follow<br><br> " . mysqli_error($con);
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    if ($count == 0)
        $return .= "<p>There are no reservations for this slot</p>";

    echo $return;
}

#####################  'abort_reservation' ####################
//
// delete the specified reservation - this would be used where a booker cancelled
// a booking by clicking the cancel button below the paypal button .

if ($helper_type == 'abort_reservation') {

    $reservation_number = $_POST['reservation_number'];

    $sql = "DELETE FROM ecommerce_reservations
            WHERE
                reservation_number = '$reservation_number';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 17. Error details follow<br><br> " . mysqli_error($con);
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }
}

#####################  'cancel_reservation' ####################
//
// Called by manageent from the "manage reservations" button - used
// typically where a booker has rung up and asked to cancel their reservation.
// The routine will send a confirmation email for reservations that started life
// online (tho unclear why booker would request this rather than rebooking - this
// way they lose their payment). 

if ($helper_type == 'cancel_reservation') {

    $reservation_number = $_POST['reservation_number'];
    $number_of_slots_per_hour = $_POST['number_of_slots_per_hour'];

    // get details of the reservation

    $sql = "SELECT
                reservation_date,
                reservation_slot,
                reservation_status,
                reserver_id,
                reservation_type
            FROM ecommerce_reservations
            WHERE
                reservation_number = '$reservation_number';";

    $result = mysqli_query($con, $sql);

    if (!$result) {

        echo "Oops - database access %failed%. $page_title Loc 17. Error details follow<br><br> " . mysqli_error($con);
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    $row = mysqli_fetch_array($result);
    $reservation_date = $row['reservation_date'];
    $reservation_slot = $row['reservation_slot'];
    $reservation_status = $row['reservation_status'];
    $reserver_id = $row['reserver_id'];
    $reservation_type = $row['reservation_type'];

    $sql = "DELETE FROM ecommerce_reservations
            WHERE
                reservation_number = '$reservation_number';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 17a. Error details follow<br><br> " . mysqli_error($con);
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    if ($reservation_type == "email") {

        $appointment_string = slot_date_to_string($reservation_slot, $reservation_date, $number_of_slots_per_hour);

        $mailing_address = $reserver_id;
        $mailing_title = "Your cancelled reservation at " . SHOP_NAME;
        $mailing_message = " 
                        <p>Dear Customer</p>
                        <p>Just to confirm that your reservation at $appointment_string has been cancelled at your request</p>";

        $mailing_result = send_email_via_postmark($mailing_address, $mailing_title, $mailing_message);

    }
}

#####################  'delete_unconfirmed_reservations' #####################
#
// delete any unconfirmed reservations more than 1 hour old. These would be seen where a
// booker had selected a date and clicked a Paypal button but had not completed the txn

if ($helper_type == 'delete_unconfirmed_reservations') {

// see https://stackoverflow.com/questions/3433465/mysql-delete-all-rows-older-than-10-minutes

    $sql = "DELETE FROM ecommerce_reservations
            WHERE
                reservation_time_stamp < DATE_SUB(NOW(),INTERVAL 60 MINUTE) AND
                reservation_status = 'U'";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 18. Error details follow<br><br> " . mysqli_error($con);
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }
}

#####################  'build_bank_holiday_table_for_month_display' ####################

if ($helper_type == 'build_bank_holiday_table_for_month_display') {

    $year = $_POST['year'];
    $month = $_POST['month'];

    $length_of_month = date("t", strtotime("$year-$month"));

    $return1 = '<p>Bank Holiday settings for ' . $month_name_array[$month - 1] . ' ' . $year . '</p>
        <p style = "margin-top: .5em;">Click to set/unset Bank Holiday days</p>';
    $return2 = '<p><img style="height: 1.25em; margin-left: 2em; " src = "img/media-stop-red.svg"> = Bank Holidays</p>';

    $return2 .= '
    <table class="table" style="border-collapse: separate; border-spacing: .5vw; ">
        <thead>
            <th>Sun</th>
            <th>Mon</th>        
            <th>Tue</th>
            <th>Wed</th>
            <th>Thu</th>
            <th>Fri</th>
            <th>Sat</th>
        </thead>
        <tbody>
        <tr>';

// get the bank holidays for the given month

    $sql = "SELECT 
                bank_holiday_day
            FROM ecommerce_bank_holidays
            WHERE 
                bank_holiday_year = '$year' AND
                bank_holiday_month = '$month';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo("Oops - database access %failed%. $page_title Loc 19. Error details follow<br><br> " . mysqli_error($con));
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }


// initialise a "day_is_bank_holiday[day]" array

    $day_is_bank_holiday = array();
    for ($j = 0; $j < 31; $j++) {
        $day_is_bank_holiday[$j] = 0;
    }

// now remove any days that are already recorded as bank holidays

    while ($row = mysqli_fetch_array($result)) {
        $bank_holiday_day = $row['bank_holiday_day'];
        $day_is_bank_holiday[$bank_holiday_day - 1] = 1;
    }

    for ($i = 1; $i <= $length_of_month; $i++) {

        $date = $year . "-" . $month . "-" . $i;
        $date_array_coordinates = date_to_display_coordinates($date);

        $date_row = $date_array_coordinates[0];
        $date_col = $date_array_coordinates[1];

        if ($i == 1) {
            for ($j = 0; $j < $date_col; $j++) {
                $return2 .= '<td></td>';
            }
        }

        if ($day_is_bank_holiday[$i - 1] == 1) {
            $return2 .= '<td style="background: red; cursor: pointer; " 
                            onclick="toggleBankHoliday(' . $year . ', ' . $month . ', ' . $i . '); ">' . $i . '</td>';
        } else {
            $return2 .= '<td style="background: aquamarine; cursor: pointer;" 
                            onclick="toggleBankHoliday(' . $year . ', ' . $month . ', ' . $i . '); ">' . $i . '</td>';
        }
        if ($date_col == 6) {
            $return2 .= '</tr>';
            if ($i < $length_of_month) {
                $return2 .= '<tr>';
            }
        }
    }

    $return2 .= '
            </tbody>
        </table>
        <button id = "advancemonth" style="margin-bottom: 1em;" onclick = "advanceMonthDisplay();"><img src = "img/caret-bottom.svg"></button>';

// wrap the two returns up in a json

    $return1 = prepareStringforXMLandJSONParse($return1);
    $return2 = prepareStringforXMLandJSONParse($return2);

    $json_string = '<returns>{"return1": "' . $return1 . '", "return2": "' . $return2 . '"}</returns>';

    header("Content-type: text/xml");
    echo "<?xml version='1.0' encoding='UTF-8'?>";
    echo $json_string;
}

#####################  toggle_bank_holiday ####################

if ($helper_type == "toggle_bank_holiday") {

    $bank_holiday_year = $_POST['bank_holiday_year'];
    $bank_holiday_month = $_POST['bank_holiday_month'];
    $bank_holiday_day = $_POST['bank_holiday_day'];


    $sql = "SELECT COUNT(*)
            FROM ecommerce_bank_holidays
            WHERE
                bank_holiday_year = '$bank_holiday_year' AND
                bank_holiday_month = '$bank_holiday_month'AND
                bank_holiday_day = '$bank_holiday_day';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo("Oops - database access %failed%. $page_title Loc 20. Error details follow<br><br> " . mysqli_error($con));
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    $row = mysqli_fetch_array($result);

    if ($row['COUNT(*)'] == 1) {

        $sql = "DELETE FROM ecommerce_bank_holidays
                    WHERE
                bank_holiday_year = '$bank_holiday_year' AND
                bank_holiday_month = '$bank_holiday_month'AND
                bank_holiday_day = '$bank_holiday_day';";

        $result = mysqli_query($con, $sql);

        if (!$result) {
            echo("Oops - database access %failed%. $page_title Loc 21. Error details follow<br><br> " . mysqli_error($con));
            require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
            exit(1);
        }
    } else {

        $sql = "INSERT INTO ecommerce_bank_holidays (
                bank_holiday_year,
                bank_holiday_month,
                bank_holiday_day)
            VALUES (
                '$bank_holiday_year',
                '$bank_holiday_month',
                '$bank_holiday_day');";

        $result = mysqli_query($con, $sql);

        if (!$result) {
            echo("Oops - database access %failed%. $page_title Loc 22. Error details follow<br><br> " . mysqli_error($con));
            require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
            exit(1);
        }
    }
}

#####################  'build_staff_holiday_table_for_month_display' ####################

if ($helper_type == 'build_staff_holiday_table_for_month_display') {

    $year = $_POST['year'];
    $month = $_POST['month'];
    $chair_number = $_POST['chair_number'];

// get chair_owner for chair_number

    $sql = "SELECT 
                chair_owner
            FROM ecommerce_work_patterns
            WHERE 
                chair_number = '$chair_number';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo("Oops - database access %failed%. $page_title Loc 23. Error details follow<br><br> " . mysqli_error($con));
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    $row_count = 0;

    while ($row = mysqli_fetch_array($result)) {
        $row_count++;
        $chair_owner = $row['chair_owner'];
    }

    if ($row_count == 0) {
        echo "number_of_chairs exceeded";
        exit(0);
    }

    $length_of_month = date("t", strtotime("$year-$month"));

    $return1 = '<p>Staff Absence settings for ' . $month_name_array[$month - 1] . ' ' . $year . '.</p> 
                <button onclick = "retardChairNum();"><img src = "img/caret-left.svg"></button>&nbsp;
                <span id = "staffholidaychairnum">' . $chair_number . '</span>&nbsp;
                <button onclick = "advanceChairNum();"><img src = "img/caret-right.svg"></button> : 
                <span>' . $chair_owner . '</span>
                <p style = "margin-top: .5em;">Click to set/unset Holidays</p>';

    $return2 = '<p><img style="height: 1.25em; margin-left: 2em; " src = "img/media-stop-red.svg"> = Staff Absences</p>';

    $return2 .= '
    <table class="table" style="border-collapse: separate; border-spacing: .5vw; ">
        <thead>
            <th>Sun</th>
            <th>Mon</th>        
            <th>Tue</th>
            <th>Wed</th>
            <th>Thu</th>
            <th>Fri</th>
            <th>Sat</th>
        </thead>
        <tbody>
        <tr>';

// get the staff absences for the given month and chair_number

    $sql = "SELECT 
                staff_holiday_day
            FROM ecommerce_staff_holidays
            WHERE 
                staff_holiday_year = '$year' AND
                staff_holiday_month = '$month' AND
                chair_number = '$chair_number';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo("Oops - database access %failed%. $page_title Loc 24. Error details follow<br><br> " . mysqli_error($con));
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

// initialise a "day_is_staff_holiday[day]" array

    $day_is_staff_holiday = array();
    for ($j = 0; $j < 31; $j++) {
        $day_is_staff_holiday[$j] = 0;
    }

// now remove any days that are already recorded as staff absence

    while ($row = mysqli_fetch_array($result)) {
        $staff_holiday_day = $row['staff_holiday_day'];
        $day_is_staff_holiday[$staff_holiday_day - 1] = 1;
    }

    for ($i = 1; $i <= $length_of_month; $i++) {

        $date = $year . "-" . $month . "-" . $i;
        $date_array_coordinates = date_to_display_coordinates($date);

        $date_row = $date_array_coordinates[0];
        $date_col = $date_array_coordinates[1];

        if ($i == 1) {
            for ($j = 0; $j < $date_col; $j++) {
                $return2 .= '<td></td>';
            }
        }

        $timestamp = strtotime($date);
        $day_of_week = date("w", $timestamp); // 0 (Sun)  - 6 

        if ($day_is_staff_holiday[$i - 1] == 1) {
            $return2 .= '<td style="background: red; cursor: pointer; " 
                            onclick="toggleStaffHoliday(' . $year . ', ' . $month . ', ' . $i . ', ' . $chair_number . '); ">' . $i . '</td>';
        } else {
            $return2 .= '<td style="background: aquamarine; cursor: pointer;" 
                            onclick="toggleStaffHoliday(' . $year . ', ' . $month . ', ' . $i . ', ' . $chair_number . '); ">' . $i . '</td>';
        }
        if ($date_col == 6) {
            $return2 .= '</tr>';
            if ($i < $length_of_month) {
                $return2 .= '<tr>';
            }
        }
    }

    $return2 .= '
            </tbody>
        </table>
        <button id = "advancemonth" style="margin-bottom: 1em;" onclick = "advanceMonthDisplay();"><img src = "img/caret-bottom.svg"></button>';

// wrap the two returns up in a json

    $return1 = prepareStringforXMLandJSONParse($return1);
    $return2 = prepareStringforXMLandJSONParse($return2);

    $json_string = '<returns>{"return1": "' . $return1 . '", "return2": "' . $return2 . '"}</returns>';

    header("Content-type: text/xml");
    echo "<?xml version='1.0' encoding='UTF-8'?>";
    echo $json_string;
}

#####################  toggle_staff_holiday ####################

if ($helper_type == "toggle_staff_holiday") {

    $staff_holiday_year = $_POST['staff_holiday_year'];
    $staff_holiday_month = $_POST['staff_holiday_month'];
    $staff_holiday_day = $_POST['staff_holiday_day'];
    $chair_number = $_POST['chair_number'];



    $sql = "SELECT COUNT(*)
            FROM ecommerce_staff_holidays
            WHERE
                staff_holiday_year = '$staff_holiday_year' AND
                staff_holiday_month = '$staff_holiday_month' AND
                staff_holiday_day = '$staff_holiday_day'AND
                chair_number = '$chair_number';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo("Oops - database access %failed%. $page_title Loc 25. Error details follow<br><br> " . mysqli_error($con));
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    $row = mysqli_fetch_array($result);

    if ($row['COUNT(*)'] == 1) {

        $sql = "DELETE FROM ecommerce_staff_holidays
                    WHERE
                staff_holiday_year = '$staff_holiday_year' AND
                staff_holiday_month = '$staff_holiday_month' AND
                staff_holiday_day = '$staff_holiday_day'AND
                chair_number = '$chair_number';";

        $result = mysqli_query($con, $sql);

        if (!$result) {
            echo("Oops - database access %failed%. $page_title Loc 26. Error details follow<br><br> " . mysqli_error($con));
            require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
            exit(1);
        }
    } else {

        $sql = "INSERT INTO ecommerce_staff_holidays (
                staff_holiday_year,
                staff_holiday_month,
                staff_holiday_day,
                chair_number)
            VALUES (
                '$staff_holiday_year',
                '$staff_holiday_month',
                '$staff_holiday_day',
                '$chair_number');";

        $result = mysqli_query($con, $sql);

        if (!$result) {
            echo("Oops - database access %failed%. $page_title Loc 27. Error details follow<br><br> " . mysqli_error($con));
            require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
            exit(1);
        }
    }
}

#####################  'build_work_pattern_table_for_week_display' ####################

if ($helper_type == 'build_work_pattern_table_for_week_display') {

    $chair_number = $_POST['chair_number'];
    $number_of_slots_per_hour = $_POST['number_of_slots_per_hour'];

    $slot_length = 60 / $number_of_slots_per_hour;

// get chair_owner for chair_number

    $sql = "SELECT 
                chair_owner
            FROM ecommerce_work_patterns
            WHERE 
                chair_number = '$chair_number';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo("Oops - database access %failed%. $page_title Loc 28. Error details follow<br><br> " . mysqli_error($con));
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    $row_count = 0;

    while ($row = mysqli_fetch_array($result)) {
        $row_count++;
        $chair_owner = $row['chair_owner'];
    }

    if ($row_count == 0) {
        echo "number_of_chairs exceeded";
        exit(0);
    }

# now get the record and use it to build returns for elements
# "patterndisplay1" and "patterndisplay2" 

    $sql = "SELECT 
                    chair_number,
                    chair_owner,
                    pattern_json
            FROM ecommerce_work_patterns
            WHERE 
                chair_number = '$chair_number';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 29. Error details follow<br><br> " . mysqli_error($con);
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    $row = mysqli_fetch_array($result);
    $chair_number = $row['chair_number'];
    $chair_owner = $row['chair_owner'];
    $pattern_json = $row['pattern_json'];

// if the json is defined, use json_decode to turn it into an asociative array
// otherwise create an empty array manually

    if ($pattern_json == '' || $pattern_json == '[]') {

        $json_array = array();
        for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {
            for ($j = 0; $j < 7; $j++) {

                $json_array[$i]['working'][$j] = 0;
            }
        }
    } else {

        $json_array = json_decode($pattern_json, true);
    }

    $return1 = '
            <p>Standard weekly work-patterns for Chair : </p>
            <button onclick = "retardChairNum();"><img src = "img/caret-left.svg"></button>&nbsp;
            <span id = "staffholidaychairnum">' . $chair_number . '</span>&nbsp;
            <button onclick = "advanceChairNum();"><img src = "img/caret-right.svg"></button> : 
            <span>' . $chair_owner . '</span>
            <p style="margin-top: .5em;">
                <span>Click to set/unset slots</span><br>
                <span>Hold Shift key and Click to set a range of slots</span>
            </p>';


    $return1 = prepareStringforXMLandJSONParse($return1);

    $return2 = '
    <table class="table" style="border-collapse: separate; border-spacing: .5vw;">
        <thead>
            <th>Time</th>
            <th>Sun</th>
            <th>Mon</th>        
            <th>Tue</th>
            <th>Wed</th>
            <th>Thu</th>
            <th>Fri</th>
            <th>Sat</th>
        </thead>
        <tbody>';

    for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {

        $hour = floor($i / $number_of_slots_per_hour);
        $minutes = ($i % $number_of_slots_per_hour) * (60 / $number_of_slots_per_hour);
        $hour_padded = sprintf('%02d', $hour);  // pad out with leading zeros : neat
        $minutes_padded = sprintf('%02d', $minutes);

        $return2 .= '<tr><td>' . $hour_padded . ':' . $minutes_padded . '</td>';
        for ($j = 0; $j < 7; $j++) {
            if ($json_array[$i]['working'][$j] == 0) {
                $return2 .= '<td><input type = "checkbox" id = "jr' . $i . 'c' . $j . '" onclick = "handlePatternClick(' . $i . ',' . $j . ');"></td>';
            } else {
                $return2 .= '<td><input type = "checkbox" id = "jr' . $i . 'c' . $j . '" onclick = "handlePatternClick(' . $i . ',' . $j . ');" checked></td>';
            }
        }
        $return2 .= '</tr>';
    }
    $return2 .= '
        </table>
        <p style = "text-align: center;">
            <button  class="btn btn-primary" onclick = "savePattern(' . $chair_number . ');">Save</button>&nbsp;&nbsp;&nbsp;
            <span id = "savepatternresult" style = "display: none;"></span>
        </p>';
    $return2 = prepareStringforXMLandJSONParse($return2);

// wrap the returns up in yet another json

    $json_string = '<returns>{"return1": "' . $return1 . '", "return2": "' . $return2 . '"}</returns>';

    header("Content-type: text/xml");
    echo "<?xml version='1.0' encoding='UTF-8'?>";
    echo $json_string;
}

#####################  save_pattern ####################

if ($helper_type == "save_pattern") {

    $chair_number = $_POST['chair_number'];
    $pattern_json = $_POST['pattern_json'];

    $sql = "UPDATE ecommerce_work_patterns SET
                pattern_json = '$pattern_json'
            WHERE
                chair_number = '$chair_number';";

    $result = mysqli_query($con, $sql);
    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 30. Error details follow<br><br> " . mysqli_error($con);
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    } else {
        echo "Save succeeded";
    }
}

#####################  build_check_display ####################

if ($helper_type == "build_check_display") {
    $number_of_slots_per_hour = $_POST['number_of_slots_per_hour'];

    $slot_length = 60 / $number_of_slots_per_hour;

    $today_day = date("j");
    $today_month = date("n"); // (1-12)
    $today_year = date("Y");

    $current_month = $today_month;
    $current_year = $today_year;

    $return = '';
    $check_display_length = 0;

// Although the code below has been written with the abiility to report on an 
// arbitarry range of months, it seems more practical to limit it to just the
// current and the next month

    $number_of_months_in_report = 2;

    for ($i = 0; $i < $number_of_months_in_report; $i++) {

        build_availability_variables_for_month($current_year, $current_month);

// When a member of staff goes sick, a full appointment book represents a major 
// administrative problem. Suddenly there are a bunch of slots = possibly spanning
// a whole block of days - with booked appointmnts that can't be fulfilled. Somehow
// these bookers have to be contacted so that they don't turn up and make a fuss. 
// But which ones? For a given slot you have both telephone and email (prepaid!)
// bookers. You may still have some staff to partially service the slot, so which 
// customers do you tell you're going to have to rebook them? Email (prepaid) ones
// are easier because we can email them in a single batch and invite them to rebook
// /themselves/ - ie put the ball in their court. There's no easy way out for telpehone
// bookers - you're going to have to contact them individually and rebook them
// over the phone. Groan!
// What's clear at this point is that we're going to have to deal with the appointments
// for a slot as a /group/ (eg, we need to know how many of them are emails and how 
// many are telephone), but at the moment they only exist as separate reservationrecords
// on the database, and this makes them very difficult to deal with. So, on this first 
// pass through the database, create a more useful data structure, compromised_slots,
// to group them up by slot - see comments in the "build" function below for details
// of the structure of compromised_slots.    

        build_compromised_slot_array_for_month($current_year, $current_month);

// OK, let's test this out. Suppose we want to build output for the current month
// range displaying compromised slots for each month inviting manual re-book.
// scoot through the whole compromised_slots array for each month - not very efficient
// but things are complicated enough as it is and at least everythng is in memory now.
// We might in future take advantage of the fact that the entries are in date order, but not
// just now.

        $current_month_alpha = $month_name_array[$current_month - 1];
        $number_of_compromised_slots_for_this_month = 0;

        $return .= "<p style='color: blue; font-weight: bold;'>Unresourced reservation slots for $current_month_alpha $current_year : </p>";

        for ($j = 0; $j < count($compromised_slots_array); $j++) {

            $reservation_slot = $compromised_slots_array[$j]['slot'];
            $reservation_date = $compromised_slots_array[$j]['date'];
            $reservation_day = $compromised_slots_array[$j]['day'];
            $reservation_emailtotal = $compromised_slots_array[$j]['emailtotal'];

            $hour = floor($reservation_slot / $number_of_slots_per_hour);
            $minutes = ($reservation_slot % $number_of_slots_per_hour) * (60 / $number_of_slots_per_hour);
            $hour_padded = sprintf('%02d', $hour);  // pad out with leading zeros : neat
            $minutes_padded = sprintf('%02d', $minutes);

            $staffing_required = count($compromised_slots_array[$j]['reservations']);
            $staffing_available = $slot_availability_array[$reservation_slot][$reservation_day - 1];
            if ($staffing_available < 0)
                $staffing_available = 0;

// The format of the checkbox id for a compromised slot is "cr" + cr$reservation_slot. 
// Tuck the slot, date and number of email reservations for the slot away as comma-separated 
// values in a hidden span with id "crs" + cr$reservation_slot

            $return .= "
                    <p style='margin-bottom: 1em;'>
                        <input type = 'checkbox' id = 'cr$j' onclick = 'handleCheckClick($j);'>
                           <span id = 'crkey$j' style = 'display: none;'>$reservation_slot,$reservation_date,$reservation_emailtotal</span>
                           <span> The $hour_padded:$minutes_padded slot on $reservation_date is under-resourced : 
                                    staffing required - $staffing_required" . " : " . "
                                    staffing available - $staffing_available
                            </span>
                     </p>";

            $check_display_length ++;
            $number_of_compromised_slots_for_this_month++;

            for ($k = 0; $k < count($compromised_slots_array[$j]['reservations']); $k++) {

                $reservation_number = $compromised_slots_array[$j]['reservations'][$k]['reservation_number'];
                $reserver_id = $compromised_slots_array[$j]['reservations'][$k]['reserver_id'];

                $return .= "<span style = 'color: red;'>";

                if ($compromised_slots_array[$j]['reservations'][$k]['reservation_type'] == "telephone") {
                    $return .= "Telephone : $reserver_id : (unpaid) </span>";
                    $return .= "<button class='btn btn-primary' style='margin-bottom: 3px;' onclick = 'rebookReservation($reservation_number);'>Rebook</button></br>";
                } else {
                    $return .= "Email : $reserver_id : (paid) </span></br>";
                }
                

            }
            $return .= "<br>";
        }

        if ($number_of_compromised_slots_for_this_month == 0) {
            $return .= "<p>There are no unresourced slots for this month</p><br>";
        }

        $current_month++;
        if ($current_month > 12) {
            $current_year++;
            $current_month = 1;
        }
    }


    $return .= "<span id='checkdisplaylength' style='display: none'>$check_display_length</span>";

    echo $return;
}

#####################  'issue_reservation_apologies' ####################

if ($helper_type == 'issue_reservation_apologies') {

// This routine messages the first "email" booker for each over-booked slots in a given date range. The email
// tells the customer that due to circumstances etc you can't fulfill their reservation. The
// message includes a link referencing their original reservation number inviting them to rebook. 
// Meanwhile their original reservation is marked as "postponed". The shop management would
// repeat this as necessary, depending on how many staff you were down. Of course, this doesn't
// completely fix the problem (a compromised slot might be telephone bookers, for example).
// However it's a start. Once you'd cleared as many email bookers as you could, you'd get onto 
// the phone! To help things along, the compromised telephone reservations would be displayed
// as "change" links so, with the booker on the phone you can rebook them just by clicking 
// on their reservation entry in the check  list

    $slot_rows_string = $_POST['slot_rows'];
    $number_of_slots_per_hour = $_POST['number_of_slots_per_hour'];

    $slot_rows_array = json_decode($slot_rows_string, true);

    for ($i = 0; $i < count($slot_rows_array); $i++) {

        $reservation_slot = $slot_rows_array[$i]['slot'];
        $reservation_date = $slot_rows_array[$i]['slotdate'];

        $sql = "SELECT
                    reservation_number,
                    reservation_date,
                    reservation_slot,
                    reservation_status,
                    reserver_id,
                    reservation_type
                FROM ecommerce_reservations
                WHERE
                    reservation_date = '$reservation_date' AND 
                    reservation_slot  = '$reservation_slot';";

        $resulta = mysqli_query($con, $sql);

        if (!$resulta) {
            echo "Oops - database access %failed%. $page_title Loc 12. Error details follow<br><br> " . mysqli_error($con);
            require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
            exit(1);
        }

        while ($rowa = mysqli_fetch_array($resulta, MYSQLI_BOTH)) {

            $reservation_number = $rowa['reservation_number'];
            $reserver_id = $rowa['reserver_id'];
            $reservation_type = $rowa['reservation_type'];

            if ($reservation_type == 'email') {

// OK - we're in business - cancel and email this booker

                $sql = "START TRANSACTION;";

                $resultb = mysqli_query($con, $sql);
                if (!$resultb) {
                    echo "Oops - database access %failed%. $page_title Loc 31. Error details follow<br><br> " . mysqli_error($con);
                    require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
                    exit(1);
                }

                $sql = "UPDATE ecommerce_reservations SET
                            reservation_status = 'P'
                        WHERE
                            reservation_number = '$reservation_number';";

                $resultc = mysqli_query($con, $sql);
                if (!$resultc) {
                    echo "Oops - database access %failed%. $page_title Loc 32. Error details follow<br><br> " . mysqli_error($con);
                    require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
                    exit(1);
                }

                $appointment_string = slot_date_to_string($reservation_slot, $reservation_date, $number_of_slots_per_hour);
                $rebookerlink = **CONFIG REQUIRED**/ecommerce_admin/booker/booker.html?mode=change&resnum=$reservation_number";

                $mailing_address = $reserver_id;
                $mailing_title = "Your reservation at " . SHOP_NAME;
                $mailing_message = " 
                        <p>Dear Customer</p>
                        <p>Due to circumstances beyond our control we are now unable to fufil your reservation 
                            for $appointment_string</p>
                         <p>Please click the following link to choose another time        
                            $rebookerlink" . "</p>";

                $mailing_result = send_email_via_postmark($mailing_address, $mailing_title, $mailing_message);

// if we haven't managed to send a renewal email, just carry on (may just have a duff email address)
/*
                if (strpos($mailing_result, "%problem%") !== false) {

                    $sql = "ROLLBACK;";
                    $result = mysqli_query($con, $sql);
                    require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
                    echo "%failed% - live but couldn't send rebooking email";
                    exit(1);
                }
*/
                $sql = "COMMIT;";

                $resultd = mysqli_query($con, $sql);
                if (!$resultd) {
                    require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
                    echo "%failed% - couldn't commit";
                    exit(1);
                }
                break;
            }
        }
    }
    echo ("mailing succeeded");
}


require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');


