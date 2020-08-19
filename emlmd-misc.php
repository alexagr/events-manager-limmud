<?php

class EM_Limmud_Misc {
    public static function init() {
        add_filter('manage_event_posts_columns', array(__CLASS__, 'show_edit_columns'), 11);
        add_filter('em_bookings_table_cols_col_action', array(__CLASS__, 'em_bookings_table_cols_col_action'), 10, 2);
        add_action('em_booking', array(__CLASS__, 'em_booking'), 10, 2);
        add_filter('em_booking_set_status', array(__CLASS__, 'em_booking_set_status'), 10, 2);
        add_action('emlmd_hourly_hook', array(__CLASS__, 'emlmd_hourly_hook'));
        add_action('em_bookings_table',array(__CLASS__,'em_bookings_table'),11,1);
        add_filter('em_get_currencies', array(__CLASS__, 'em_get_currencies'), 10, 2);
        add_filter('em_booking_calculate_price', array(__CLASS__, 'em_booking_calculate_price'), 10, 2);
        add_filter('em_ticket_is_displayable', array(__CLASS__, 'em_ticket_is_displayable'), 10, 2);
        add_filter('em_booking_form_tickets_cols', array(__CLASS__, 'em_booking_form_tickets_cols'), 10, 2);
        add_filter('em_booking_get_spaces', array(__CLASS__, 'em_booking_get_spaces'), 10, 2);
        add_filter('em_booking_get_person', array(__CLASS__, 'em_booking_get_person'), 10, 2);
        add_filter('em_get_booking', array(__CLASS__, 'em_get_booking'), 10, 2);
        add_filter('em_action_booking_add', array(__CLASS__, 'em_action_booking_add'), 10, 2);
    }

    public static function show_edit_columns($columns) { 
        if (get_option('dbem_show_event_details') == 'hide') {
            unset($columns['place']);
            unset($columns['description']);
            unset($columns['event_repeating']);
        }
        return $columns;
    }
    
    public static function em_bookings_table_cols_col_action($booking_actions, $EM_Booking) {
        if (($EM_Booking->booking_status == 6) || ($EM_Booking->booking_status == 7)) {
            $booking_actions = array(
                'approve' => '<a class="em-bookings-approve" href="'.em_add_get_params($url, array('action'=>'bookings_approve', 'booking_id'=>$EM_Booking->booking_id)).'">'.__('Approve','events-manager').'</a>',
                'reject' => '<a class="em-bookings-reject" href="'.em_add_get_params($url, array('action'=>'bookings_reject', 'booking_id'=>$EM_Booking->booking_id)).'">'.__('Reject','events-manager').'</a>',
                'delete' => '<span class="trash"><a class="em-bookings-delete" href="'.em_add_get_params($url, array('action'=>'bookings_delete', 'booking_id'=>$EM_Booking->booking_id)).'">'.__('Delete','events-manager').'</a></span>',
                'edit' => '<a class="em-bookings-edit" href="'.em_add_get_params($EM_Booking->get_event()->get_bookings_url(), array('booking_id'=>$EM_Booking->booking_id, 'em_ajax'=>null, 'em_obj'=>null)).'">'.__('Edit/View','events-manager').'</a>',
            );
        }
        if (get_option('dbem_admin_actions') == 'edit') {
            unset($booking_actions['approve']);
            unset($booking_actions['reject']);
            unset($booking_actions['unapprove']);
        }
        return $booking_actions; 
    } 

    public static function em_booking($EM_Booking, $booking_data) {
        unset($EM_Booking->status_array[4]); // remove Online Payment option
        $EM_Booking->status_array[6] = 'No Payment';         
        $EM_Booking->status_array[7] = 'Partially Paid';         
    }
    
    public static function em_booking_set_status($result, $EM_Booking) {
        if ($EM_Booking->booking_status == 5) {
            $EM_Booking->add_note('Awaiting Payment');
        }
        if ($EM_Booking->booking_status == 1) {
            $EM_Booking->add_note('Approved');
        }
        if ($EM_Booking->booking_status == 2) {
            $EM_Booking->add_note('Rejected');
        }
        if ($EM_Booking->booking_status == 6) {
            $EM_Booking->add_note('No Payment');
        }
        if ($EM_Booking->booking_status == 7) {
            $EM_Booking->add_note('Partially Paid');
        }
        return $result;
    }

    static function em_bookings_table($EM_Bookings_Table){
        $EM_Bookings_Table->statuses['no-payment'] = array('label'=>'No Payment', 'search'=>6);
        $EM_Bookings_Table->statuses['partially-paid'] = array('label'=>'Partially Paid', 'search'=>7);
        unset($EM_Bookings_Table->statuses['awaiting-online']);
        $EM_Bookings_Table->statuses['awaiting-payment'] = array('label'=>'Awaiting Payment', 'search'=>5);
        $EM_Bookings_Table->statuses['needs-attention']['search'] = array(0,5,7);
        $EM_Bookings_Table->status = ( !empty($_REQUEST['status']) && array_key_exists($_REQUEST['status'], $EM_Bookings_Table->statuses) ) ? $_REQUEST['status']:get_option('dbem_default_bookings_search','needs-attention');
    }
    
    public static function emlmd_hourly_hook() {
        // EM_Pro::log('emlmd_hourly_hook', 'general', true);
        $diffdays = intval(get_option('dbem_days_for_payment', '0'));
        if ($diffdays == 0) {
            return;
        }

        $events = EM_Events::get(array('scope'=>'future'));
        foreach ($events as $EM_Event) {
            $EM_Bookings = $EM_Event->get_bookings(true);
            foreach ($EM_Bookings->bookings as $EM_Booking) {
                if ($EM_Booking->booking_status == 5) {
                    // EM_Pro::log('#'.$EM_Booking->booking_id.' email='.$EM_Booking->get_person()->user_email, 'general', true);
                    $date1 = date_create();
                    $date2 = date_create();
                    $no_expiration = false;
                    $payment_reminder = false;
                    foreach ($EM_Booking->get_notes() as $note) {
                        if ($note['note'] == 'Awaiting Payment') {
                            date_timestamp_set($date1, $note['timestamp']);
                            $payment_reminder = false;
                            // EM_Pro::log('timestamp '. date(DATE_ATOM, $note['timestamp']), 'general', true);                    
                        }
                        if ($note['note'] == 'No Expiration') {
                            $no_expiration = true;
                        }
                        if ($note['note'] == 'Payment Reminder') {
                            $payment_reminder = true;
                        }
                    }
                    if ($no_expiration) {
                        continue;
                    }
                    $diffdays = intval(get_option('dbem_days_for_payment', '0'));
                    /*
                    $weekday = intval(date_format($date1, "w"));
                    if ($weekday > (4 - $diffdays)) {
                        $diffdays += 2;
                    }
                    */ 
                    $diff = date_diff($date1, $date2);

                    // EM_Pro::log('d='.$diff->d.' diffdays='.$diffdays.' payment_reminder='.$payment_reminder, 'general', true);

                    if ($diff->d >= $diffdays) {
                        if (EM_Limmud_Paypal::get_total_paid($EM_Booking) > 0) {
                            $EM_Booking->set_status(7);
                            EM_Pro::log('move to Partially Paid #'.$EM_Booking->booking_id.' email='.$EM_Booking->get_person()->user_email, 'general', true);
                        } else {
                            $EM_Booking->set_status(6);
                            EM_Pro::log('move to No Payment #'.$EM_Booking->booking_id.' email='.$EM_Booking->get_person()->user_email, 'general', true);
                        }
                    }

                    if (($diff->d == ($diffdays - 1)) && !$payment_reminder) {
                        $msg = array( 'user'=> array('subject'=>'', 'body'=>''), 'admin'=> array('subject'=>'', 'body'=>''));
                        $msg['user']['subject'] = get_option('dbem_bookings_email_payment_reminder_subject');
                        $msg['user']['body'] = get_option('dbem_bookings_email_payment_reminder_body');

                        $output_type = get_option('dbem_smtp_html') ? 'html':'email';
                        if (!empty($msg['user']['subject'])) {
                            $msg['user']['subject'] = $EM_Booking->output($msg['user']['subject'], 'raw');
                            $msg['user']['body'] = $EM_Booking->output($msg['user']['body'], $output_type);
                            $EM_Booking->email_send( $msg['user']['subject'], $msg['user']['body'], $EM_Booking->get_person()->user_email);
                        }
                        $EM_Booking->add_note("Payment Reminder");
                        EM_Pro::log('send payment reminder #'.$EM_Booking->booking_id.' email='.$EM_Booking->get_person()->user_email, 'general', true);
                    }
                }
            }
        }
    }

    public static function em_get_currencies($currencies) {
        $currencies->names['ILS'] = 'ILS - Israeli Shekel';
        $currencies->symbols['ILS'] = '&#8362;';
        $currencies->true_symbols['ILS'] = '₪';
        return $currencies;
    }
    
    public static function em_booking_calculate_price($price, $booking) {
        // round the price after calculation (we use prices less that $1 for tickets that collect information about participants)
        return floor($price);
    }
    
    public static function em_ticket_is_displayable($result, $ticket, $ignore_guest_restrictions = false, $ignore_member_restrictions = false) {
        // display only tickets that collect information about participants (i.e. have price less than $1)
        if (($ticket->ticket_price < 0) || ($ticket->ticket_price >= 1)) {
            $result = false;
        }
        return $result;
    }

    public static function em_booking_form_tickets_cols($columns, $EM_Event) { 
        // hide price - 'cause we are displaying tickets that collect information about participants
        unset($columns['price']);
        
        // change ticket type and spaces titles
        // $columns['type'] = "<h5>[:ru]Количество участников[:he]כמות משתתפים[:]</h5>";
        // $columns['spaces'] = "<h5>#</h5>";
        $columns['type'] = "";
        $columns['spaces'] = "";
        return $columns;
    }
    

    public static function em_booking_get_spaces($spaces, $obj) {
        // count only tickets that collect information about participants (i.e. have price less than $1)
        if (get_option('dbem_show_admin_tickets') == 'ignore') {
            if (get_class($obj) == 'EM_Tickets') {
                $spaces = 0;
                foreach( $obj->tickets as $EM_Ticket ){
                    if (($EM_Ticket->ticket_price >= 0) && ($EM_Ticket->ticket_price < 1)) {
                        $spaces += $EM_Ticket->get_spaces();
                    }
                }
            }
            if (get_class($obj) == 'EM_Tickets_Bookings') {
                $spaces = 0;
                foreach( $obj->tickets_bookings as $EM_Ticket_Booking) {
                    if (($EM_Ticket_Booking->get_ticket()->ticket_price >= 0) && ($EM_Ticket_Booking->get_ticket()->ticket_price < 1)) {
                        $spaces += $EM_Ticket_Booking->get_spaces();
                    }
                }
            }
            if (get_class($obj) == 'EM_Booking') {
                $spaces = $obj->get_tickets_bookings()->get_spaces();
            }
        }
        return $spaces;
    }

    public static function em_booking_get_person($EM_Person, $EM_Booking) {
        if ((($EM_Person->display_name == 'Guest User') || ($EM_Person->display_name == 'Гость') || ($EM_Person->display_name == 'משתמש אורח')) && isset($EM_Booking->booking_meta['attendees'])) {
            // calculate person name from attendee details of the most expensive 'person' ticket
            $price = -1;
            foreach ($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking ) {
                if (isset($EM_Booking->booking_meta['attendees'][$EM_Ticket_Booking->ticket_id][0]) &&
                    isset($EM_Booking->booking_meta['attendees'][$EM_Ticket_Booking->ticket_id][0]['attendee_first_name'])) {
                    if (($EM_Ticket_Booking->get_ticket()->ticket_price > $price) && ($EM_Ticket_Booking->get_ticket()->ticket_price < 1))
                    {
                        $price = $EM_Ticket_Booking->get_ticket()->ticket_price;
                        $EM_Person->first_name = $EM_Booking->booking_meta['attendees'][$EM_Ticket_Booking->ticket_id][0]['attendee_first_name'];
                        $EM_Person->last_name = $EM_Booking->booking_meta['attendees'][$EM_Ticket_Booking->ticket_id][0]['attendee_last_name'];
                        $EM_Person->display_name = $EM_Person->first_name . " " . $EM_Person->last_name;
                    }
                }
            }
        }
        return $EM_Person;
    }
    
    public static function em_get_booking($EM_Booking) {
        // allow non-admin users to manage bookings - so that we can add notes and update them from custom hooks 
        $EM_Booking->manage_override = true;
        return $EM_Booking;
    }
    
    public static function em_action_booking_add($return, $EM_Booking) {
        // redirect user to booking details page upon successful Booking submission
        if ($return['result'] && (get_option('dbem_automatic_payment') == 'enable')) {
            $my_booking_summary_page_id = get_option('dbem_booking_summary_page');
            if ($my_booking_summary_page_id != 0) {
                // $return['redirect'] = 'http://limmudfsu.org.il/site/booking-summary/?booking_id=' . $EM_Booking->booking_id;
                $return['redirect'] = get_post_permalink($my_booking_summary_page_id) . '&booking_id=' . $EM_Booking->booking_id . '&secret=' . md5($EM_Booking->person->user_email);
            }
        }
        return $return;
    }
}

EM_Limmud_Misc::init();
