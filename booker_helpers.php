<?php

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
# 'delete_reservation'              -   delete the reservation for supplied reservation_number                                 
#
# 'delete_unconfirmed_reservations' -   delete any unconfirmed reservations more than 1 hour old
#
# 'build_bank_holiday_table_for_month_display'   -   create display table for supplied year (yyyy), month (1 - 12)
#                                                    showing bank holidays
#                                                    
# 'toggle_bank_holiday'             -   toggle bank holiday record  in and out of existence for supplied date                                               
#                                                    
# 'build_staff_holiday_table_for_month_display'   -   create display table for supplied year (yyyy), month and chair_number
#                                                    showing staff holidays  
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
# 'check_reservation_existence'     -   Return "reservation present/absent" for supplied reservation_number

$page_title = 'booker_helpers';

# set headers to NOT cache the page
header("Cache-Control: no-cache, must-revalidate"); //HTTP 1.1
header("Pragma: no-cache"); //HTTP 1.0
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

date_default_timezone_set('Europe/London');

# Connect to the database

require ('/home/qfgavcxt/connect_ecommerce_prototype.php');

$helper_type = $_POST['helper_type'];

// Useful parameters

$month_name_array = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

// Appointment-availability specification

$number_of_slots_per_hour = 4;

require ('../includes/booker_globals.php');

function date_to_display_coordinates($date) {

# Takes a date in mysql date format ("yyyy-mm-dd") and returns an array
# where the first element is the the row the date would occupy in the calendar
# display for the date month (a value ranging from 0 to 5), and the second
# element is the column it would occupy (a value ranging from 0 to 6, where
# 0 represents Sunday)

    $timestamp = strtotime($date);
//echo 'date("N", $timestamp) = ' . date("N", $timestamp) . "/n";
    $column = (date("N", $timestamp)) % 7; # (0 - 6)
    $day = date("d", $timestamp); # (01 to 31)
    $row = floor(($day - 1) / 7);

# create an array to accomodate the returns (PHP can only return a single variable)

    $returns = array();
    array_push($returns, $row);
    array_push($returns, $column);

    return $returns;
}

function build_availability_variables_for_month($year, $month) {

// Build a $slot-availability_array(slot, day) array for the given month that shows the number of chairs available
// for each slot/day combination. 
// Also build a day_available array[day] summary for $slot_availability_array showing each column that has at least one chair
// available in one of its slots     

    require ('../includes/booker_globals.php');

// Initialise the array for 24*$number_of_slots_per_hour and 31 days (ie potential max) as $number_of_chairs
// We don't actually know the number of chairs at this point so find out

    $sql = "SELECT COUNT(*)
            FROM ecommerce_work_patterns;";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        error_log("Oops - database access %failed%. $page_title Loc 3. Error details follow<br><br> " . mysqli_error($con));
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    $row = mysqli_fetch_array($result);
    $number_of_chairs = $row['COUNT(*)'];

    $slot_availability_array = array();

    $length_of_month = date("t", strtotime("$year-$month"));

    for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {
        for ($j = 0; $j < $length_of_month; $j++) {
            $slot_availability_array[$i][$j] = $number_of_chairs;
        }
    }

// now reduce slot-availabilities due to work_patterns

    $sql = "SELECT 
                chair_number,
                pattern_json
            FROM ecommerce_work_patterns;";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        error_log("Oops - database access %failed%. $page_title Loc 4. Error details follow<br><br> " . mysqli_error($con));
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    $earliest_working_hour = 24;
    $latest_working_hour = 0;

    while ($row = mysqli_fetch_array($result)) {
        $chair_number = $row['chair_number'];
        $pattern_json = $row['pattern_json'];

// turn pattern_json into an array weekday_pattern_availability[slot][weekday] where 1 means chair available for slot/weekday
        $weekday_pattern_availability = json_decode($pattern_json, true); //  returns associative array
// now unfold this into a day_pattern_json[slot][day] noting that the json is in hours and we want slots

        $day_pattern_availability = array();

        for ($j = 1; $j <= $length_of_month; $j++) {
            $day_of_week = date("w", strtotime("$year-$month-$j")); // 0 - 6 : Note that the - here are dashes, not minuses 
            for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {
                $hour = floor($i / 4);
                $day_pattern_availability[$i][$j - 1] = $weekday_pattern_availability[$hour]['working'][$day_of_week];
            }
        }

// now apply uanavailabilities to the $slot_availability_array
//  - if date_pattern[i][j] = 0 (unavailable) take one off 
//  - if date_pattern[i][j] = 1 (available) leave unchanged


        for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {
            for ($j = 0; $j < $length_of_month; $j++) {
                $slot_availability_array[$i][$j] = $slot_availability_array[$i][$j] - (1 - $day_pattern_availability[$i][$j]);
            }
        }

// while you're at it, use the $weekday_pattern_availability array to calculate
// $earliest_working_hour and  $latest_working_hour variables as an act of kindness
// to the build_calendar_day_display routine that will use them to blank out uesles
// early and late rows from the display

        for ($j = 0; $j < 7; $j++) {
            for ($i = 0; $i < 24; $i++) {

                if ($i < $earliest_working_hour && $weekday_pattern_availability[$i]['working'][$j] != 0)
                    $earliest_working_hour = $i;
                if ($i > $latest_working_hour && $weekday_pattern_availability[$i]['working'][$j] != 0)
                    $latest_working_hour = $i;
            }
        }
    }

// now remove availability for whole days due to bank holidays in the given month

    $sql = "SELECT
      bank_holiday_day
      FROM ecommerce_bank_holidays
      WHERE
      bank_holiday_year = '$year' AND
      bank_holiday_month = '$month';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        error_log("Oops - database access %failed%. $page_title Loc 5. Error details follow<br><br> " . mysqli_error($con));
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    while ($row = mysqli_fetch_array($result)) {
        $bank_holiday_day = $row['bank_holiday_day'];

        for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {
            $slot_availability_array[$i][$bank_holiday_day - 1] = 0;
        }
    }

// now reduce availability for whole days due to staff holidays

    $sql = "SELECT
      staff_holiday_day
      FROM ecommerce_staff_holidays
      WHERE
      staff_holiday_year = '$year' AND
      staff_holiday_month = '$month';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        error_log("Oops - database access %failed%. $page_title Loc 6. Error details follow<br><br> " . mysqli_error($con));
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }


    while ($row = mysqli_fetch_array($result)) {

        $staff_holiday_day = $row['staff_holiday_day'];

        for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {
            $slot_availability_array[$i][$staff_holiday_day - 1] = $slot_availability_array[$i][$staff_holiday_day - 1] - 1;
        }
    }

// Finally, reduce availabiity due to bookings already taken for this month

    $sql = "SELECT
                reservation_slot,
                reservation_date
            FROM ecommerce_reservations
            WHERE
                reservation_date >= '$year" . "-" . $month . "-01' AND
                reservation_date <= '$year" . "-" . $month . "-$length_of_month'";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        error_log("Oops - database access %failed%. $page_title Loc 7. Error details follow<br><br> " . mysqli_error($con));
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    while ($row = mysqli_fetch_array($result)) {

        $reservation_slot = $row['reservation_slot'];
        $reservation_date = $row['reservation_date'];
        $reservation_day = date("j", strtotime($reservation_date)); // 1-31

        $slot_availability_array[$reservation_slot][$reservation_day - 1] --;
    }

// generate a day_available array[day] summary for $slot_availability_array showing each column that has at least one chair
// available in one of its slots (as long as its date isn't earlier than today's date

    $day_available_array = array();
    $today_timestamp = strtotime(date("Y-m-d"));

    for ($j = 0; $j < $length_of_month; $j++) {
        $day_availability_array[$j] = 0;
        $day = $j + 1;
        $day_timestamp = strtotime(date("$year-$month-$day"));
        if ($day_timestamp >= $today_timestamp) {

            for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {
                if ($slot_availability_array[$i][$j] > 0) {
                    $day_availability_array[$j] = 1;
                }
            }
        }
    }
}

function prepareStringforXMLandJSONParse($input) {

# < , > and & must be turned into &lt; , &gt; and &amp; to get them through an XML return
# " must be turned into \\" to make them acceptable to JSON.Parse
# life feeds (\n) should be removed as we don't want them at all
# &nbsp; must be turned in " "
# &quot; must be turned into "'"
# 
# maybe should consider encodeURIComponent  see https://stackoverflow.com/questions/20960582/html-string-nbsp-breaking-json
# For JSON syntax see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/JSON
# Not clear from this why &nbsp; is breaking the return of the JSON - basically empty  - for 
# further info on URL encoding see https://www.urlencoder.io/learn/
# 
# You might have thought we would do this at the outset before these characters reached "helpers"
# but the problem is that escaped strings get unescaped when they're stored on the database. The
# "tag" characters < and > could probably have been dealt with at the outset, but it seems better
# to keep things together

    $output = $input;

    $output = str_replace('&', '&amp;', $output); ## haha - best do this first eh!!
    $output = str_replace('<', '&lt;', $output);
    $output = str_replace('>', '&gt;', $output);


    $output = str_replace('"', '\\"', $output);
    $output = str_replace('\n', '', $output);

    $output = str_replace('&nbsp;', ' ', $output);
    $output = str_replace('&quot;', "'", $output);

    return $output;
}

#####################  'build_calendar_month_display' ####################

if ($helper_type == 'build_calendar_month_display') {

    $year = $_POST['year'];
    $month = $_POST['month'];

    $length_of_month = date("t", strtotime("$year-$month"));

    $return = '<p>Availability for ' . $month_name_array[$month - 1] . ' ' . $year . '</p>
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

    for ($i = 1; $i <= $length_of_month; $i++) {

        $date = $year . "-" . $month . "-" . $i;
        $date_array_coordinates = date_to_display_coordinates($date);

        $date_row = $date_array_coordinates[0];
        $date_col = $date_array_coordinates[1];

        if ($i == 1) {
            for ($j = 0; $j < $date_col; $j++) {
                $return .= '<td></td>';
            }
        }


        if ($day_availability_array[$i - 1] == 1) {

            $return .= '<td style="background: aquamarine; cursor: pointer;" 
                            onclick="displayDay(' . $year . ',' . $month . ',' . $i . ');">' . $i . '</td>';
        } else {
            $return .= '<td style="background: red;">' . $i . '</td>';
        }

        if ($date_col == 6) {
            $return .= '</tr>';
            if ($i < $length_of_month) {
                $return .= '<tr>';
            }
        }
    }

    $return .= '
            </tbody>
        </table>
        <button id = "advancemonth" style="margin-bottom: 1em;" onclick = "advanceMonthDisplay();"><img src = "img/caret-bottom.svg"></button>';

    echo $return;
}

#####################  'build_calendar_day_display' ####################

if ($helper_type == 'build_calendar_day_display') {

    $year = $_POST['year'];
    $month = $_POST['month'];
    $day = $_POST['day'];
    $mode = $_POST['mode'];

    $reservation_date = $year . "-" . $month . "-" . $day;

# build 'availability' array for the slots for this date from the ecommerce_reservations,
# day_override and slot_override tables

    build_availability_variables_for_month($year, $month);

    $date_string = $year . "-" . $month . "-" . $day;
    $date = strtotime($date_string);

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

        $return .= '<th>+ .' . $i * (60 / $number_of_slots_per_hour) . '</th>';
    }

    $return .= '</thead>
                    <tbody>
                    <tr>';

// We need to trim off unavailable working hours at the beginning and end of the day
// to stop it flooding the screen with unavailable days. Use the $earliest_working_hour
// and $latest_working_hour global variables from build_availability_variables_for_month   

    for ($i = $earliest_working_hour; $i <= $latest_working_hour; $i++) {

        $return .= '<tr>
                            <td style="background: gainsboro;">' . $i . '.00</td>';
        for ($j = 0; $j < $number_of_slots_per_hour; $j++) {

            $reservation_slot = $i * $number_of_slots_per_hour + $j;
            $reservation_time_hours = floor($reservation_slot / $number_of_slots_per_hour);
            $reservation_time_minutes = 15 * ($reservation_slot % 4);
            $reservation_time = ($reservation_time_hours * 100) + $reservation_time_minutes;

            if ($mode == "email" || $mode == "telephone" || $mode == "change") {
                if ($slot_availability_array[$reservation_slot][$day - 1] == 0)
                    $return .= '<td style = "background: red;"></td>';
                if ($slot_availability_array[$reservation_slot][$day - 1] == 1)
                    $return .= '<td style = "background: palegreen;" onclick = "bookSlot(' . $year . ',' . $month . ',' . $day . ',' . $reservation_slot . ');"></td>';
                if ($slot_availability_array[$reservation_slot][$day - 1] > 1)
                    $return .= '<td style = "background: aquamarine;" onclick = "bookSlot(' . $year . ',' . $month . ',' . $day . ',' . $reservation_slot . ');"></td>';
            }
            if ($mode == "viewer") {
                if ($slot_availability_array[$reservation_slot][$day - 1] == 0)
                    $return .= '<td style = "background: red;" onclick = "viewSlot(' . $year . ',' . $month . ',' . $day . ',' . $reservation_slot . ');"></td>';
                if ($slot_availability_array[$reservation_slot][$day - 1] == 1)
                    $return .= '<td style = "background: palegreen;" onclick = "viewSlot(' . $year . ',' . $month . ',' . $day . ',' . $reservation_slot . ');"></td>';
                if ($slot_availability_array[$reservation_slot][$day - 1] > 1)
                    $return .= '<td style = "background: aquamarine;"></td>';
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

    if ($slot_availability_array[$reservation_slot][$day - 1] == 0) {
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

    $outgoing_reservation_number = $_POST['outgoing_reservation_number'];

    $year = $_POST['year'];
    $month = $_POST['month'];
    $day = $_POST['day'];
    $reservation_slot = $_POST['reservation_slot'];

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

// OK - now clear to delete the outgoing reservation and create the new one
// Start by getting the old reservation details

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
                '$reservation_status',
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

        if (($reservation_slot % 4) == 0) {
            $reservation_time = round($reservation_slot / 4) . ":00";
        } else {
            $reservation_time = floor($reservation_slot / 4) . ":" . ($reservation_slot % 4) * 15;
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

            $payment_status = "Paid";
            if ($reservation_type == "telephone")
                $payment_status = "Unpaid";

            $booking_time_object = strtotime($reservation_time_stamp);
            $booking_time = date('Y-m-d at H:i:s', $booking_time_object);

            $return .= "
                        <strong>Reservation $count:</strong>
                        <p>Contact details : $reserver_id</p>
                        <p>Reservation Reference : $reservation_number</p>
                        <p>Payment Status : $payment_status</p>
                        <Booking Time : $booking_time</p>";
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

#####################  'delete_reservation' ####################
//
// delete the specified reservation - this would be used where a booker cancelled
// a booking by clicking the cancel button below the paypal button

if ($helper_type == 'delete_reservation') {

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

#####################  'delete_unconfirmed_reservations' #####################
#
// delete any unconfirmed reservations more than 1 hour old. These would be seen where a
// booker had selected a date and clicked a Paypal button but had not completed the txn

if ($helper_type == 'delete_unconfirmed_reservations') {

// see https://stackoverflow.com/questions/3433465/mysql-delete-all-rows-older-than-10-minutes

    $sql = "DELETE FROM ecommerce_reservations
            WHERE
                reservation_time_stamp < (NOW() - INTERVAL 60 MINUTE) AND
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
        error_log("Oops - database access %failed%. $page_title Loc 19. Error details follow<br><br> " . mysqli_error($con));
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
        error_log("Oops - database access %failed%. $page_title Loc 20. Error details follow<br><br> " . mysqli_error($con));
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
            error_log("Oops - database access %failed%. $page_title Loc 21. Error details follow<br><br> " . mysqli_error($con));
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
            error_log("Oops - database access %failed%. $page_title Loc 22. Error details follow<br><br> " . mysqli_error($con));
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
        error_log("Oops - database access %failed%. $page_title Loc 23. Error details follow<br><br> " . mysqli_error($con));
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

    $return1 = '<p>Staff Holiday settings for ' . $month_name_array[$month - 1] . ' ' . $year . '. 
                <img src = "img/caret-left.svg" onclick = "retardChairNum();">&nbsp;
                <span id = "staffholidaychairnum">' . $chair_number . '</span>&nbsp;
                <img src = "img/caret-right.svg" onclick = "advanceChairNum();"> : 
                <span>' . $chair_owner . '</span>
                <p style = "margin-top: .5em;">Click to set/unset Holidays</p>';

    $return2 = '<p><img style="height: 1.25em; margin-left: 2em; " src = "img/media-stop-red.svg"> = Staff Holidays</p>';

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

// get the staff holidays for the given month and chair_number

    $sql = "SELECT 
                staff_holiday_day
            FROM ecommerce_staff_holidays
            WHERE 
                staff_holiday_year = '$year' AND
                staff_holiday_month = '$month' AND
                chair_number = '$chair_number';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        error_log("Oops - database access %failed%. $page_title Loc 24. Error details follow<br><br> " . mysqli_error($con));
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

// initialise a "day_is_staff_holiday[day]" array

    $day_is_staff_holiday = array();
    for ($j = 0; $j < 31; $j++) {
        $day_is_staff_holiday[$j] = 0;
    }

// now remove any days that are already recorded as staff holidays

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
        error_log("Oops - database access %failed%. $page_title Loc 25. Error details follow<br><br> " . mysqli_error($con));
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
            error_log("Oops - database access %failed%. $page_title Loc 26. Error details follow<br><br> " . mysqli_error($con));
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
            error_log("Oops - database access %failed%. $page_title Loc 27. Error details follow<br><br> " . mysqli_error($con));
            require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
            exit(1);
        }
    }
}


#####################  'build_work_pattern_table_for_week_display' ####################

if ($helper_type == 'build_work_pattern_table_for_week_display') {

    $chair_number = $_POST['chair_number'];

// get chair_owner for chair_number

    $sql = "SELECT 
                chair_owner
            FROM ecommerce_work_patterns
            WHERE 
                chair_number = '$chair_number';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        error_log("Oops - database access %failed%. $page_title Loc 28. Error details follow<br><br> " . mysqli_error($con));
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

# now get the record and build "patterndisplay1" and "patterndisplay2" for it

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

    $json_array = json_decode($pattern_json, true); //  returns associative array

    $return1 = '
            <p>Standard weekly work-patterns for Chair : </p>
            <img src = "img/caret-left.svg" onclick = "retardChairNum();">&nbsp;
            <span id = "staffholidaychairnum">' . $chair_number . '</span>&nbsp;
            <img src = "img/caret-right.svg" onclick = "advanceChairNum();"> : 
            <span>' . $chair_owner . '</span>
            <p style="margin-top: .5em;">Click to set/unset slots</p>';

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
        <tbody>
        <tr>
        <td></td>';

    for ($i = 0; $i < 7; $i++) {
        $return2 .= '<td><input type = "checkbox" id = "day' . $i . '" onclick="setPatternColumn(' . $i . ');"></td>';
    }
    $return2 .= '</tr>';

    for ($i = 0; $i < 24; $i++) {
        $return2 .= '<tr><td>' . $i . ':00</td>';
        for ($j = 0; $j < 7; $j++) {
            if ($json_array[$i]['working'][$j] == 0) {
                $return2 .= '<td><input type = "checkbox" id = "jr' . $i . 'c' . $j . '"></td>';
            } else {
                $return2 .= '<td><input type = "checkbox" id = "jr' . $i . 'c' . $j . '" checked></td>';
            }
        }
        $return2 .= '</tr>';
    }
    $return2 .= '
        </table>
        <p style = "text-align: center;">
            <button onclick = "savePattern(' . $chair_number . ');">Save</button>&nbsp;&nbsp;&nbsp;
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
#####################  'check_reservation_existence' ####################

if ($helper_type == 'check_reservation_existence') {

    $reservation_number = $_POST['reservation_number'];

    $sql = "SELECT COUNT(*) FROM ecommerce_reservations
            WHERE
                reservation_number = '$reservation_number';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 17. Error details follow<br><br> " . mysqli_error($con);
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    $row = mysqli_fetch_array($result);

    if ($row['COUNT(*)'] == 0) {
        echo "reservation absent";
    } else {
        echo "reservation present";
    }
}

require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
