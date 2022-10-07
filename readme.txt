Software components
-------------------

  * WordPress
  * Events Manager (https://wordpress.org/plugins/events-manager/)
    this is a basis of our registration system
  * Events Manager Pro (https://eventsmanagerpro.com/)
    we bought it long time ago and didn't renew subscription - hence the version 
	used is VERY old; it's not a big deal though as we are essentially using 
	only attendee_forms from it - and NOT PayPal integration
  * Events Manager for Limmud FSU Israel (https://github.com/alexagr/events-manager-limmud)
    this is custom integration plugin that tweaks Events Manager to operate
    in a slightly different mode ("participants" and "real" tickets - as described 
	below); it also provides integration with PayPal
  * Events Manager Hacks (https://github.com/alexagr/events-manager-limmud)
    some minor tweaks inside Events Manager / Pro plugins (due to their existence
	we DO NOT upgrade either of them)

We add the following code via My Custom Functions plugin to prevent Events Manager
from updating:

    /* disable updates to Events Manager - we did some manual hacks to its source code */
    function my_prevent_update_check($r, $url) {
    	if (0 === strpos($url, 'https://api.wordpress.org/plugins/update-check/1.1/')) {
            $plugins = json_decode($r['body']['plugins'], true);
            $my_plugin = 'events-manager/events-manager.php';
            if (array_key_exists($my_plugin, $plugins['plugins'])) {
                unset($plugins['plugins'][$my_plugin]);
            }
            $r['body']['plugins'] = json_encode($plugins);
    	}
    	return $r;
    }
    add_filter('http_request_args', 'my_prevent_update_check', 10, 2);


Concept of operation
--------------------

We use two types of tickets:
  * "participants" tickets - they cost $0
    (in the code we allow them to be less than $1 - e.g. $0.01 - and round them up to $0;
    this was used in the past to create Adult / Children tickets and sort them based on price;
    but lately we stopped doing this and use single "participants" ticket type)
  * "real" tickets - they cost $1 or more and are not shown in booking form

When user enters new booking it stays in "Pending" state (because Events Manager
is configured to require new booking confirmation). User is automatically
redirected to "booking summary" page. In parallel he receives a mail with a
link to the same page.

"Booking summary" page runs event-specific business logic when it opens.
This logic populates booking with "real" tickets (if they are not present)
and moves booking to "Awaiting Payment" state. This all happens "behind the
scene" - so user essentially sees his order filled and in "Awaiting Payment" 
state.

If event is in "Awaiting Payment" state smart PayPal button is displayed.
We use latest API (https://developer.paypal.com/docs/checkout/) with server-side
transaction set-up and capture. Upon successful PAYPAL transaction booking is 
moved to "Approved" state and user is redirected to "Booking success" page. 
Transactions are recorded in Events Manager Pro's  transaction table (and 
displayed at the bottom of Bookings admin screen).

NOTES: 
  - before 2020 we didn't have "booking summary" page - instead booking
    stayed in Pending state after submission; then we (org.committee) manually
    went to Admin page, filled order with "real" tickets and moved it to "Awaiting 
    Payment" state. Users received email with a payment link inside.
    In order to simplify our work we used "Admin Wizard" - that contained 
    event-specific custom code. This is all history now - as "booking
    summary" page provides a better flow.
  - before 2020 we used old PayPal integration API - where we constructed
    a "payment link" with all data and processed IPN (with the help of 
    "IPN for PayPal" plugin). This is all history now - as latest API
    is much more powerful and provides better user experience.

We use booking notes to record time when booking state changes (we just add note
with state name and Events Manager adds timestamp). Based on that, for example, 
periodic task that runs every hour finds bookings that were not payed for 3
days and moves them to "No Payment" state (essentially "rejected"). If some payment
was received the booking is moved into "Partially Paid" state instead.

Admin can add "No Expiration" note - to prevent specific booking from
moving to "No Payment" / "Partially Paid" state - for example, if specific
customer asked to pay later.

Discounts can be implemented in one of the following ways:
  * admin discount
  * tickets with negative value

Multilanguage considerations:
  * "participants" tickets should have qTranslate-X multi-language format in name
  * "real" tickets should have English name (we use it in PayPal transaction)
    and qTranslate-X multi-language format in description (we use it in "booking
	summary" page)
	
When we calculate the booking price we round up result - to eliminate 
"participants" tickets.

If "Waiting List" is configured for the specific event and total amount of 
bookings (both approved and in pending state) exceeds the specified threshold
booking is moved to "Waiting List" state. This is done by "booking summary" page
logic - see update_booking() function for details.


What to do when new year comes
------------------------------

1. Update update_booking() function in emlmd-booking.php

   This function is called when user fills in the registration and is redirected
   to the "payment" link. If order is in "pending" state and has no "real" tickets
   (that cost $1 or more) - it automatically adds needed tickets to it and moves
   it to "Awaiting Payment" state.

   You need to change business logic under the three blocks like this:
        if ($EM_Booking->event_id == 19) {
            // regular 2022 registration
            ...

   Update event ID to match the new event ID. Also update ticket IDs and adjust
   business logic as needed.

2. Update em_booking_js() function in emlmd-frontend.php

   This is Javascript code that runs when user fills the registration.
   It hides/shows relevant registration form elements, updates combo-box
   values and does various validations.

   Start with updating the event date - in the beginning of updateForm() function
   (line 148 in 2022):
        var d1 = new Date("2022-12-03");

   Then update the business logic as needed.

3. Update emlmd-csv.php

   These functions are called when admin exports data for the specific event.

   Start with updating event name in em_bookings_table_export_options() function.
   Then continue with updating intercept_csv_export() function:
     * update event IDs in lines like this:
         if (($EM_Event->event_id != 16) && ($EM_Event->event_id != 17) && ($EM_Event->event_id != 18)) {
     * update business logic as needed.

4. generate new secret codes and place them into:
     public_html/site/wp-content/plugins/events-manager-secrets/secrets.txt
   codes that start with 1 are for volunteers
   codes that start with 2 are for presentors
   codes that start with 3 are for VIPs and grant FREE participation
   codes that start with 4 are for guests - this lets them register via 
   volunteers/presenters registration page (i.e. even when regular registration
   is closed) but doesn't grant any discounts

   generate a new secret code for organizing committee and place in into
     public_html/site/wp-content/plugins/events-manager-secrets/admin.txt

   generate a new promo code (if relevant) and place in into
     public_html/site/wp-content/plugins/events-manager-secrets/promo.txt
