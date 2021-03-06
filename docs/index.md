<p>The image below shows the online booking screen displayed by booker.php. The customer enters an email address and is then is able to select an available appointment time and, if required, a preferred stylist. The screen initially shows days with free appointments for a three-month period. Selecting a day reveals a detailed screen displaying the free appointment slots on that day. Selecting a slot initiates a payment process. Successful conclusion results in the despatch of a confirmation receipt to the customer's email address.</p>
<div style="width: 60%; margin-left: auto; margin-right: auto; text-align: center;">
<img src="img/screen1.png"> 
</div>
<p>The image below shows the configuration view of the online booking screen, accessed by calling login.php. Once entered, the screen offers six management options. The image below show the initial default view - the shop-manager's view of the appointment book. This permits selection of a day/slot combination and displays the bookings that have been created for that slot as a popup overlay. The popup allows these bookings to be re-scheduled or cancelled.</p>
<div style="width: 50%; margin-left: auto; margin-right: auto; text-align: center;">
<img src="img/screen2.png"> 
</div>
<p>The "Take Tel Booking" button allows the manager to record an unpaid telephone booking. The telephone number input field can be used to store additional contact details, if required. As with the online booking screen, a specificd stylist can be allocated to the reservation, if the customer requires this, otherwise a free stylist for this slot is allocated at random.</p>  
<p>The three buttons on the lower rank of the control screen provide for configuration of the appointment book. "Set Bank Holidays" and "Set Staff Holidays" allow complete days to be blocked out. The "Set Working Patterns" button is used to set the working patterns of each individual member of staff and is rather more complex</p>
<p>"Working patterns" are currently configured by the "slot sessions" and are set by "chair number" for a full week. Setting working patterns for a "chair" is achieved by checking the boxes for day/slot combinations. Holding the shift key (configuration is best done on a device witha keyboard) while clicking a checkbox will toggle the range of checkboxes above it. The "working patterns" button also allows you to add, remove and edit chair owners.</p>
<p> A screenshot for a sample work-pattern configuration is shown below. This commits "Robert" to work Monday to Saturday between 8.00am and 7.00pm except for an hour off at 1pm each day and Saturday when he leaves at 6.00pm. Lucky Robert!</p>
<div style="width: 60%; margin-left: auto; margin-right: auto; text-align: center;">
<img src="img/screen3.png"> 
</div>
<p>The number of chairs and the names of their owners are set by manually initialising the associated records in the database.</p>
<p>Perhaps the most challenging aspect of a reservation system is the mechanism for fielding the consequences of unexpected staff absences. When a member of staff fails to show up it may no longer be possible to honour reservations. Once the "Staff Absences" button has been used to record the absence, the consequences can be displayed by clicking the "Check Resourcing" button. This lists any compromised slots. For telephone bookers, the only option is to get on the phone and arrange another appointment. But online (prepaid) customers are easier to handle since they have left their email addresses on the system and can thus be sent a digital apology. If the original booking did not specify a stylist and an alternative is available, the system will allocate a replacement automatically. If not, thtere is no algternative to issue a cancellation email. The fact that online bookers have already paid and might be looking for a refund is handled by including a link in the apology inviting them to select a new date (free of charge of course). The screenshots below illustrate the mechanism</p>
<div style="width: 60%; margin-left: auto; margin-right: auto; text-align: center;">
<img src="img/screen4.png"> 
</div>
<p>Clicking the "Send Email Messages" button at this point will send cancellation messages and (where re-allocation of the stylist is not possible) re-booking links to a total of 4 customers (the first "email" customer in each of the checked slots). A typical message is shown below</p>
<div style="width: 60%; margin-left: auto; margin-right: auto; text-align: center;">
<img src="img/screen5.png"> 
</div>



