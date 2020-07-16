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
a new version number in launchBooker below can be relied upon to reference the new version
rather than out-of-date cache. Secondly, and more importantly, because the management
routines are advertised on the web, they need to be protected from unauthorised access.
Login.php provides a way of declaring some sort of security token known only to 
authorised users.

In high security applications the usual way of handling this would be to arrange for
the login.php to set a parameter in the server session record that is checked by every
subsequent php routine. If these routines fail to find a satisfactory value in this 
parameter they direct control back to the login routine. 

An issue with this arrangement is that a session record expires within quite a short 
period of time (typically 20 minutes) unless it is regularly referenced. While this
is good practice for high-security applications (eg banking) in that it minimises the risk
of an unattended session being misused, it is felt that this arrangement is inappropriate
for the booker reservation system since it implies that management may be required
frequently to re-enter their security credentials to the login screen. The booker system
does not present a high security risk - indeed we /want/ it to be publicly accessible
for reservation purposes. All we might be concerned about are the routines for rebookig
reservations and for creating holiday and work patterns settings. It would be 
counter-productive to force a login procedure onto a harassed shop-manager fielding
a query fro an angry customer. 

Accordingly, while booker.html uses the server session record as described above, the login
code laid out below is rather more relaxed about the presentation of security credentials.
On first use the login routine will request a user-id and password, as would normally
be expected. However, having validated these against the database, a "trusted user" parameter
containing an encrypted version of the original credentials returned by the validation routine
is placed in the local storage record. The PC or hone on which the initial login was made
now becomes a "trusted device" because future calls to login will first attempt to
validate themselves using the encrypted parameter - user-Id and password input will only
be requested if the encrypted parameter isn't accepted. While this arrangement isn't ideal - there 
isn't even a "password-change" facility, for example (it is assumed that the password record
in the database will be created as part of the initial setup) - but it seems a reasonable
compromise and can be readily replaced by a more conventional login if circumstances demand.

-->
<html>
    <head>
        <title>Booker Managment signon</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <!-- Latest compiled and minified CSS -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
        <!-- jQuery library -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
        <!-- Optional theme -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">

        <style>
            .formlabel {
                display: inline-block;
                width: 25%;
            }
        </style>
    </head>
    <body>
        <div class = 'container-fluid'>

            <!-- dummy sidebar to make display look reasonable on large screens -->

            <div class="row">

                <div class= "col-md-4">
                </div>

                <div class = "col-md-4 col xs-12">        

                    <form id="dummyform"></form>  <!-- dummy form used by d/b interface routines -->

                    <h2 style="text-align: center;">Booker Management login</h2>

                    <form style="border: solid; width: 100%; padding: 3vh 1vw 4vh 1vw; margin: 4vh auto 5vh auto;">        
                        <p>
                            <label id = "useridlabel" class = "formlabel">User Id:</label>&nbsp;&nbsp;  
                            <input id = "userid" type="text" title="Your system identifier" maxlength="10"  size="10" onkeyup = "clearAllErrors();">
                        </p>
                        <p>
                            <label id = "passwordlabel" class = "formlabel">Password:</label>&nbsp;&nbsp;
                            <input  id = "password" type="password" title="Your personal password" maxlength="12" size="12" onkeyup = "clearAllErrors();">
                        </p>
                        <p id = "loginpanelmessage" style = "width: 30vw; display: none;">&nbsp;
                        </p>
                        <label class = "formlabel"></label>&nbsp;&nbsp;
                        <button class = 'btn btn-primary' style="margin-top: 1em;" onclick = "loginWithUnencryptedCredentials()" type="button">Login</button>&nbsp;&nbsp;&nbsp;
                    </form>
                </div>
                <div class= "col-md-4">
                </div>
            </div>
        </div>
        <script>

            // It was originally intended that Login and Password Change Buttons would only be activated
            // after typing was seen in the userId and password fields. But autofill fires up as soon as  
            // the browser sees a form containing a password field and cannot be overridden - so 
            // effectively typing has always occurred in these fields. 

            function clearAllErrors() {
                loginpanelmessage.style.display = "none";
            }

            userid = document.getElementById('userid');
            password = document.getElementById('password');
            function loginWithUnencryptedCredentials() {

                // we've clicked a button on the form, so this means native form events are fired also
                // We need to stop these in their tracks!

                event.preventDefault();
                var form = document.forms.namedItem("dummyform");
                var oData = new FormData(form);
                oData.append("helper_type", "login_with_unencrypted_credentials");
                oData.append("user_id", userid.value);
                oData.append("password", password.value);
                var oReq = new XMLHttpRequest();
                oReq.open("POST", "php/booker_helpers.php", true);
                oReq.onload = function (oEvent) {
                    if (oReq.status == 200) {

                        var response = oReq.responseText;
                        if (response.indexOf("%failed%") != -1) {
                            alert(response);
                        } else {

                            // solid response - so look at the two parameters that will
                            // have come back. The first will tell us if the credentials 
                            // were accepted or not. If they are, the second will contain
                            // the encrypted keys

                            var xmlDoc = oReq.responseXML;
                            var JSONString = xmlDoc.getElementsByTagName("returns")[0].childNodes[0].nodeValue;
                            var JSONObject = JSON.parse(JSONString);
                            validationResult = JSONObject.return1;
                            encryptedCredentials = JSONObject.return2;
                            if (validationResult == "succeeded") {
                                localStorage.setItem('bookerencryptedcredentials', encryptedCredentials);
                                launchBooker();
                            } else {
                                loginpanelmessage.innerHTML = "Sorry, but this user-id/password combination doesn't work";
                                loginpanelmessage.style.display = "block";
                                loginpanelmessage.style.color = "red";
                            }
                        }
                    }
                };
                oReq.send(oData);
            }

            function loginWithEncryptedCredentials(encryptedCredentials) {

                var oData = new FormData(dummyform);
                oData.append("helper_type", "login_with_encrypted_credentials");
                oData.append("encrypted_credentials", encryptedCredentials);
                var oReq = new XMLHttpRequest();
                oReq.open("POST", "php/booker_helpers.php", true);
                oReq.onload = function (oEvent) {
                    if (oReq.status == 200) {
                        var response = oReq.responseText;
                        if (response.indexOf("%failed%") != -1) {
                            alert(response);
                        } else {
                            launchBooker();
                        }
                    }
                };
                oReq.send(oData);
            }

            function launchBooker() {

                // if this is a timeout response, there will be a return paramter
                // on the booker.php call that started the program - see if there
                // is one and, if so, add it to the booker.html call so we get 
                // back to the point that hit the timeout

                var target = "booker.html?ver=32.2";
                var returnParam = getUrlParameter("return");
                if (returnParam == "") {
                    target += "&mode=viewer";
                } else {
                    target += "&mode=" + returnParam;
                }

                window.location.assign(target);
            }

            function getUrlParameter(name) {
                name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
                var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
                var results = regex.exec(window.location.search);
                return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
            }

            window.onload = function () {

                // if encryptedCredentials are present, try to login with them

                if (localStorage.getItem('bookerencryptedcredentials')) {
                    var encryptedCredentials = localStorage.getItem('bookerencryptedcredentials');
                    loginWithEncryptedCredentials(encryptedCredentials)
                }

                // if you're still here, either the crdentials aren't there (ie 
                // first-time use of login on this device) or they're wrong, so
                // let the user (or the hacker!) fill in the user_id and password 
                // fields and try to get in through the front door this time
            };
        </script>  
    </body>
</html>
