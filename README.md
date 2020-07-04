# barbershop

This is a web-based system to allow bookers to make reservation against an on-line appointment book. The system also includes a supporting configuration page that allows the shop-management to specify staff-working patterns and to block out bank holidays etc. It expects online bookings to be paid for using Paypal, though the support page also allows the shop to take unpaid bookings from telephone enquirers. The software is designed to work from mobile phones as well as laptops aetc. It requires a Mysql database

The code as presented here would need some configuration of elements to accomodate local setup - eg Paypal business account and email engine account. These elements are identified in the code by a ***CONFIG REQUIRED** tag.

Development of the system is ongoing and there are currently some limitations - in particular the number of bookable session per hour is currently set to 4 (though the number of chairs is unlimited) and staff work patterns can only be set to hourly intervals.

See <a href = "https://mjoycemilburn.github.io/barbershop/">https://mjoycemilburn.github.io/barbershop/ for screenshots</a>
