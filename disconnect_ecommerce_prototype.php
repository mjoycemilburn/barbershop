<?php

if ($logging) {

    $disconnect_datetime = date('Y-m-d H:i:s');

    $sql = "UPDATE pagelogentries
        SET
        disconnect_datetime = '$disconnect_datetime'
            WHERE
            page_title = '$page_title' 
			AND 
            connect_datetime = '$connect_datetime'";


    $result = mysqli_query($con, $sql);

    if (!$result) {
        echo 'Database logging error ' . $result;
        mysqli_close($con);
        exit(1);
    }
}

mysqli_close($con);
?>