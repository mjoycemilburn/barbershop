# barbershop

Barbershop V5.0 is a web-based system that allows on-line customers to make reservation against a server-based appointment book. The system includes a supporting configuration page that allows shop-management to specify staff-working patterns and to block out staff and bank holidays etc. Though it expects customers to make bookings online and pay for them upfront using Paypal, the support page also allows the shop management to take unpaid bookings from telephone enquirers. The software is designed to work from mobile phones as well as laptops etc. It uses a Mysql database.

Barbershop V5.0 is designed for full commercial operation in the sense that it enable the operator to provide reservation services for an unlimited number of shops, all operating through a shared database and common code. Each shop has its own "website address" and is distinguished by its own graphic banner. Typically, a shop's booking page would be accessed by the customer via a website address such as oneills.cumbrianstylist.com and would be administered bvia a login screen at oneills.cumbrianstyliss.com/login.php. The cost of setup is minimal. Assuming the operator already has a website, the only cost (typically £10 pa) would be the purchase of an "addon domain" for the site (cumbrianstylist.com in the previous example). Individual shops would then be registered as subdomains of this addon domain (typically at zero charge).

How the operator might monetize this arrrangement is not specified. In these times of CV19, when an efficient reservation system is a useful element of infection control, you might consider providing a free service.

The code is extensively commented so configuration and customisation should be reasonably straightforward - see the installation guide document for details.

Barbershop V5.0 is built around the concept that reservation slot should all be of equal length (though there is nothing to stop users booking slots in sequence in order to accomodate more extensive procedures). This keeps the interface simple. In all other respects it is fully generalised and the number of chairs, the workig hours of staff and the range of treatments and prices are all configurable. It is also possible for customers to specify a preferred stylist.

See <a href = "https://mjoycemilburn.github.io/barbershop/">https://mjoycemilburn.github.io/barbershop/ for screenshots</a>
