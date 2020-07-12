<p>The image below shows the online booking screen. The customer is required to enter an email address (for subsequent communication purposes) and is then is able to select an available appointment time. The screen initially shows days with free appointments for a three-month period. Selecting a day reveals a detailed screen displaying the free appointment slots on that day. Selecting a slot initiates a payment process. Successful conclusion results in the despatch of a confirmation receipt to the customer's email address.</p>
<div style="width: 60%; margin-left: auto; margin-right: auto; text-align: center;">
<img src="img/screen1.png"> 
</div>
<p>The image below shows the configuration view of the online booking screen. This is initiated by calling booker.html with "mode" and "version" parameters - best achieved by interfacing it to a signon screen. Once entered, the screen offers six management options. The image below has been produced via booker.html?mode=viewer?ver=2.0 and displays the shop-manager's view of the appointment book. This permits selection of a day/slot combination and displays the bookings that have been created for that slot as a popup overlay. The popup allows these bookings to be re-scheduled or cancelled.</p>
<p>The "Take Tel Booking" button allows the manager to record an unpaid telephone booking.</p>  
<div style="width: 50%; margin-left: auto; margin-right: auto; text-align: center;">
<img src="img/screen2.png"> 
</div>
<p>The three buttons on the lower rank of the control screen provide for configuration of the appointment book. "Set Bank Holidays" and "Set Staff Holidays" allow complete days to be blocked out. The "Set Working Patterns" button is used to set the working patterns of each individual member of staff and is rather more complex</p>
<p>"Working patterns" are currently configured by the "slot sessions" and are set by "chair number" for a full week. Setting working patterns for a "chair" is achieved by checking the boxes for day/slot combinations. Holding the shift key while clicking a checkbox will toggle the range of checkboxes above it. <p> A screenshot for a sample work-pattern configuration is shown below. This commits "Robert" to work Monday to Saturday between 8.00am and 7.00pm except for an hour off at 1pm each day and Saturday when he leaves at 6.00pm. Lucky Robert!</p></p>
<div style="width: 60%; margin-left: auto; margin-right: auto; text-align: center;">
<img src="img/screen3.png"> 
</div>
<p> The number of chairs and the names of their owners are set by manually initialising the associated records in the database.</p>
<p>Perhaps the most challenging aspect of a reservation system is the mechanism for fielding the consequences of unexpected staff absences. When a member of staff fails to show up it may no longer be possible to honour reservations. Once the "Staff Absences" button has been used to record the absence, the consequences can be displayed by clicking the "Check Staffing" button. This lists the compromised slots. For telephone bookers, the only option is to get on the phone and arrange another appointment. But online (prepaid) customers are easier to handle as they have left their email addresses on the system and can thus be sent a digital apology. The fact that they have already paid and might be looking for a refund is handled by including a link in the apology inviting them to select a new date (free of charge of course). The screenshots below illustrate the mechanism</p>


