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


Concept of operation
--------------------

We use two types of tickets:
  * "participants" tickets - they cost less than $1; price is used to sort them 
    (so that Adults always show first)
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
days and moves them to "Waiting List" state (essentially "rejected").
We can also add "No Expiration" note - to prevent specific booking from
moving to "Waiting List" state.

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
