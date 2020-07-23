<?php

# This routines need to be  copied manually to the home directories on the local PC (C:/home/qfgavcxt) and the Live server

# set $logging appropriately

$logging = FALSE;

# if $_SESSION['testing'] is set use this to direct towards appropriate database, otherwise use main database

$testing = FALSE;
if (isset($_SESSION['testing'])) {
    if ($_SESSION['testing']) {
        $testing = TRUE;
    }
}

# Connect to the database : note params 2 and 3 here are set for local use and need to be changed 
# when the routine is deployed on the live server. For local operations seemed no point in using
# anything other than test_apparch2015


# Connect to the ecommerce_prototype database

$con = mysqli_connect('localhost','root','chickpeas','qfgavcxt_ecommerce_prototype');
if (!$con) {
    die('Could not connect: ' . mysqli_error($con));
    mysqli_set_charset($con, 'utf-8');
}

?>
