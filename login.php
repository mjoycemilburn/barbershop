<?php
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
                    window.location.assign("booker.html?mode=viewer&ver=1.0");
                    };
             </script>       
        </div>
    </body>
</html>

