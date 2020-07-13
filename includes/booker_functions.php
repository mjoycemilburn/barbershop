<?php

function build_availability_variables_for_month($year, $month) {

// Build $slot-availability_array(slot, day) and $bookings_taken(slot, day) arrays for the
// given month (1-31) that shows the number of chairs available for each slot/day combination
// and the number of bookings taken for those slots.. 
// Also build a day_available array[day] summary for $slot_availability_array showing each column that has at least one chair
// available in one of its slots     

    require ('../includes/booker_globals.php');

// Initialise the array for 24*$number_of_slots_per_hour and 31 days (ie potential max) as $number_of_chairs

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
    $bookings_taken_array = array();
    
    $length_of_month = date("t", strtotime("$year-$month"));

    for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {
        for ($j = 0; $j < $length_of_month; $j++) {
            $slot_availability_array[$i][$j] = $number_of_chairs;
            $bookings_taken_array[$i][$j] = 0;
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

    $earliest_working_slot = 24 * $number_of_slots_per_hour;
    $latest_working_slot = 0;

    while ($row = mysqli_fetch_array($result)) {
        $chair_number = $row['chair_number'];
        $pattern_json = $row['pattern_json'];

// if the json is defined, use json_decode to turn it into an asociative array,
// weekday_pattern_availability otherwise create an empty array manually.
// In weekday_pattern_availability[slot][weekday],  1 means chair available
//  for slot/weekday

        if ($pattern_json == '' || $pattern_json == '[]') {

            $weekday_pattern_availability = array();
            for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {
                for ($j = 0; $j < 7; $j++) {

                    $weekday_pattern_availability[$i]['working'][$j] = 0;
                }
            }
        } else {

            $weekday_pattern_availability = json_decode($pattern_json, true);
        }

// now unfold this into a day_pattern_json[slot][day] noting that the json is in hours and we want slots

        $day_pattern_availability = array();

        for ($j = 1; $j <= $length_of_month; $j++) {
            $day_of_week = date("w", strtotime("$year-$month-$j")); // 0 - 6 : Note that the - here are dashes, not minuses 
            for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {
                $day_pattern_availability[$i][$j - 1] = $weekday_pattern_availability[$i]['working'][$day_of_week];
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
// $earliest_working_slot and  $latest_working_slot variables as an act of kindness
// to the build_calendar_day_display routine that will use them to blank out uesles
// early and late rows from the display

        for ($j = 0; $j < 7; $j++) {
            for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {

                if ($i < $earliest_working_slot && $weekday_pattern_availability[$i]['working'][$j] != 0)
                    $earliest_working_slot = $i;
                if ($i > $latest_working_slot && $weekday_pattern_availability[$i]['working'][$j] != 0)
                    $latest_working_slot = $i;
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

// now reduce availability for whole days due to staff absence

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
            $slot_availability_array[$i][$staff_holiday_day - 1] --;
        }
    }

// Finally, reduce availabiity due to bookings already taken for this month
// Don't count P (postponed) or  U (unconfirmed) rewervations - just take Cs.   

    $sql = "SELECT
                reservation_slot,
                reservation_date
            FROM ecommerce_reservations
            WHERE
                reservation_status = 'C' AND
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

        $bookings_taken_array[$reservation_slot][$reservation_day - 1] ++;
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
                if ($slot_availability_array[$i][$j] - $bookings_taken_array[$i][$j] > 0) {
                    $day_availability_array[$j] = 1;
                }
            }
        }
    }
}

function build_compromised_slot_array_for_month($year, $month) {

    // build a two-dimensional array  showing reservation slots that are currently under-resourced as follows:
    // 
    // compromised_slots['date' 'year', 'month', 'day', 'slot', 'emailtotal', reservations[
    //                         ['reservation_number','reserver_id', 'reservation_type', 'reservation_status'], ....
    //                                                               ]]
    // 'date' is included (in mysql "date format - eg 2020-06-03) aslongside its component
    //  year, month, day elements (eg 2020, 6, 3), for convenience later
    // 'emailtotal' likewise shows the number of entries in reservations for email bookers - useful
    // when alerting users to the number of cancellation messages they're generating
    

    require ('booker_globals.php');

    $length_of_month = date("t", strtotime("$year-$month"));

    $sql = "SELECT
                reservation_number,
                reservation_date,
                reservation_slot,
                reservation_status,
                reserver_id,
                reservation_type
            FROM ecommerce_reservations
            WHERE
                reservation_date >= '$year" . "-" . $month . "-01' AND
                reservation_date <= '$year" . "-" . $month . "-$length_of_month'
            ORDER BY reservation_date, reservation_slot;";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        error_log("Oops - database access %failed%. $page_title Loc 31. Error details follow<br><br> " . mysqli_error($con));
        require ('/home/qfgavcxt/disconnect_ecommerce_prototype.php');
        exit(1);
    }

    $last_reservation_slot = 0;
    $last_reservation_date = '';
    $compromised_slot_index = -1;
    $compromised_slots_array = array();

    while ($row = mysqli_fetch_array($result)) {
        $reservation_number = $row['reservation_number'];
        $reservation_date = $row['reservation_date'];
        $reservation_slot = $row['reservation_slot'];
        $reservation_status = $row['reservation_status'];

        $reserver_id = $row['reserver_id'];
        $reservation_type = $row['reservation_type'];

        $reservation_day = date("j", strtotime($reservation_date));

        if ($slot_availability_array[$reservation_slot][$reservation_day - 1] - $bookings_taken_array[$reservation_slot][$reservation_day - 1] < 0) {

// OK - we've found a reservation for an under-resourced slot. If this is
// a slot we've not seen so far, start a new compromised_slots_array[] entry, 
// otherwise add it to the one you're already building

            if ($reservation_slot != $last_reservation_slot || $reservation_date != $last_reservation_date) {
//create new slot
                $compromised_slot_index++;
                $reservations_index = 0;
                $compromised_slots_array[$compromised_slot_index]['date'] = $reservation_date;
                $compromised_slots_array[$compromised_slot_index]['year'] = date("Y", strtotime($reservation_date));
                $compromised_slots_array[$compromised_slot_index]['month'] = date("n", strtotime($reservation_date)); // (1-12)
                $compromised_slots_array[$compromised_slot_index]['day'] = date("j", strtotime($reservation_date)); // (1-31)
                $compromised_slots_array[$compromised_slot_index]['emailtotal'] = 0; 
                $compromised_slots_array[$compromised_slot_index]['slot'] = $reservation_slot;
                $compromised_slots_array[$compromised_slot_index]['reservations'] = array();
                $last_reservation_slot = $reservation_slot;
                $last_reservation_date = $reservation_date;
            }
// add to existing slot
            $compromised_slots_array[$compromised_slot_index]['reservations'][$reservations_index]['reservation_number'] = $reservation_number;
            $compromised_slots_array[$compromised_slot_index]['reservations'][$reservations_index]['reserver_id'] = $reserver_id;
            $compromised_slots_array[$compromised_slot_index]['reservations'][$reservations_index]['reservation_type'] = $reservation_type;
            $compromised_slots_array[$compromised_slot_index]['reservations'][$reservations_index]['reservation_status'] = $reservation_status;
            if ($reservation_type == "email") {
                $compromised_slots_array[$compromised_slot_index]['emailtotal']++;  
            }
            $reservations_index++;
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

function date_to_display_coordinates($date) {

# Takes a date in mysql date format ("yyyy-mm-dd") and returns an array
# where the first element is the the row the date would occupy in the calendar
# display for the date month (a value ranging from 0 to 5), and the second
# element is the column it would occupy (a value ranging from 0 to 6, where
# 0 represents Sunday)

    $column = (date("N", strtotime($date))) % 7; # (0 - 6)
    $day = date("d", strtotime($date)); # (01 to 31)
    $row = floor(($day - 1) / 7);

# create an array to accomodate the returns (PHP can only return a single variable)

    $returns = array();
    array_push($returns, $row);
    array_push($returns, $column);

    return $returns;
}

function slot_date_to_string($slot, $date, $number_of_slots_per_hour) {

    // Given a slot number, a date (eg 2020-06-07) and the $number_of_slots_per_hour parameter,
    // return a "civilised" string eg (8:00am on Saturday 7'th July)

    $slot_length = 60 / $number_of_slots_per_hour;

    $slot_time = 100 * floor($slot / $number_of_slots_per_hour) + $slot_length * ($slot % $number_of_slots_per_hour);

    $ampm = "am";
    if ($slot_time == 1200)
        $ampm = " noon";
    if ($slot_time == 00)
        $ampm = " midnight";
    if ($slot_time > 1200)
        $ampm = "pm";
    if ($slot_time >= 1300) {
        $slot_time = $slot_time - 1200;
    }

    $first_bit = floor($slot_time / 100);
    $second_bit = strval($slot_time % 100);
    if ($second_bit == "0")
        $second_bit = "00";


    return "$first_bit:$second_bit$ampm on " . date('l jS F Y', strtotime($date));
}

function pr($var) {
    print '<pre>';
    print_r($var);
    print '</pre>';
}
