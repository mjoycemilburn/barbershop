<?php

# This routine need to be  placed in the home directory of your server. 
# 

$con = mysqli_connect('hostname','root','password','database_name'); //**CONFIG REQUIRED**
if (!$con) {
    die('Could not connect: ' . mysqli_error($con));
}

