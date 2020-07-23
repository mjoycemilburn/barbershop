<?php

# This routine need to be  placed in the home directory of your server. It may seem a bit "over the top" to 
# implement the connect/disconnect code as "includes" since, as presented here, they're exteremely simple. But
# you may want to add other features such as logging, so payes to create smoe flexibility
# 

$con = mysqli_connect('hostname','root','password','database_name'); //**CONFIG REQUIRED**
if (!$con) {
    die('Could not connect: ' . mysqli_error($con));
}

