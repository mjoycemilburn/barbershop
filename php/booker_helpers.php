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
# 'get_shop_parameters'              -  return the shop_name and number of slots per hour parameter for a given shop_url
#
# 'build_service_selection_picklists' - build the service-selection picklist for the current shop
#  
# 'build_paypal_confirmation_button' - build a paypal for the slsected Service for the current shop
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
# 'build_slot_reservations_display' -   create a display popup for the reservations for the specified slot                                 
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
# 'toggle_staff_holiday'            -   toggle a staff_holiday record  in and out of existence for supplied date and chair_number                                                                                                                                                      
#  
# 'build_work_pattern_table_for_week_display'   -   create display table for supplied chair_number showing hours
#                                                   worked in a standard week. If there is no work_pattern for
#                                                   the chair number, it creates one
#
# 'save_pattern'                    -   Update the pattern json and chair_owner name for the supplied chair_number
#
# 'prepare_to_delete_chair'         -   build a "confirm you want to delete chair" message for given chair number
# 
# 'delete_chair'                    -   delete chair number
# 
# 'insert_chair'                    -   insert chair number
#
# 'build_check_display'             - Return a list of slots (in the upcoming three months) that are
#                                     unresourced give current staffing, work-patterns and holidays
#
# 'issue_reservation_apologies'    - issue reservation apologies for given range of slots - set the first email booking in
#                                     each of the affected slots to "postponed" and despatch an email conataining a link
#                                     inviting rebooking
#
# 'login_with_user_credentials'  -  Attempt to login with supplied user_id and password
#
# 'login_with_trusted_user_code'    -  Attempt to login with supplied encrypted_keys
#   
# 'validate_trusted_user_code'      -  Check validity of trusted_user_code for supplied shop_code
#   

$page_title = 'booker_helpers';
$source_root = 'website_root_domain/'; //**CONFIG REQUIRED** - set this to your website root domain - eg https://mywebsite.com

# set headers to allow code-sharing between stylist websites

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Depth, User-Agent, X-File-Size, X-Requested-With, If-Modified-Since, X-File-Name, Cache-Control");
header("Access-Control-Max-Age: 18000");

# set headers to NOT cache the page
header("Cache-Control: no-cache, must-revalidate"); //HTTP 1.1
header("Pragma: no-cache"); //HTTP 1.0
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

date_default_timezone_set('Europe/London');

require ('../includes/booker_functions.php');
require ('../includes/send_email_via_postmark.php');

# Connect to the database

connect();

$helper_type = $_POST['helper_type'];
$shop_url = $_POST['shop_url'];

// Turn the url into a shop_code. If running locally, set shop_code = 1

if ($_SERVER['REMOTE_ADDR'] == '127.0.0.1' or $_SERVER['REMOTE_ADDR'] == '::1') {
    $shop_url = "localhost";
}

$sql = "SELECT 
            shop_code,
            shop_name,
            paypal_business_address,
            number_of_slots_per_hour,
            default_service_code
        FROM ecommerce_barbershops
        WHERE shop_url = '$shop_url';";

$result = mysqli_query($con, $sql);

if (!$result) {
    echo "Oops - database access %failed%. $page_title Loc 1. Error details follow<br><br> " . mysqli_error($con);
    disconnect();
    exit(1);
}

$shop_code = '';
$shop_name = '';
$paypal_business_address = '';
$number_of_slots_per_hour = 0;
$default_service_code = 0;

while ($row = mysqli_fetch_array($result, MYSQLI_BOTH)) {
    $shop_code = $row['shop_code'];
    $shop_name = $row['shop_name'];
    $paypal_business_address = $row['paypal_business_address'];
    $number_of_slots_per_hour = $row['number_of_slots_per_hour'];
    $default_service_code = $row['default_service_code'];
}

// Useful parameters

$month_name_array = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

#####################  'get_shop_parameters' ####################

if ($helper_type == 'get_shop_parameters') {

    if ($shop_name == "") {
        echo "Oops - database access %failed%. $page_title Loc 2. Shop_url not found " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    $return1 = prepareStringforXMLandJSONParse($shop_code);
    $return2 = prepareStringforXMLandJSONParse($shop_name);
    $return3 = prepareStringforXMLandJSONParse($number_of_slots_per_hour);
    $return4 = prepareStringforXMLandJSONParse($default_service_code);

    $json_string = '<returns>{"return1": "' . $return1 . '", "return2": "' . $return2 . '", "return3": "' . $return3 . '", "return4": "' . $return4 . '"}</returns>';

    header("Content-type: text/xml");
    echo "<?xml version='1.0' encoding='UTF-8'?>";
    echo $json_string;
}

#####################  'build_service_selection_picklists' ####################

if ($helper_type == 'build_service_selection_picklists') {

    $selected_service_code = $_POST['selected_service_code'];

    $sql = "SELECT 
                service_code,
                service_description,
                service_price,
                service_class
            FROM ecommerce_shop_services
            WHERE shop_code = '$shop_code'
            ORDER BY shop_code;";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 3. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    $return1 = "<select id='emailservicepicklist' onchange = 'handleServiceChangeOnEmail();'>";
    $return2 = "<select id='telephoneservicepicklist' onchange = 'handleServiceChangeOnTelephone();'>";

    while ($row = mysqli_fetch_array($result, MYSQLI_BOTH)) {

        $service_code = $row['service_code'];
        $service_description = $row['service_description'];
        $service_price = $row['service_price'];
        $service_class = $row['service_class'];

        if ($service_code == $selected_service_code) {
            $return1 .= "<option selected value = '$service_code'>$service_description : £$service_price</option>";
            $return2 .= "<option selected value = '$service_code'>$service_description : £$service_price</option>";
        } else {
            if ($service_class == "standard") {
                $return1 .= "<option value = '$service_code'>$service_description : £$service_price</option>";
                $return2 .= "<option value = '$service_code'>$service_description : £$service_price</option>";
            } else {
                $return2 .= "<option value = '$service_code'>$service_description : £$service_price</option>";
            }
        }
    }

    $return1 .= "</select>";
    $return2 .= "</select>";

    $return1 = prepareStringforXMLandJSONParse($return1);
    $return2 = prepareStringforXMLandJSONParse($return2);

    $json_string = '<returns>{"return1": "' . $return1 . '", "return2": "' . $return2 . '"}</returns>';

    header("Content-type: text/xml");
    echo "<?xml version='1.0' encoding='UTF-8'?>";
    echo $json_string;
}

#####################  'build_paypal_confirmation_button' ####################

if ($helper_type == 'build_paypal_confirmation_button') {

    $selected_service_code = $_POST['selected_service_code'];

    // get the description and price for the selected service

    $sql = "SELECT 
                service_description,
                service_price
            FROM ecommerce_shop_services
            WHERE 
                shop_code = '$shop_code' AND
                service_code = '$selected_service_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 4. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    $row = mysqli_fetch_array($result, MYSQLI_BOTH);
    $service_description = $row['service_description'];
    $service_price = $row['service_price'];

    $return = "
    <strong>$service_description<br> £$service_price<br></strong>";

    if ($shop_code == 1) {
        $return .= "        
    <form action = 'https://www.sandbox.paypal.com/cgi-bin/webscr' method = 'post' target = '_top'> 
        <input type = 'hidden' name = 'business' value = '$paypal_business_address'> 
        <input type = 'hidden' name = 'notify_url' value = '" . $source_root . "booker_shared_code/listener_sandbox.php'>";
    } else {
        $return .= "   
    <form action = 'https://www.paypal.com/cgi-bin/webscr' method = 'post' target = '_top'> 
        <input type = 'hidden' name = 'business' value = '$paypal_business_address'> 
        <input type = 'hidden' name = 'notify_url' value = '" . $source_root . "booker_shared_code/listener.php'>";
    }

    $return .= " 
        <input type = 'hidden' name = 'cmd' value = '_xclick'>
        <input type = 'hidden' name = 'item_name' value = '$service_description'>
        <input type = 'hidden' name = 'amount' value = '$service_price'>
        <input type = 'hidden' name = 'currency_code' value = 'GBP'>
        <input id = 'papypalbookeremail' type = 'hidden' NAME='email'>
        <input id = 'paypalcustomvariable' type = 'hidden' name = 'custom'>
        <input id = 'paypalreturn' type = 'hidden' name = 'return' value = 'https://$shop_url/index.php?mode=paypalreturn'> 
        <input type = 'image' src='https://www.paypalobjects.com/webstatic/en_US/i/buttons/pp-acceptance-large.png' alt = 'PayPal Acceptance' border = '0' name = 'submit'>
    </form>";

    echo $return;
}

#####################  'build_calendar_month_display' ####################

if ($helper_type == 'build_calendar_month_display') {

    $year = $_POST['year'];
    $month = $_POST['month'];
    $mode = $_POST['mode'];
    $min_chair_number = $_POST['min_chair_number'];
    $max_chair_number = $_POST['max_chair_number'];

    // If this is the current month, begin by building a "pick-list" to allow 
    // the customer to choose a stylist

    $return = '';

    $current_month = date("n");
    if ($month == $current_month) {

        $sql = "SELECT 
                chair_number,
                chair_owner
            FROM ecommerce_work_patterns
            WHERE shop_code = '$shop_code';";

        $result = mysqli_query($con, $sql);

        if (!$result) {
            echo "Oops - database access %failed%. $page_title Loc 5. Error details follow<br><br> " . mysqli_error($con);
            disconnect();
            exit(1);
        }

        if ($mode == "viewer") {
            $return = "<form style = 'margin-bottom: 1em;'>
                <label for='stylistmonthpicklist' name='stylistmonthpicklist'>Selected Stylist : </label>
                <select id='stylistmonthpicklist' onchange = 'handleStylistChangeOnMonthDisplay($year, $month);'>";
            if ($min_chair_number != $max_chair_number) {
                $return .= "<option selected value = '0'>All</option>";
            } else {
                $return .= "<option value = '0'>All</option>";
            }
        } else {
            $return = "<form style = 'margin-bottom: 1em;'>
                <label for='stylistmonthpicklist' name='stylistmonthpicklist'>Preferred Stylist : </label>
                <select id='stylistmonthpicklist' onchange = 'handleStylistChangeOnMonthDisplay($year, $month);'>";
            if ($min_chair_number != $max_chair_number) {
                $return .= "<option selected value = '0'>Any</option>";
            } else {
                $return .= "<option value = '0'>Any</option>";
            }
        }

        while ($row = mysqli_fetch_array($result, MYSQLI_BOTH)) {

            $chair_number = $row['chair_number'];
            $chair_owner = $row['chair_owner'];

            if (($min_chair_number == $max_chair_number) && ($chair_number == $min_chair_number)) {
                $return .= "<option selected value = '$chair_number'>$chair_owner</option>";
            } else {
                $return .= "<option value = '$chair_number'>$chair_owner</option>";
            }
        }

        $return .= "</select></form>";
    }

    $slot_length = 60 / $number_of_slots_per_hour;

    $length_of_month = date("t", strtotime("$year-$month"));

    $return .= '<p>Availability for <strong>' . $month_name_array[$month - 1] . '</strong> ' . $year . '</p>
                    <p><span style="font-size: 1.25em; color: aquamarine; background: aquamarine;">&#9744</span> = days with free slots</p>';

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

    build_availability_variables_for_month($year, $month, $min_chair_number, $max_chair_number);

// unless the mode is "viewer" (when we may want to see historic bookings), only show rows 
// referencing current dates for the current month - otherwise show the whole month

    $today_day = date("j");
    $today_month = date("n");
    if ($today_month != $month || $mode == "viewer")
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

        if ($day_availability_array[$i - 1] == 1 || $mode == "viewer") {
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
    $min_chair_number = $_POST['min_chair_number'];
    $max_chair_number = $_POST['max_chair_number'];

    // Begin by building a "pick-list" to allow the customer to choose a stylist

    $sql = "SELECT 
                chair_number,
                chair_owner
            FROM ecommerce_work_patterns
            WHERE shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 6. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    if ($mode == "viewer") {
        $return = "<form style='margin-bottom: 1em;'>
                <label for='stylistdaypicklist' name='stylistdaypicklist'>Selected Stylist : </label>
                <select id='stylistdaypicklist' onchange = 'handleStylistChangeOnDayDisplay($year, $month, $day);'>";

        if ($min_chair_number != $max_chair_number) {
            $return .= "<option selected value = '0'>All</option>";
        } else {
            $return .= "<option value = '0'>All</option>";
        }
    } else {
        $return = "<form style='margin-bottom: 1em;'>
                <label for='stylistdaypicklist' name='stylistdaypicklist'>Preferred Stylist : </label>
                <select id='stylistdaypicklist' onchange = 'handleStylistChangeOnDayDisplay($year, $month, $day);'>";

        if ($min_chair_number != $max_chair_number) {
            $return .= "<option selected value = '0'>Any</option>";
        } else {
            $return .= "<option value = '0'>Any</option>";
        }
    }

    while ($row = mysqli_fetch_array($result, MYSQLI_BOTH)) {

        $chair_number = $row['chair_number'];
        $chair_owner = $row['chair_owner'];

        if (($min_chair_number == $max_chair_number) && ($chair_number == $min_chair_number)) {
            $return .= "<option selected value = '$chair_number'>$chair_owner</option>";
        } else {
            $return .= "<option value = '$chair_number'>$chair_owner</option>";
        }
    }

    $return .= "</select></form>";

    $slot_length = 60 / $number_of_slots_per_hour;

    $reservation_date = $year . "-" . $month . "-" . $day;

# build 'availability' array for the slots for this date from the ecommerce_reservations,
# day_override and slot_override tables

    build_availability_variables_for_month($year, $month, $min_chair_number, $max_chair_number);

    $date_string = $year . "-" . $month . "-" . $day;
    $date = strtotime($date_string);

    $requested_date = date("Y-m-d", $date);
    $now_date = date("Y-m-d");
    $now_hour = date("G"); // (0-23)
    $now_minutes = date("i"); // (00-59);
    $next_available_slot = $now_hour * $number_of_slots_per_hour + floor($now_minutes / (60 / $number_of_slots_per_hour)) + 1;

    $datetime = new DateTime($date_string); // see https://stackoverflow.com/questions/5883571/get-next-and-previous-day-with-php
    $interval = new DateInterval('P1D');
    $datetime->sub($interval);
    $pre_day = $datetime->format("j");
    $pre_month = $datetime->format("n");
    $pre_year = $datetime->format("Y");
    $datetime->add($interval);
    $datetime->add($interval);
    $post_day = $datetime->format("j");
    $post_month = $datetime->format("n");
    $post_year = $datetime->format("Y");

    $return .= '<p><button onclick = "displayDay(' . $pre_year . ',' . $pre_month . ',' . $pre_day . ')">
                        <span class="oi oi-caret-left"></span>
                    </button>&nbsp;
                    Avblty : ' . date("D", $date) . " " . date("j", $date) . date("S", $date) . " " . date("M", $date) . " " . $year . '&nbsp;
                    <button onclick = "displayDay(' . $post_year . ',' . $post_month . ',' . $post_day . ')">
                        <span class="oi oi-caret-right"></span>
                    </button
                </p> 
                <p><span style="font-size: 1.25em; color: aquamarine; background: aquamarine;">&#9744;</span> - avble
                       <span style="font-size: 1.25em; color: palegreen; background: palegreen;">&#9744;</span> - limited
                       <span style="font-size: 1.25em; color: red; background: red;">&#9744;</span> - unavble
                    </p>';

    $return .= '
    <table class="table" style="border-collapse: separate;
        border-spacing: .5vw;
        ">
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

        $return .= '<tr><td style="background: gainsboro;
        ">' . $i . '.00</td>';
        for ($j = 0; $j < $number_of_slots_per_hour; $j++) {

            $reservation_slot = $i * $number_of_slots_per_hour + $j;
// block out expired slots
            if ($requested_date == $now_date && $reservation_slot < $next_available_slot) {
                // but if mode is "viewer" still make them clickable
                if ($mode == "viewer") {
                    $return .= '<td onclick = "viewSlot(' . $year . ', ' . $month . ', ' . $day . ', ' . $reservation_slot . ');"></td>';
                } else {
                    $return .= '<td></td>';
                }
            } else {

                $staffed = count($slot_availability_array[$reservation_slot][$day - 1]);  // this is the number of chairs that are staffed for this slot
                $takeup = 0; // this is the number of chairs that are already booked for this slot
                foreach ($slot_availability_array[$reservation_slot][$day - 1] as $key => $value) {

                    if ($value == "B") {
                        $takeup ++;
                    }
                }
                $headroom = $staffed - $takeup;

                if ($headroom <= 0) {
                    $background = "red";
                } else {
                    if ($staffed == 1) {
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
                    $onclick = 'onclick = "viewSlot(' . $year . ', ' . $month . ', ' . $day . ', ' . $reservation_slot . ');"';
                } else {
                    if ($headroom == 0) {
                        $onclick = "";
                    } else {
                        $onclick = 'onclick = "bookSlot(' . $year . ', ' . $month . ', ' . $day . ', ' . $reservation_slot . ');"';
                    }
                }
                $return .= '<td style = "background: ' . $background . ';
        " ' . $onclick . '</td>';
            }
        }
        $return .= '</tr>';
    }

    $return .= '
            </tbody>
        </table>
        <button id = "dayviewcancelbutton" style="margin-bottom: 1em;" 
            onclick="dayViewRange = \'historic\'; displayMonth(' . $year . ', ' . $month . ');">Cancel</button>';
    // The dayViewRange setting above resets the toggle so that the monthView starts with current month
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

    $min_chair_number = $_POST['min_chair_number'];
    $max_chair_number = $_POST['max_chair_number'];

    $selected_service_code = $_POST['selected_service_code'];

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
        echo "Oops - database access %failed%. $page_title Loc 7. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    build_availability_variables_for_month($year, $month, $min_chair_number, $max_chair_number);

// check the continued availability for this slot  

    if ($min_chair_number == $max_chair_number) {
        $requested_chair_number = $min_chair_number;
    }

    $results_array = assign_chair($reservation_slot, $day, $requested_chair_number, $min_chair_number, $max_chair_number);
    $chair_expressly_chosen = $results_array[0];
    $assigned_chair_number = $results_array[1];

// OK - if there was a problem, would already have errored back to booker.html. Now clear to create a new reservation

    $sql = "INSERT INTO ecommerce_reservations (
                reservation_date,
                shop_code,
                reservation_slot,
                reservation_status,
                reserver_id,
                reservation_type,
                assigned_chair_number,
                chair_expressly_chosen,
                service_code,
                reservation_time_stamp)
            VALUES (
                '$reservation_date', 
                '$shop_code',
                '$reservation_slot',";

    if ($mode == "email") {
        $sql .= "'U',";
    } else {
        $sql .= "'C',";
    }

    $sql .= "'$reserver_id',
                '$mode',
                '$assigned_chair_number',
                '$chair_expressly_chosen', 
                '$selected_service_code',
                '$reservation_time_stamp');";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 8. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

// get the reservation_number that's just been allocated

    $reservation_number = mysqli_insert_id($con);

// confirmation of booking for email bookers will be sent by process_paypal_consequentials

    $sql = "COMMIT;";

    $result = mysqli_query($con, $sql);
    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 9. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
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
    $min_chair_number = $_POST['min_chair_number'];
    $max_chair_number = $_POST['max_chair_number'];

    $slot_length = 60 / $number_of_slots_per_hour;

    $reservation_date = $year . "-" . $month . "-" . $day;
    $reservation_time_stamp = date('Y-m-d H:i:s');

    build_availability_variables_for_month($year, $month, $min_chair_number, $max_chair_number);

// OK - first check the status of the outgoing reservation:
// 
// for "change" mode, the type needs to be email and the status needs to be "P" (postponed)
// for "rebook" mode, the type can be anything and the status need to be "C"  

    $sql = "SELECT
                reservation_status,
                reserver_id,
                reservation_type,
                assigned_chair_number,
                chair_expressly_chosen,
                service_code,
                amount_paid
            FROM ecommerce_reservations
            WHERE
                reservation_number = '$outgoing_reservation_number' AND
                shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 10. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    $row = mysqli_fetch_array($result);

    $reservation_status = $row['reservation_status'];
    $reserver_id = $row['reserver_id'];
    $reservation_type = $row['reservation_type'];
    $assigned_chair_number = $row['assigned_chair_number'];
    $chair_expressly_chosen = $row['chair_expressly_chosen'];
    $service_code = $row['service_code'];
    $amount_paid = $row['amount_paid'];

    if (($mode == "change" && $reservation_status == "P") ||
            ($mode == "rebook" && $reservation_status == "C")) {
        
    } else {
        echo "Oops - reservation status %failed%. $page_title Loc 11. mode = $mode, status = $reservation_status";
        disconnect();
        exit(1);
    }

// Now find a chair for this customer - if there still is one!

    $results_array = assign_chair($reservation_slot, $day, $requested_chair_number, $min_chair_number, $max_chair_number);
    $chair_expressly_chosen = $results_array[0];
    $assigned_chair_number = $results_array[1];

// OK, if there was a problem, we'd have errored back to booker.html by now, so start a transaction to
// delete the outgoing reservation and create the new one

    $sql = "START TRANSACTION;";

    $result = mysqli_query($con, $sql);
    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 12. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    $sql = "DELETE FROM ecommerce_reservations
            WHERE
                reservation_number = '$outgoing_reservation_number' AND
                shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 13. Error details follow<br><br> " . mysqli_error($con);
        $sql = "ROLLBACK;";
        $result = mysqli_query($con, $sql);
        disconnect();
        exit(1);
    }

    $sql = "INSERT INTO ecommerce_reservations (
                shop_code,
                reservation_date,
                reservation_slot,
                reservation_status,
                reserver_id,
                reservation_type,
                assigned_chair_number,
                chair_expressly_chosen,
                service_code,
                amount_paid,
                reservation_time_stamp)
            VALUES (
                '$shop_code',
                '$reservation_date', 
                '$reservation_slot',
                'C',
                '$reserver_id',
                '$reservation_type',
                '$assigned_chair_number',
                '$chair_expressly_chosen',
                '$service_code',  
                '$amount_paid',
                '$reservation_time_stamp')";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 14. Error details follow<br><br> " . mysqli_error($con);
        $sql = "ROLLBACK;";
        $result = mysqli_query($con, $sql);
        disconnect();
        exit(1);
    }

// get the reservation_number that's just been allocated

    $reservation_number = mysqli_insert_id($con);

// finally send a confirmation email for the new slot

    $appointment_string = slot_date_to_string($reservation_slot, $reservation_date, $number_of_slots_per_hour);

    $mailing_address = $reserver_id;
    $mailing_title = "Your re-booked reservation at " . $shop_name;
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
        disconnect();
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
    build_availability_variables_for_month($year, $month, 1, 10000);

    // pr($slot_availability_array[$reservation_slot][$day - 1]);

    $slot_length = 60 / $number_of_slots_per_hour;

    $reservation_date = $year . "-" . $month . "-" . $day;

# build html code to display the details of all reservation for the selected slot Start
# by building tables of chair-owner names and service descriptions/prices

    $sql = "SELECT
                chair_number,
                chair_owner
            FROM ecommerce_work_patterns
            WHERE shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 16. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    $chair_owners = array();

    while ($row = mysqli_fetch_array($result)) {

        $chair_number = $row['chair_number'];
        $chair_owner = $row['chair_owner'];
        $chair_owners["'$chair_number'"] = $chair_owner;
    }

    $sql = "SELECT
                shop_code,
                service_code,
                service_description,
                service_price
            FROM ecommerce_shop_services
            WHERE shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 16a. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    $services = array();

    while ($row = mysqli_fetch_array($result)) {

        $service_code = $row['service_code'];
        $services[$service_code]['description'] = $row['service_description'];
        $services[$service_code]['price'] = $row['service_price'];
    }

    $sql = "SELECT
                reservation_number,
                reservation_status,
                reserver_id,
                reservation_type,
                assigned_chair_number,
                service_code,
                reservation_time_stamp
            FROM ecommerce_reservations
            WHERE
                reservation_date = '$reservation_date' AND
                reservation_slot = '$reservation_slot' AND
                shop_code = '$shop_code'
            ORDER BY 
                assigned_chair_number ASC;";

    $result = mysqli_query($con, $sql);

    if ($result) {

        if (($reservation_slot % $number_of_slots_per_hour) == 0) {
            $reservation_time = round($reservation_slot / $number_of_slots_per_hour) . ":00";
        } else {
            $reservation_time = floor($reservation_slot / $number_of_slots_per_hour) . ":" . ($reservation_slot % $number_of_slots_per_hour) * $slot_length;
        }

        $return = "<p style='padding-top: 2em;'>Reservations for $reservation_time on $year-$month-$day</p>";
        $count = 0;

        while ($row = mysqli_fetch_array($result)) {

            $count++;
            $reservation_number = $row['reservation_number'];
            $reservation_status = $row['reservation_status'];
            $reserver_id = $row['reserver_id'];
            $reservation_type = $row['reservation_type'];
            $assigned_chair_number = $row['assigned_chair_number'];
            $service_code = $row['service_code'];
            $reservation_time_stamp = $row['reservation_time_stamp'];

            $status = "(prepaid)";
            if ($reservation_type == "telephone")
                $status = "(due)";

            $booking_time_object = strtotime($reservation_time_stamp);
            $booking_time = date('Y-m-d at H:i:s', $booking_time_object);

            $chair_owner = $chair_owners["'$assigned_chair_number'"];

            $return .= "
                        <strong>Chair $assigned_chair_number : $chair_owner</strong>
                        <p>Customer Id : $reserver_id</p>
                        <p>Service : " . $services[$service_code]['description'] . " £" . $services[$service_code]['price'] . " $status</p>
                        <p>Reservation Reference : $reservation_number</p>
                        <p>    
                        <button class='btn btn-primary' onclick = 'rebookReservation(" . $reservation_number . ");'>Re-book Res</button>&nbsp;&nbsp;&nbsp;
                        <button class='btn btn-danger' onclick = 'cancelReservation(" . $reservation_number . ");'>Cancel Res</button>
                        </p>";
        }
    } else {
        echo "Oops - database access %failed%. $page_title Loc 17. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
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
                reservation_number = '$reservation_number' AND
                shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 18. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
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

    // get details of the reservation

    $sql = "SELECT
                reservation_date,
                reservation_slot,
                reservation_status,
                reserver_id,
                reservation_type
            FROM ecommerce_reservations
            WHERE
                reservation_number = '$reservation_number' AND
                shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {

        echo "Oops - database access %failed%. $page_title Loc 19. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
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
                reservation_number = '$reservation_number' AND
                shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 20. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    if ($reservation_type == "email") {

        $appointment_string = slot_date_to_string($reservation_slot, $reservation_date, $number_of_slots_per_hour);

        $mailing_address = $reserver_id;
        $mailing_title = "Your cancelled reservation at " . $shop_name;
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
                reservation_status = 'U' AND
                shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 21. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
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
    $return2 = '<p><span style="font-size: 1.25em; color: red; background: red;">&#9744</span> = Bank Holidays</p>';

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
                bank_holiday_month = '$month' AND
                shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo("Oops - database access %failed%. $page_title Loc 22. Error details follow<br><br> " . mysqli_error($con));
        disconnect();
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
        <button id = "advancemonth" style="margin-bottom: 1em;" onclick = "advanceMonthDisplay();"><span class="oi oi-caret-bottom"></span></button>';

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
                bank_holiday_day = '$bank_holiday_day' AND
                shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo("Oops - database access %failed%. $page_title Loc 23. Error details follow<br><br> " . mysqli_error($con));
        disconnect();
        exit(1);
    }

    $row = mysqli_fetch_array($result);

    if ($row['COUNT(*)'] == 1) {

        $sql = "DELETE FROM ecommerce_bank_holidays
                    WHERE
                bank_holiday_year = '$bank_holiday_year' AND
                bank_holiday_month = '$bank_holiday_month'AND
                bank_holiday_day = '$bank_holiday_day' AND
                shop_code = '$shop_code';";

        $result = mysqli_query($con, $sql);

        if (!$result) {
            echo("Oops - database access %failed%. $page_title Loc 24. Error details follow<br><br> " . mysqli_error($con));
            disconnect();
            exit(1);
        }
    } else {

        $sql = "INSERT INTO ecommerce_bank_holidays (
                shop_code,
                bank_holiday_year,
                bank_holiday_month,
                bank_holiday_day)
            VALUES (
                '$shop_code',
                '$bank_holiday_year',
                '$bank_holiday_month',
                '$bank_holiday_day');";

        $result = mysqli_query($con, $sql);

        if (!$result) {
            echo("Oops - database access %failed%. $page_title Loc 25. Error details follow<br><br> " . mysqli_error($con));
            disconnect();
            exit(1);
        }
    }
}

#####################  'build_staff_holiday_table_for_month_display' ####################

if ($helper_type == 'build_staff_holiday_table_for_month_display') {

    $year = $_POST['year'];
    $month = $_POST['month'];
    $chair_number = $_POST['chair_number'];

// Build an array of chair numbers (can't guarantee they're sequential) to permit
// close control of "get previous" and "get next" buttons and pick up the
// chair_owner for the current chair_number while you're at it. If no chair number 
// is specified, use the first you find and include it in the returns

    $sql = "SELECT 
                chair_owner,
                chair_number
            FROM ecommerce_work_patterns
            WHERE shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo("Oops - database access %failed%. $page_title Loc 26. Error details follow<br><br> " . mysqli_error($con));
        disconnect();
        exit(1);
    }

    $chairs_index = 0;

    $chairs_array = array(); //this is arranged as a 2d array chairs[['chair_number' : nn, 'chair_name' : aaaa]]

    while ($row = mysqli_fetch_array($result)) {

        $chairs_array[$chairs_index]['chair_number'] = $row['chair_number'];
        $chairs_array[$chairs_index]['chair_owner'] = $row['chair_owner'];

        if ($row['chair_number'] == $chair_number || $chair_number == 0) {
            $chair_number = $row['chair_number'];
            $chair_owner = $row['chair_owner'];
            $chair_owner_index = $chairs_index;
        }

        $chairs_index++;
    }

// use $chair_owners array to define preceding and next_chair indices

    $preceding_chair_index = $chair_owner_index - 1;
    $next_chair_index = $chair_owner_index + 1;
    if ($preceding_chair_index < 0)
        $preceding_chair_index = 0;
    if ($next_chair_index > count($chairs_array) - 1)
        $next_chair_index = count($chairs_array) - 1;
    $preceding_chair_number = $chairs_array[$preceding_chair_index]['chair_number'];
    $next_chair_number = $chairs_array[$next_chair_index]['chair_number'];

    $length_of_month = date("t", strtotime("$year-$month"));

    $return1 = '<p>Staff Absence settings for ' . $month_name_array[$month - 1] . ' ' . $year . '.</p> 
                <button onclick = "buildStaffHolidayDisplay(' . $year . ',' . $month . ',' . $preceding_chair_number . ');"><span class="oi oi-caret-left"></span></button>&nbsp;
                <span id = "staffholidaychairnum">' . $chair_number . '</span>&nbsp;
                <button onclick = "buildStaffHolidayDisplay(' . $year . ',' . $month . ',' . $next_chair_number . ');"><span class="oi oi-caret-right"></span></button> : 
                <span>' . $chair_owner . '</span>
                <p style = "margin-top: .5em;">Click to set/unset Holidays</p>';

    $return2 = '<p><span style="font-size: 1.25em; color: red; background: red;">&#9744</span> = Staff Absences</p>';

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
                chair_number = '$chair_number' AND
                shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo("Oops - database access %failed%. $page_title Loc 27. Error details follow<br><br> " . mysqli_error($con));
        disconnect();
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
        <button id = "advancemonth" style="margin-bottom: 1em;" onclick = "advanceMonthDisplay();"><span class="oi oi-caret-bottom"></span></button>';

// wrap the two returns up in a json

    $return1 = prepareStringforXMLandJSONParse($return1);
    $return2 = prepareStringforXMLandJSONParse($return2);

    $json_string = '<returns>{"return1": "' . $return1 . '", "return2": "' . $return2 . '", "return3": "' . $chair_number . '"}</returns>';

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
                chair_number = '$chair_number' AND
                shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo("Oops - database access %failed%. $page_title Loc 28. Error details follow<br><br> " . mysqli_error($con));
        disconnect();
        exit(1);
    }

    $row = mysqli_fetch_array($result);

    if ($row['COUNT(*)'] == 1) {

        $sql = "DELETE FROM ecommerce_staff_holidays
                    WHERE
                staff_holiday_year = '$staff_holiday_year' AND
                staff_holiday_month = '$staff_holiday_month' AND
                staff_holiday_day = '$staff_holiday_day'AND
                chair_number = '$chair_number' AND
                shop_code = '$shop_code';";

        $result = mysqli_query($con, $sql);

        if (!$result) {
            echo("Oops - database access %failed%. $page_title Loc 29. Error details follow<br><br> " . mysqli_error($con));
            disconnect();
            exit(1);
        }
    } else {

        $sql = "INSERT INTO ecommerce_staff_holidays (
                shop_code,
                staff_holiday_year,
                staff_holiday_month,
                staff_holiday_day,
                chair_number)
            VALUES (
                '$shop_code',
                '$staff_holiday_year',
                '$staff_holiday_month',
                '$staff_holiday_day',
                '$chair_number');";

        $result = mysqli_query($con, $sql);

        if (!$result) {
            echo("Oops - database access %failed%. $page_title Loc 30. Error details follow<br><br> " . mysqli_error($con));
            disconnect();
            exit(1);
        }
    }
}

#####################  'build_work_pattern_table_for_week_display' ####################

if ($helper_type == 'build_work_pattern_table_for_week_display') {

    $chair_number = $_POST['chair_number'];

    $slot_length = 60 / $number_of_slots_per_hour;

// Build an array of chair numbers (can't guarantee they're sequential) to permit
// close control of "get previous" and "get next" buttons and pick up the
// chair_owner and pattern_json for the current chair_number while you're at it.
// If the supplied chair_number is 0, return data the first chair in the series

    $sql = "SELECT 
                chair_owner,
                chair_number,
                pattern_json
            FROM ecommerce_work_patterns
            WHERE shop_code = '$shop_code'
            ORDER BY chair_number ASC;";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo("Oops - database access %failed%. $page_title Loc 31. Error details follow<br><br> " . mysqli_error($con));
        disconnect();
        exit(1);
    }

    $chairs_index = 0;

    $chairs_array = array(); //this is arranged as a 2d array chairs[['chair_number' : nn, 'chair_name' : aaaa]]

    while ($row = mysqli_fetch_array($result)) {

        $chairs_array[$chairs_index]['chair_number'] = $row['chair_number'];
        $chairs_array[$chairs_index]['chair_owner'] = $row['chair_owner'];

        if ($row['chair_number'] == $chair_number || $chair_number == 0) {
            $chair_number = $row['chair_number'];
            $chair_owner = $row['chair_owner'];
            $pattern_json = $row['pattern_json'];
            $chair_owner_index = $chairs_index;
        }

        $chairs_index++;
    }

// use $chair_owners array to define preceding and next_chair indices

    $preceding_chair_index = $chair_owner_index - 1;
    $next_chair_index = $chair_owner_index + 1;
    if ($preceding_chair_index < 0)
        $preceding_chair_index = 0;
    if ($next_chair_index > count($chairs_array) - 1)
        $next_chair_index = count($chairs_array) - 1;
    $preceding_chair_number = $chairs_array[$preceding_chair_index]['chair_number'];
    $next_chair_number = $chairs_array[$next_chair_index]['chair_number'];

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
            <p>
                <button style="position: absolute; left: 10%;"
                  title = "Delete chair"
                  onclick = "prepareToDeleteChair(' . $chair_number . ');">
                    <span class="oi oi-minus"></span>    
                </button>
                <button style="position: absolute; right: 10%;"
                  title = "Add new chair"
                  onclick = "document.getElementById(\'createnewchairdisplay\').style.display = \'block\';
                             document.getElementById(\'newchairnumbermessage\').style.display = \'none\';">
                    <span class="oi oi-plus"></span>                   
                </button>
                <span>Standard weekly <br>patterns for Chair : </span>            
            </p>
                <button onclick = "buildPatternDisplay(' . $preceding_chair_number . ');" >
                    <span class="oi oi-caret-left"></span>
                </button>&nbsp;
                <span id = "staffholidaychairnum">' . $chair_number . '</span>&nbsp;
                <button onclick = "buildPatternDisplay(' . $next_chair_number . ');">
                    <span class="oi oi-caret-right"></span>
                </button>
                <span>&nbsp;&nbsp;:&nbsp;&nbsp;<span>
                <input id="currentchairowner" type="text" name="currentchairowner"
                   value = "' . $chair_owner . '"
                   style = "height: 2.5em; text-align: center; font-weight: bold; background: aliceblue; margin-top: 1em;" 
                   maxlength="20" size="10">
            <p style="margin-top: 1em;">
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

    $json_string = '<returns>{"return1": "' . $return1 . '", "return2": "' . $return2 . '", "return3": "' . $chair_number . '"}</returns>';

    header("Content-type: text/xml");
    echo "<?xml version='1.0' encoding='UTF-8'?>";
    echo $json_string;
}

#####################  save_pattern ####################

if ($helper_type == "save_pattern") {

    $chair_number = $_POST['chair_number'];
    $chair_owner = $_POST['chair_owner'];
    $pattern_json = $_POST['pattern_json'];

    $sql = "UPDATE ecommerce_work_patterns SET
                    chair_owner = '$chair_owner',
                    pattern_json = '$pattern_json'
                WHERE
                    chair_number = '$chair_number' AND
                    shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);
    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 32. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    } else {
        echo "Save succeeded";
    }
}

#####################  prepare_to_delete_chair ####################

if ($helper_type == "prepare_to_delete_chair") {

    $chair_number = $_POST['chair_number'];

    $today = date("Y-m-d");

    // see if there are any reservations for the given chair and use this to construct an
    // appropriate message to ask users to confirm they really want to delete thechair

    $sql = "SELECT 
                 COUNT(*)
            FROM ecommerce_reservations
            WHERE 
                assigned_chair_number = '$chair_number' AND
                reservation_date >= '$today' AND
                shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 33. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    $row = mysqli_fetch_array($result);
    $count = $row['COUNT(*)'];

    echo "Do you really want to delete chair_number $chair_number? ";

    if ($count == 0) {
        echo "(There are no upcoming reservations for this chair)";
    } else {
        echo "(There are $count upcoming reservations for this chair)";
    }
}

#####################  delete_chair ####################

if ($helper_type == "delete_chair") {

    $chair_number = $_POST['chair_number'];

    $sql = "START TRANSACTION;";

    $result = mysqli_query($con, $sql);
    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 34. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    $sql = "DELETE FROM ecommerce_work_patterns
                WHERE 
                    chair_number = '$chair_number' AND
                    shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        $sql = "ROLLBACK;";
        $result = mysqli_query($con, $sql);
        echo "Oops - database access %failed%. $page_title Loc 35. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    $sql = "DELETE FROM ecommerce_staff_holidays
                WHERE 
                    chair_number = '$chair_number' AND
                    shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        $sql = "ROLLBACK;";
        $result = mysqli_query($con, $sql);
        echo "Oops - database access %failed%. $page_title Loc 36. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    $sql = "DELETE FROM ecommerce_reservations
                WHERE 
                    assigned_chair_number = '$chair_number' AND
                    shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        $sql = "ROLLBACK;";
        echo "Oops - database access %failed%. $page_title Loc 37. Error details follow<br><br> " . mysqli_error($con);
        $result = mysqli_query($con, $sql);
        disconnect();
        exit(1);
    }

    $sql = "COMMIT;";

    $result = mysqli_query($con, $sql);
    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 38. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    echo "deletion succeeded";
}

#####################  insert_chair ####################

if ($helper_type == "insert_chair") {

    $chair_number = $_POST['chair_number'];
    $chair_owner = $_POST['chair_owner'];

    // check that chair_owner is an integer within valid range

    if ($chair_number < 1 || $chair_number > 100 || $chair_number - floor($chair_number) > 0) {
        echo "Invalid chair numberb";
        exit(0);
    }

    // now check that there's not already a record for this chair_number

    $sql = "SELECT COUNT(*)
                FROM ecommerce_work_patterns
                WHERE 
                    chair_number = '$chair_number' AND
                    shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 39. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    $row = mysqli_fetch_array($result);
    $count = $row['COUNT(*)'];

    if ($count > 0) {
        echo "Chair already exists";
        exit(0);
    }

    // insert the record

    $pattern_json = '[]';

    $sql = "INSERT INTO ecommerce_work_patterns (
                shop_code,
                chair_number,
                chair_owner,
                pattern_json)
            VALUES (
                '$shop_code',
                '$chair_number', 
                '$chair_owner',
                '$pattern_json');";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 40. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }
    echo "New chair created";
}

#####################  build_check_display ####################

if ($helper_type == "build_check_display") {
    $min_chair_number = $_POST['min_chair_number'];
    $max_chair_number = $_POST['max_chair_number'];

    $slot_length = 60 / $number_of_slots_per_hour;

    $today_day = date("j");
    $today_month = date("n"); // (1-12)
    $today_year = date("Y");

    $current_month = $today_month;
    $current_year = $today_year;

    // Begin by building a "pick-list" to allow 
    // the customer to choose a stylist

    $return = '';

    $sql = "SELECT 
                chair_number,
                chair_owner
            FROM ecommerce_work_patterns
            WHERE shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 41. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    $return = "<form style = 'margin-bottom: 1em;'>
                <label for='stylistmonthpicklist' name='stylistmonthpicklist'>Target Stylist(s) : </label>
                <select id='stylistmonthpicklist' onchange = 'handleStylistChangeOnCheckDisplay();'>";

    if ($min_chair_number != $max_chair_number) {
        $return .= "<option selected value = '0'>All</option>";
    } else {
        $return .= "<option value = '0'>All</option>";
    }

    while ($row = mysqli_fetch_array($result, MYSQLI_BOTH)) {

        $chair_number = $row['chair_number'];
        $chair_owner = $row['chair_owner'];

        if (($min_chair_number == $max_chair_number) && ($chair_number == $min_chair_number)) {
            $return .= "<option selected value = '$chair_number'>$chair_owner</option>";
        } else {
            $return .= "<option value = '$chair_number'>$chair_owner</option>";
        }
    }

    $return .= "</select></form>";

// Although the code below has been written with the abiility to report on an 
// arbitarry range of months, it seems more practical to limit it to just the
// current and the next month

    $number_of_months_in_report = 2;

    $check_display_length = 0;

    for ($i = 0; $i < $number_of_months_in_report; $i++) {

        build_availability_variables_for_month($current_year, $current_month, $min_chair_number, $max_chair_number);

// When a member of staff goes sick, a full appointment book represents a major 
// administrative problem. Suddenly there are a bunch of slots = possibly spanning
// a whole block of days - with booked appointmnts that can't be fulfilled. Somehow
// these bookers have to be contacted so that they don't turn up and make a fuss. 
// But which ones? For a given slot you have both telephone and email (prepaid!)
// bookers. You may still have some free staff to partially service the slot, so which 
// customers are you going to prioritise? It's easier to think about which customers
// are easiest to put off. Email (prepaid) customers are by far the easiest
// because you can email them in a single batch and invite them to rebook
// /themselves/ - ie put the ball in their court. There's no easy way out for telpehone
// bookers - you're going to have to contact them individually and rebook them
// over the phone. Groan!
// What's clear at this point is that we're going to have to deal with the appointments
// for a slot as a /group/ (eg, we need to know how many of them are emails and how 
// many are telephone), but at the moment they only exist as separate reservation records
// on the database, and this makes them very difficult to deal with. So, on this first 
// pass through the database, create a more useful data structure, compromised_slots,
// to group them up by slot - see comments in the "build" function below for details
// of the structure of compromised_slots.    

        build_compromised_slot_array_for_month($current_year, $current_month, $min_chair_number, $max_chair_number);

// OK, let's test this out. Suppose we want to build output for the current month
// range displaying compromised slots for each month so we can invite the first email
// customer for the slot to re-book themselves.
// 
// Scoot through the whole compromised_slots array for each month. This is not terribly
// efficient of course but things are complicated enough as it is and at least everything
// is in memory now. We might in future take advantage of the fact that the entries are
// in date order, but notjust now, eh.

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

            $compromised_reservations_total = count($compromised_slots_array[$j]['reservations']);
            if ($compromised_reservations_total > 1) {
                $reason = "Several chairs are absent";
            } else {
                $reason = "Chair " . $compromised_slots_array[$j]['reservations'][0]['assigned_chair_number'] . " is absent";
            }

// The format of the checkbox id for a compromised slot is "cr" + cr$reservation_slot. 
// Tuck the slot, date and number of email reservations for the slot away as comma-separated 
// values in a hidden span with id "crs" + cr$reservation_slot

            $return .= "
                    <p style='margin-bottom: 1em;'>
                        <input type = 'checkbox' id = 'cr$check_display_length' onclick = 'handleCheckClick($check_display_length);'>
                           <span id = 'crkey$check_display_length' style = 'display: none;'>$reservation_slot,$reservation_date,$reservation_emailtotal</span>
                           <span> The $hour_padded:$minutes_padded slot on $reservation_date is under-resourced : $reason</span>
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
// tells the customer that due to circumstances etc you can't fulfill their reservation. If the compromised slot
// isn't for a "expressly-chosen" stylist, there's the possibility that another stylist might be available
// to take the booking. In this case, the assigned chair in the compromised booking is simply switched to
// to another stylist and the customer is sent an explanatory email. However, if either the compromised slot has
// been expressly chosen or there's no spare capacity, then the apology email includes a link referencing
// the original reservation number inviting the inconveniencd customer  to rebook. 
// Meanwhile the original reservation is marked as "postponed". The shop management would
// repeat this as necessary, depending on how many staff they were down. Of course, this doesn't
// completely fix the problem (a compromised slot might contain only telephone bookers, for example,
// and so this would continue to pop up in the "check" display until you manually fix them).
// However it's a start. Once you'd cleared as many email bookers as you could, you'd get onto 
// the phone! To help things along, the compromised telephone reservations would be displayed
// as "change" links so, with the booker on the phone you can rebook them just by clicking 
// on their reservation entry in the check  list.
// Get the JSON containing the list of compromised slots

    $slot_rows_string = $_POST['slot_rows'];

    // Begin by building an array of chair_owner names

    $sql = "SELECT 
                chair_number,
                chair_owner
            FROM ecommerce_work_patterns
            WHERE shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 42. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    $chair_owners = array();

    while ($row = mysqli_fetch_array($result)) {

        $chair_number = $row['chair_number'];
        $chair_owner = $row['chair_owner'];
        $chair_owners[$chair_number - 1] = $chair_owner;
    }

    $slot_rows_array = json_decode($slot_rows_string, true);

    // Go through the slots month-wise, building availability_variable and compromised_slot details for each
    // month so that we can see all the stylists that are available for each and, with luck, rebook the slot. 
    // We want the widest possible view of availability, so set min and max chair-number variables to their limits


    $min_chair_number = 0;
    $max_chair_number = 10000;

    $last_month = '';
    $last_year = '';

    for ($i = 0; $i < count($slot_rows_array); $i++) {

        $reservation_slot = $slot_rows_array[$i]['slot'];
        $reservation_date = $slot_rows_array[$i]['slotdate'];
        $reservation_day = date("j", strtotime($reservation_date));

        $current_month = date("n", strtotime($reservation_date));
        $current_year = date("Y", strtotime($reservation_date));

        if ($current_month != $last_month) {

            build_availability_variables_for_month($current_year, $current_month, $min_chair_number, $max_chair_number);
            build_compromised_slot_array_for_month($current_year, $current_month, $min_chair_number, $max_chair_number);

            $last_month = $current_month;
            $last_year = $current_year;
        }

        // get the reservation that is causing the problem. First find its index in the compromised_slots array

        for ($j = 0; $j < count($compromised_slots_array); $j++) {
            if ($compromised_slots_array[$j]['slot'] == $reservation_slot && $compromised_slots_array[$j]['date'] == $reservation_date) {
                $compromised_target_index = $j;
            }
        }

        $problem_reservation_number = $compromised_slots_array[$compromised_target_index]['reservations'][0]['reservation_number'];
        $problem_reserver_id = $compromised_slots_array[$compromised_target_index]['reservations'][0]['reserver_id'];
        $problem_reservation_type = $compromised_slots_array[$compromised_target_index]['reservations'][0]['reservation_type'];
        $problem_reservation_status = $compromised_slots_array[$compromised_target_index]['reservations'][0]['reservation_status'];
        $problem_assigned_chair_number = $compromised_slots_array[$compromised_target_index]['reservations'][0]['assigned_chair_number'];
        $problem_chair_expressly_chosen = $compromised_slots_array[$compromised_target_index]['reservations'][0]['chair_expressly_chosen'];

        if ($problem_reservation_type == "email") { // if email we're going to send an email (tho not sure what sort yet), otherwise nothing to do
            // has the booker expressly chosen this sylist?
            $failed_to_rebook = true;
            if ($problem_chair_expressly_chosen == "N") { //Ok, there's a chance we can rebook this
                // is there an unbooked stylist for this slot? If so, switch the problem reservation to this chair and send a mild apology
                foreach ($slot_availability_array[$reservation_slot][$reservation_day - 1] as $key => $value) {

                    if ($value == "A") { //hooray - can rebook
                        $failed_to_rebook = false;
                        $free_chair_number = $key;

                        // switch chair to free stylist and send apologetic "rebooked" mail

                        $sql = "UPDATE ecommerce_reservations SET
                                        assigned_chair_number = '$key'
                                    WHERE
                                        reservation_number = '$problem_reservation_number' AND
                                        shop_code = '$shop_code';";

                        $result = mysqli_query($con, $sql);
                        if (!$result) {
                            echo "Oops - database access %failed%. $page_title Loc 43. Error details follow<br><br> " . mysqli_error($con);
                            disconnect();
                            exit(1);
                        }

                        $appointment_string = slot_date_to_string($reservation_slot, $reservation_date, $number_of_slots_per_hour);

                        $mailing_address = $problem_reserver_id;
                        $mailing_title = "Your reservation at " . $shop_name;
                        $new_chair_owner = $chair_owners[$key - 1];
                        $mailing_message = " 
                                <p>Dear Customer</p>
                                <p>Due to circumstances beyond our control we have had to change the stylist on your reservation
                                   for $appointment_string to $new_chair_owner.
                                   Please accept  our apologies.
                                </p>";

                        $mailing_result = send_email_via_postmark($mailing_address, $mailing_title, $mailing_message);

                        // if we haven't managed to send a renewal email, just carry on (may just have a duff email address)

                        break;
                    }
                }
            }

            if ($problem_chair_expressly_chosen == "Y" || $failed_to_rebook) { // no good - got to cancel
                // switch reservation_status to "P" and send apologetic "cancelled" mail
                $sql = "UPDATE ecommerce_reservations SET
                                    reservation_status = 'P'
                                WHERE
                                    reservation_number = '$problem_reservation_number' AND
                                    shop_code = '$shop_code';";

                $result = mysqli_query($con, $sql);
                if (!$result) {
                    echo "Oops - database access %failed%. $page_title Loc 44. Error details follow<br><br> " . mysqli_error($con);
                    disconnect();
                    exit(1);
                }

                $appointment_string = slot_date_to_string($reservation_slot, $reservation_date, $number_of_slots_per_hour);

                // conventionally would have added a version number paramter here in order to avoid cache problems
                // but this isn't available. However we've go a unique reservation number, so should be OK

                $rebookerlink = "https://" . $shop_url . "/index.php?mode=change&resnum=$problem_reservation_number";

                $mailing_address = $problem_reserver_id;
                $mailing_title = "Your reservation at " . $shop_name;
                $mailing_message = " 
                        <p>Dear Customer</p>
                        <p>Due to circumstances beyond our control we are now unable to fufil your reservation 
                            for $appointment_string</p>
                         <p>Please click the following link to choose another time        
                            $rebookerlink" . "</p>";

                $mailing_result = send_email_via_postmark($mailing_address, $mailing_title, $mailing_message);

                // if we haven't managed to send a renewal email, just carry on (may just have a duff email address)
            }
        }
    }
    echo ("successful exit");
}

#####################  login_with_user_credentials ####################

if ($helper_type == "login_with_user_credentials") {

    $user_id = $_POST['user_id'];
    $password = $_POST['password'];


    $sql = "SELECT COUNT(*)
            FROM ecommerce_user_passwords
            WHERE
                user_id = '$user_id' AND 
                password  = '$password' AND
                shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 45. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    $trusted_user_code = 0;

    $row = mysqli_fetch_array($result, MYSQLI_BOTH);

    $login_outcome = "failed";

    if ($row['COUNT(*)'] == 1) {

        $login_outcome = "succeeded";

        // now build and encrypted version of the user_id and password. Well, actually
        // we never need to un-encrypt them, so just generate a random number and save
        // it in the record

        $trusted_user_code = mt_rand(1000000, 2000000);

        $sql = "UPDATE ecommerce_user_passwords SET
                    trusted_user_code = '$trusted_user_code'
                WHERE
                    user_id = '$user_id' AND
                    shop_code = '$shop_code';";

        $result = mysqli_query($con, $sql);
        if (!$result) {
            echo "Oops - database access %failed%. $page_title Loc 46. Error details follow<br><br> " . mysqli_error($con);
            disconnect();
            exit(1);
        }
    }

    // return the outcome and the encrypted_keys value (if you managed to create it) in a jso

    $json_string = '<returns>{"return1": "' . $login_outcome . '", "return2": "' . $trusted_user_code . '"}</returns>';

    header("Content-type: text/xml");
    echo "<?xml version='1.0' encoding='UTF-8'?>";
    echo $json_string;
}

#####################  login_with_trusted_user_code ####################

if ($helper_type == "login_with_trusted_user_code") {

    $trusted_user_code = $_POST['trusted_user_code'];

    $sql = "SELECT COUNT(*)
            FROM ecommerce_user_passwords
            WHERE
                trusted_user_code = '$trusted_user_code' AND
                shop_code = '$shop_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed%. $page_title Loc 47. Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    $login_outcome = "failed";

    $row = mysqli_fetch_array($result, MYSQLI_BOTH);

    if ($row['COUNT(*)'] == 1) {

        $login_outcome = "succeeded";
    }

    echo $login_outcome;
}

#####################  validate_trusted_user_code ####################

if ($helper_type == "validate_trusted_user_code") {

    $trusted_user_code = $_POST['trusted_user_code'];

// check $trusted_user_code is valid for this $shop_code

    $sql = "SELECT COUNT(*)
            FROM ecommerce_user_passwords
            WHERE
                shop_code = '$shop_code' AND 
                trusted_user_code = '$trusted_user_code';";

    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo "Oops - database access %failed% in $page_title Loc 48. . Error details follow<br><br> " . mysqli_error($con);
        disconnect();
        exit(1);
    }

    $row = mysqli_fetch_array($result, MYSQLI_BOTH);

    if ($row['COUNT(*)'] == 1) {
        echo "valid";
    } else {
        echo "invalid";
    }
}

disconnect();


