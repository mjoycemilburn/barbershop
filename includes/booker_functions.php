<?php

function build_availability_variables_for_month($year, $month, $min_chair_number, $max_chair_number) {

// Build $slot_availability_array(slot, day) and $bookings_taken(slot, day) arrays for the
// given month (1-31) that shows the chairs available for each slot/day combination (as an
// associative array of chair_numbers, and wheher they're booked or not. 
// 
// The data for a day/slot combination would typically be:
// 
// $slot_availability_array[slot][[day][ [2] => 'B', [4] => 'A' ]. This is saying that at this 
// particular slot time on this particular day, two stylists are on duty (after taking account of 
// bank holidays and their indivdual staff holiday and work-pattern arrangements). They are identified
// on the database as chair 2 and chair 4. Chair 2 has already been booked for this slot so is 
// "unavailable", but Chair 4 is still available to bookers.
// 
// The slot_availability array thus differs from the compromised_slots array in that the former
// presents the chairs assigned to a slot whereas the latter presents the reservations for a slot.
// The slot_availability array is also keyed (so you get the entry for a particular chair from
// [chair_number], whereas the latter is a series with reservations[0] first presenting the problem 
// reservation with the rest of the array follows in sequence. Ther may be gaps in slot_availabiity
// (eg if there's no chair 3 on the database, there will never be a [3] entry in slot_availabiity.
//  
// Also build a $day_available array[day] summary for $slot_availability_array showing each column that 
// has at least one chair available in one of its slots     

    require ('../includes/booker_globals.php');

// Initialise the arrays for 24*$number_of_slots_per_hour and 31 days (ie potential max)
// with the chair_numbers for the currently selected range of chairs and initialise the bookings
// taken for each as 0

    $slot_availability_array = array();

    $sql = "SELECT 
                chair_number
            FROM ecommerce_work_patterns
            WHERE 
                chair_number >= '$min_chair_number' AND
                chair_number <= '$max_chair_number' AND
                shop_code = '$shop_code';";

    $resulta = mysqli_query($con, $sql);

    if (!$resulta) {
        error_log("Oops - database access %failed% in booker_functions loc 1. Error details follow<br><br> " . mysqli_error($con));
        disconnect();
        exit(1);
    }

    $earliest_working_slot = 24 * $number_of_slots_per_hour;
    $latest_working_slot = 0;

    while ($rowa = mysqli_fetch_array($resulta)) {

        $chair_number = $rowa['chair_number'];

        $length_of_month = date("t", strtotime("$year-$month"));

        for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {
            for ($j = 0; $j < $length_of_month; $j++) {
                $slot_availability_array[$i][$j][$chair_number] = "A";
                // "Available" - in theory - we'may demote to "Unavailable" shortly - or,indeed, remove altogether
            }
        }

// now reduce slot-availabilities due to the work_patterns for this chair

        $sql = "SELECT 
                chair_number,
                pattern_json
            FROM ecommerce_work_patterns
            WHERE 
               chair_number = '$chair_number' AND
               shop_code = '$shop_code';";

        $resultb = mysqli_query($con, $sql);

        if (!$resultb) {
            error_log("Oops - database access %failed% in booker_functions loc 2. Error details follow<br><br> " . mysqli_error($con));
            disconnect();
            exit(1);
        }

        while ($rowb = mysqli_fetch_array($resultb)) {
            $chair_number = $rowb['chair_number'];
            $pattern_json = $rowb['pattern_json'];

// if the json is defined, use json_decode to turn it into an asociative array,
// weekday_pattern_availability otherwise create an empty array manually.
// In weekday_pattern_availability[slot][weekday],  1 means chair available
// for slot/weekday
            if ($pattern_json == '' || $pattern_json == '[]') {

                $weekday_pattern_availability = array();
                for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {
                    for ($j = 0; $j < 7; $j++) {

                        $weekday_pattern_availability[$i]['working'][$j] = 1;
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
//  - if day_pattern[i][j] = 0 (unavailable) remove the chair from slot_availability
//  - if day_pattern[i][j] = 1 (available), set the chair accordingly


            for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {
                for ($j = 0; $j < $length_of_month; $j++) {
                    if ($day_pattern_availability[$i][$j] == 0) { // note, this is the day_pattern for chair $chair_number
                        unset($slot_availability_array[$i][$j][$chair_number]);
                    }
                }
            }
        }

// while you're at it, use the $weekday_pattern_availability array to calculate
// $earliest_working_slot and  $latest_working_slot variables as an act of kindness
// to the build_calendar_day_display routine that will use them to blank out uesles
// early and late rows from the display
// Note that "$weekday_pattern_availability" refers to the current chair  

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
                bank_holiday_month = '$month' AND
                shop_code= '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        error_log("Oops - database access %failed% in booker_functions loc 3. Error details follow<br><br> " . mysqli_error($con));
        disconnect();
        exit(1);
    }

    while ($row = mysqli_fetch_array($result)) {
        $bank_holiday_day = $row['bank_holiday_day'];

        for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {
            foreach ($slot_availability_array[$i][$bank_holiday_day - 1] as $key => $value) {
                unset($slot_availability_array[$i][$bank_holiday_day - 1][$key]);
            }
        }
    }

// now demote chair entries in $slot_availability_array due to staff absence

    $sql = "SELECT
                chair_number,
                staff_holiday_day
            FROM ecommerce_staff_holidays
            WHERE
                staff_holiday_year = '$year' AND
                staff_holiday_month = '$month' AND
                chair_number >= '$min_chair_number' AND
                chair_number <= '$max_chair_number' AND
                shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        error_log("Oops - database access %failed% in booker_functions loc 4. Error details follow<br><br> " . mysqli_error($con));
        disconnect();
        exit(1);
    }

    while ($row = mysqli_fetch_array($result)) {

        $chair_number = $row['chair_number'];
        $staff_holiday_day = $row['staff_holiday_day'];

        for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {
            unset($slot_availability_array[$i][$staff_holiday_day - 1][$chair_number]);
        }
    }


// Finally, update stylist entries in $slot_availability_array to show which ones are booked
// Don't count P (postponeds) as stylists for these reservations won't be in the array for the slot.   

    $sql = "SELECT
                assigned_chair_number,
                reservation_slot,
                reservation_date
            FROM ecommerce_reservations
            WHERE
                reservation_status <> 'P' AND
                reservation_date >= '$year" . "-" . $month . "-01' AND
                reservation_date <= '$year" . "-" . $month . "-$length_of_month' AND
                assigned_chair_number >= '$min_chair_number' AND
                assigned_chair_number <= '$max_chair_number' AND
                shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        error_log("Oops - database access %failed% in booker_functions loc 5. Error details follow<br><br> " . mysqli_error($con));
        disconnect();
        exit(1);
    }

    while ($row = mysqli_fetch_array($result)) {

        $assigned_chair_number = $row['assigned_chair_number'];
        $reservation_slot = $row['reservation_slot'];
        $reservation_date = $row['reservation_date'];
        $reservation_day = date("j", strtotime($reservation_date)); // 1-31
        // if this chair still exists in $slot_availability_array for this slot/day (it may have become "compromised" by
        // changes to staff absences and working patterns since it was created - this will only be picked up by the
        // "check" function) then mark it as booked

        foreach ($slot_availability_array[$reservation_slot][$reservation_day - 1] as $key => $value) {
            if ($key == $assigned_chair_number) {
                $slot_availability_array[$reservation_slot][$reservation_day - 1][$assigned_chair_number] = "B"; //"Booked"
            }
        }
    }

// generate a day_available array[day] summary for $slot_availability_array showing each column that has at least one chair
// available in one of its slots (as long as its date isn't earlier than today's date

    $day_availabilty_array = array();
    $today_timestamp = strtotime(date("Y-m-d"));

    for ($j = 0; $j < $length_of_month; $j++) {
        $day_availability_array[$j] = 0;
        $day = $j + 1;
        $day_timestamp = strtotime(date("$year-$month-$day"));
        if ($day_timestamp >= $today_timestamp) {

            for ($i = 0; $i < 24 * $number_of_slots_per_hour; $i++) {
                foreach ($slot_availability_array[$i][$j] as $key => $value) {
                    if ($value == "A")
                        $day_availability_array[$j] = 1;
                }
            }
        }
    }
}

function build_compromised_slot_array_for_month($year, $month, $min_chair_number, $max_chair_number) {

    // build a two-dimensional array  showing reservation slots that are currently under-resourced as follows:
    // 
    // compromised_slots['date' 'year', 'month', 'day', 'slot', 'emailtotal',
    //          reservations[['reservation_number','reserver_id', 'reservation_type', 'reservation_status', 'chair_number'], .... ]]
    // 'date' is included (in mysql "date format - eg 2020-06-03) alongside its component year, month, day elements
    // (eg 2020, 6, 3), for convenience later. Likewise the 'emailtotal' field show the number of reservations of type 'email'.
    // 
    // We do this in two runs, firstly identifying the compromised slots and the  reservation that is causing the
    // problem (storing this in the first reservation element, and then, in a second run, filling out the rest of the 
    // compromised_slot_array itself (there's a danger, otherwise, that a problem reservation is only identified on a 
    // slot after you've already OK'd some of its elements).

    require ('booker_globals.php');

    $length_of_month = date("t", strtotime("$year-$month"));

    // OK, first run

    $compromised_slots_array = array();

    //ignore "P" status reservations

    $sql = "SELECT
                reservation_number,
                reservation_date,
                reservation_slot,
                reservation_status,
                reserver_id,
                reservation_type,
                assigned_chair_number,
                chair_expressly_chosen
            FROM ecommerce_reservations
            WHERE
                reservation_status <> 'P' AND
                reservation_date >= '$year" . "-" . $month . "-01' AND
                reservation_date <= '$year" . "-" . $month . "-$length_of_month' AND
                assigned_chair_number >= '$min_chair_number' AND
                assigned_chair_number <= '$max_chair_number' AND
                shop_code = '$shop_code'
            ORDER BY reservation_date, reservation_slot;";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        error_log("Oops - database access %failed% in booker_functions loc 6. Error details follow<br><br> " . mysqli_error($con));
        disconnect();
        exit(1);
    }

    $compromised_slot_index = 0;
    $last_reservation_date = '';
    $last_reservation_slot = -1;

    while ($row = mysqli_fetch_array($result)) {
        $reservation_number = $row['reservation_number'];
        $reservation_date = $row['reservation_date'];
        $reservation_slot = $row['reservation_slot'];
        $reservation_status = $row['reservation_status'];
        $reserver_id = $row['reserver_id'];
        $reservation_type = $row['reservation_type'];
        $assigned_chair_number = $row['assigned_chair_number'];
        $chair_expressly_chosen = $row['chair_expressly_chosen'];


        $reservation_day = date("j", strtotime($reservation_date));

        // This slot is compromised if there is a reservation for a chair and the chair isn't there!

        $stylists_on_duty_for_this_slot = count($slot_availability_array[$reservation_slot][$reservation_day - 1]);
        $stylists_booked_for_this_slot = 0;

        $compromised = true;

        foreach ($slot_availability_array[$reservation_slot][$reservation_day - 1] as $key => $value) {
            if ($key == $assigned_chair_number) {
                $compromised = false;
            }
        }

        if ($compromised) {

            // Take only the first compromised reservation in each date/slot combination

            if ($reservation_date != $last_reservation_date || $reservation_slot != $last_reservation_slot) {

                $last_reservation_date = $reservation_date;
                $last_reservation_slot = $reservation_slot;

// OK - we've found a reservation for an under-resourced slot. Initialise a $compromised_slots_array entry

                $compromised_slots_array[$compromised_slot_index]['date'] = $reservation_date;
                $compromised_slots_array[$compromised_slot_index]['year'] = date("Y", strtotime($reservation_date));
                $compromised_slots_array[$compromised_slot_index]['month'] = date("n", strtotime($reservation_date)); // (1-12)
                $compromised_slots_array[$compromised_slot_index]['day'] = date("j", strtotime($reservation_date)); // (1-31)
                $compromised_slots_array[$compromised_slot_index]['emailtotal'] = 0;
                $compromised_slots_array[$compromised_slot_index]['slot'] = $reservation_slot;
                $compromised_slots_array[$compromised_slot_index]['reservations'] = array();

// Now add details of the problem reservation as reservations{0]

                $compromised_slots_array[$compromised_slot_index]['reservations'][0]['reservation_number'] = $reservation_number;
                $compromised_slots_array[$compromised_slot_index]['reservations'][0]['reserver_id'] = $reserver_id;
                $compromised_slots_array[$compromised_slot_index]['reservations'][0]['reservation_type'] = $reservation_type;
                $compromised_slots_array[$compromised_slot_index]['reservations'][0]['reservation_status'] = $reservation_status;
                $compromised_slots_array[$compromised_slot_index]['reservations'][0]['assigned_chair_number'] = $assigned_chair_number;
                $compromised_slots_array[$compromised_slot_index]['reservations'][0]['chair_expressly_chosen'] = $chair_expressly_chosen;

                if ($reservation_type == "email") {
                    $compromised_slots_array[$compromised_slot_index]['emailtotal'] = 1;
                }
                $compromised_slot_index++;
            }
        }
    }

    // OK, that's the first run finished, now go through again for each identified problem booking and add the details
    // of other reservations for this slot

    for ($i = 0; $i < count($compromised_slots_array); $i++) {

        $reservation_date = $compromised_slots_array[$i]['date'];
        $reservation_slot = $compromised_slots_array[$i]['slot'];

        $problem_reservation_number = $compromised_slots_array[$i]['reservations'][0]['reservation_number'];

        $sql = "SELECT
                reservation_number,
                reservation_date,
                reservation_slot,
                reservation_status,
                reserver_id,
                reservation_type,
                assigned_chair_number
            FROM ecommerce_reservations
            WHERE
                reservation_date = '$reservation_date' AND
                reservation_slot = '$reservation_slot' AND
                reservation_number   <> '$problem_reservation_number' AND  
                assigned_chair_number >= '$min_chair_number' AND
                assigned_chair_number <= '$max_chair_number' AND
                shop_code = '$shop_code'
            ORDER BY reservation_date, reservation_slot;";

        $result = mysqli_query($con, $sql);

        if (!$result) {
            error_log("Oops - database access %failed% in booker_functions loc 7. Error details follow<br><br> " . mysqli_error($con));
            disconnect();
            exit(1);
        }

        $reservations_index = 1;

        while ($row = mysqli_fetch_array($result)) {
            $reservation_number = $row['reservation_number'];
            $reservation_date = $row['reservation_date'];
            $reservation_slot = $row['reservation_slot'];
            $reservation_status = $row['reservation_status'];
            $reserver_id = $row['reserver_id'];
            $reservation_type = $row['reservation_type'];
            $assigned_chair_number = $row['assigned_chair_number'];

            $compromised_slots_array[$i]['reservations'][$reservations_index]['reservation_number'] = $reservation_number;
            $compromised_slots_array[$i]['reservations'][$reservations_index]['reserver_id'] = $reserver_id;
            $compromised_slots_array[$i]['reservations'][$reservations_index]['reservation_type'] = $reservation_type;
            $compromised_slots_array[$i]['reservations'][$reservations_index]['reservation_status'] = $reservation_status;
            $compromised_slots_array[$i]['reservations'][$reservations_index]['chair_number'] = $assigned_chair_number;

            if ($reservation_type == "email") {
                $compromised_slots_array[$i]['emailtotal'] ++;
            }

            $reservations_index++;
        }
    }
}

function assign_chair($reservation_slot, $day, $requested_chair_number, $min_chair_number, $max_chair_number) {

    // if $min_chair_number =  $max_chair_number, returns $chair_expressly_chosen = "Y" in return[0],
    // otherwise "N"
    // 
    // If $chair_expressly_chosen == "Y", checks the "requested_chair is still available and returns
    // $requested_chair in return[1], otherwise errors
    // 
    // If $chair_expressly_chosen == "N", checks to see that there is still a stylist available and
    // returns a chair number at random in return[1],, otherwise errors

    require ('../includes/booker_globals.php');

    $chair_expressly_chosen = "N";
    if ($min_chair_number == $max_chair_number) {
        $chair_expressly_chosen = "Y";
        $requested_chair_number = $min_chair_number;
        $assigned_chair_number = $requested_chair_number;
    }

    $slot_still_free = false;

    //pr($slot_availability_array[36][12]);
    //pr($chair_expressly_chosen);  
    //pr($requested_chair_number);
    //pr($day);

    if ($chair_expressly_chosen == "Y") {

        if ($slot_availability_array[$reservation_slot][$day - 1][$requested_chair_number] == "A") {
            $slot_still_free = true;
        }
    } else {

        // build an array of free chairs for this slot (if there are any!)
        $free_chairs = array();
        $j = 0;
        foreach ($slot_availability_array[$reservation_slot][$day - 1] as $key => $value) {
            if ($value == "A") {
                $free_chairs[$j] = $key;
                $j++;
            }
        }

        if (count($free_chairs) != 0) {
            $slot_still_free = true;
            // choose a chair_number at random
            $assigned_chair_number = $free_chairs[mt_rand(0, count($free_chairs) - 1)];
        }
    }

    if (!$slot_still_free) {
        echo "Sorry - this slot has just been booked by another customer - please choose a different slot";
        disconnect();
        exit(1);
    }

    $return_array[0] = $chair_expressly_chosen;
    $return_array[1] = $assigned_chair_number;

    return $return_array;
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

function connect() {

    # Connect to the database

    require ('booker_globals.php');
    require ('hidden_connect_code'); //**CONFIG REQUIRED** - the hidden db connect code at the root of your server  - eg /home/pqrtyu/connect_ecommerce_prototype.php
}

function disconnect() {

    # Disconnect from the database

    require ('booker_globals.php');
    require ('hidden_disconnect_code'); //**CONFIG REQUIRED** 
}

function pr($var) {
    print '<pre>';
    print_r($var);
    print '</pre>';
}
