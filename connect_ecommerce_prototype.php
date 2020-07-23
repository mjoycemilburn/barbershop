<?php

# This routines need to be  copied manually to the home directories on the local PC (C:/home/qfgavcxt) and the Live server

$con = mysqli_connect('hostname','root','password','database_name'); //**CONFIG REQUIRED**
if (!$con) {
    die('Could not connect: ' . mysqli_error($con));
}

?>
