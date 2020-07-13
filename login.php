<?php

# create header to pre-expire the page and make sure the browser reloads it.This ensures that 
# php and html references also get reloaded if their version numbers have been changed
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>

<!DOCTYPE html>
<!--
This is the recommended entry for the online Management operation mode of booker.html 
and the one that should be advertised to users. There are two reasons for this.
Firstly in the event that booker.html changes, a new version of login.php containing
a new version number at line 21 below can be relied upon to reference the new version
rather than out-of-date cache. Secondly, code can be added here to take a user-id and
password to apply credential checking to the operation of booker.html. 
-->
<html>
    <head>
        <title>Booker Managment signon</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body>
        <div>
            <script>
                window.onload = function () {
                    window.location.assign("booker.html?mode=viewer&ver=32.0");
                    };
             </script>       
        </div>
    </body>
</html>

