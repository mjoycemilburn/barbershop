# barbershop

Barbershop V2.0 is a web-based system allowing bookers to make reservation against an on-line appointment book. The system includes a supporting configuration page that allows shop-management to specify staff-working patterns and to block out staff and bank holidays etc. It expects online bookings to be paid upfront using Paypal, though the support page also allows the shop to take unpaid bookings from telephone enquirers. The software is designed to work from mobile phones as well as laptops etc. It requires a Mysql database

The code as presented here would need some configuration of elements to accomodate local setup - eg Paypal business account and email engine account. These elements are identified in the code by a **CONFIG REQUIRED** tag.

The code is exctensivey commented so configuration and customisation should be reasonably straightforward.

Development of the system is ongoing and there are currently some limitations - reservation slots must be of equal length and it is not possible for bookers to specify a stylist. However, the length of the reservation slot,  the number of chairs and staff working hours are all configurable.

See <a href = "https://mjoycemilburn.github.io/barbershop/">https://mjoycemilburn.github.io/barbershop/ for screenshots</a>
