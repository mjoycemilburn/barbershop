# barbershop

Barbershop V4.0 is a web-based system that allows on-line customers to make reservation against a server-based appointment book. The system includes a supporting configuration page that allows shop-management to specify staff-working patterns and to block out staff and bank holidays etc. Though it expects bookings to be made generaly online and paid upfront using Paypal, the support page also allows the shop to take unpaid bookings from telephone enquirers. The software is designed to work from mobile phones as well as laptops etc. The system requires a Mysql database

The code as presented here would need some configuration of elements to accomodate local setup - eg Paypal business account and email engine account. These elements are identified in the code by a **CONFIG REQUIRED** tag.

The code is extensively commented so configuration and customisation should be reasonably straightforward.

Development of the system is ongoing and there are some limitations - for example, reservation slots must be of equal length. However, the length of the reservation slot,  the number of chairs and staff working hours are all configurable, and it is now also possible for customers to specify a preferred stylist.

See <a href = "https://mjoycemilburn.github.io/barbershop/">https://mjoycemilburn.github.io/barbershop/ for screenshots</a>
