<?php

class EM_Limmud_Misc {
    public static function init() {
        add_filter('manage_event_posts_columns', array(__CLASS__, 'show_edit_columns'), 11, 1);
        add_filter('em_bookings_table_cols_col_action', array(__CLASS__, 'em_bookings_table_cols_col_action'), 10, 2);
        add_action('em_booking', array(__CLASS__, 'em_booking'), 10, 2);
        add_filter('em_booking_set_status', array(__CLASS__, 'em_booking_set_status'), 10, 2);
        add_action('emlmd_hourly_hook', array(__CLASS__, 'emlmd_hourly_hook'));
        add_action('em_bookings_table',array(__CLASS__,'em_bookings_table'), 11, 1);
        add_filter('em_get_currencies', array(__CLASS__, 'em_get_currencies'), 10, 1);
        add_filter('em_booking_calculate_price', array(__CLASS__, 'em_booking_calculate_price'), 10, 2);
        add_filter('em_ticket_is_displayable', array(__CLASS__, 'em_ticket_is_displayable'), 10, 2);
        add_filter('em_booking_form_tickets_cols', array(__CLASS__, 'em_booking_form_tickets_cols'), 10, 2);
        add_filter('em_booking_get_spaces', array(__CLASS__, 'em_booking_get_spaces'), 10, 2);
        add_filter('em_booking_get_person', array(__CLASS__, 'em_booking_get_person'), 10, 2);
        add_filter('em_get_booking', array(__CLASS__, 'em_get_booking'), 10, 1);
        add_filter('em_action_booking_add', array(__CLASS__, 'em_action_booking_add'), 10, 2);
        add_action('em_events_admin_bookings_footer',array(__CLASS__, 'em_events_admin_bookings_footer'), 10, 0);
        add_action('em_event_save_meta_pre',array(__CLASS__, 'em_event_save_meta_pre'), 10, 1);
        add_action('em_booking_form_before_tickets',array(__CLASS__, 'em_booking_form_before_tickets'), 10, 1);
        add_filter('em_bookings_get_pending_spaces', array(__CLASS__, 'em_bookings_get_pending_spaces'), 2, 2);
        add_filter('em_booking_validate', array(__CLASS__, 'em_booking_validate'), 13, 2);
        add_action('em_bookings_single_metabox_footer',array(__CLASS__, 'em_bookings_single_metabox_footer'), 10, 1);
        add_action('em_booking_email_after_send',array(__CLASS__, 'em_booking_email_after_send'), 10, 1);
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
        if (($EM_Booking->booking_status == 6) || ($EM_Booking->booking_status == 7) || ($EM_Booking->booking_status == 8)) {
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
        $EM_Booking->status_array[8] = 'Waiting List';
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
        if ($EM_Booking->booking_status == 8) {
            $EM_Booking->add_note('Waiting List');
        }
        return $result;
    }

    static function em_bookings_table($EM_Bookings_Table){
        $EM_Bookings_Table->statuses['no-payment'] = array('label'=>'No Payment', 'search'=>6);
        $EM_Bookings_Table->statuses['partially-paid'] = array('label'=>'Partially Paid', 'search'=>7);
        $EM_Bookings_Table->statuses['waiting-list'] = array('label'=>'Waiting List', 'search'=>8);
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

                    $discount = false;
                    foreach($EM_Booking->get_tickets_bookings() as $EM_Ticket_Booking) {
                        if ($EM_Ticket_Booking->get_price() < 0) {
                            $discount = true;
                        }
                    }

                    if (($diff->d >= $diffdays) && !$discount) {
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
        // $columns['type'] = "<h5>[:ru]Количество участников[:he]כמות משתתפים/ות[:]</h5>";
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
                $return['redirect'] = get_post_permalink($my_booking_summary_page_id) . '&booking_id=' . $EM_Booking->booking_id . '&secret=' . md5($EM_Booking->person->user_email) . '&scroll=true';
            }
        }
        return $return;
    }

    public static function em_events_admin_bookings_footer(){
        global $EM_Event;
        $waiting_list = get_post_meta($EM_Event->post_id, '_waiting_list', true);
        $room_limit = get_post_meta($EM_Event->post_id, '_room_limit', true);
		?>
        <p>
            <label>Waiting List</label>
            <input type="text" name="waiting_list" size="30" value="<?php echo $waiting_list; ?>" /><br />
            <em>Defines amount of available rooms/spaces after which tickets will be moved to &quot;Waiting List&quot;. Syntax #1: comma-separated list of hotel_name=rooms_num, where hotel_name is partial &quot;hotel_name&quot; booking attribute - e.g. &quot;King Solomon=30,Club=50,Astoria=0&quot;. Syntax #2: limit number of bookings - &quot;bookings=50&quot;. Syntax #3: limit number of spaces - &quot;spaces=150&quot;. Syntax #4 (only for events with &quot;participation_type&quot; attribute): limit number of rooms / spaces without accomodation - &quot;rooms=70&quot; or &quot;no-accomodation=50&quot;. Leave blank for no limit.</em>
        </p>
        <p>
            <label>Room Limit</label>
            <input type="text" name="room_limit" size="30" value="<?php echo $room_limit; ?>" /><br />
            <em>Defines limit for specific room types. Syntax: comma-separated list of adults_num+children_num=limit - e.g. &quot;3+0=15,2+3=8&quot;. Leave blank for no limit.</em>
        </p>
		<?php
	}

    public static function em_event_save_meta_pre($EM_Event){
        if( !empty($EM_Event->duplicated) ) return; //if just duplicated, we ignore this and let EM carry over duplicate event data
        if( !empty($_REQUEST['waiting_list']) && (strlen($_REQUEST['waiting_list']) > 3)) {
            update_post_meta($EM_Event->post_id, '_waiting_list', $_REQUEST['waiting_list']);
        }
        else {
            update_post_meta($EM_Event->post_id, '_waiting_list', '');
        }
        if( !empty($_REQUEST['room_limit']) && (strlen($_REQUEST['room_limit']) > 3)) {
            update_post_meta($EM_Event->post_id, '_room_limit', $_REQUEST['room_limit']);
        }
        else {
            update_post_meta($EM_Event->post_id, '_room_limit', '');
        }
	}

    public static function check_waiting_list($EM_Event, $booking_hotel_name=NULL) {
        // check that event has enough spaces available - as per "waiting_list" meta-data variable
        // if $booking_hotel_name is not NULL - check rooms in specific hotel
        // return array(status, waiting_list_limits), where:
        //   status:
        //     0 - spaces are not available in all hotels
        //     1 - spaces are not available in $booking_hotel_name, but available in some other hotel
        //     2 - spaces are available
        //   waiting_list_limits: associative array of hotel names and remaining spaces

        $waiting_list_limits = array();
        $waiting_list = get_post_meta($EM_Event->post_id, '_waiting_list', true);
        if (empty($waiting_list) || (strlen($waiting_list) <= 3)) {
            return array(2, $waiting_list_limits);
        }

        // "waiting_list" meta-data variable supports the following syntaxes:
        //   - comma-separated list of hotel_name=rooms_available - e.g. "King Solomon=30,Club Hotel=50,Astoria=0"
        //     where:
        //       - hotel_name is unique, but possibly partial, hotel name - taken from "hotel_name" event variable
        //       - rooms_available is number of rooms available in specific hotel
        //   - limit on number of bookings - e.g. "bookings=50"
        //   - limit on number of spaces - e.g. "spaces=50"
        $EM_Bookings = $EM_Event->get_bookings();

        $waiting_list_array = explode(",", $waiting_list);
        foreach ($waiting_list_array as $waiting_list_str) {
            $waiting_list_data = explode("=", $waiting_list_str);
            if ((count($waiting_list_data) != 2) || !is_numeric($waiting_list_data[1]))
                continue;

            $key = $waiting_list_data[0];
            $value = intval($waiting_list_data[1]);

            $waiting_list_limits[$key] = $value;
        }

        if (empty($waiting_list_limits)) {
            return array(2, $waiting_list_limits);
        }

        foreach ($waiting_list_limits as $key => $value) {
            if ($key == "spaces") {
                $booked_spaces = $EM_Bookings->get_booked_spaces();
                if (get_option('dbem_bookings_approval_reserved')) {
                    $booked_spaces += $EM_Bookings->get_pending_spaces();
                }
                if ($booked_spaces >= $value) {
                    return array(0, $waiting_list_limits);
                }
                return array(2, $waiting_list_limits);
            }
        }

        foreach ($EM_Bookings->bookings as $EM_Booking) {
            switch ($EM_Booking->booking_status) {
                case 0:
                case 1:
                case 5:
                case 7:
                    $count = 1;
                    $hotel_name = '';
                    if (array_key_exists('hotel_name', $EM_Booking->booking_meta['booking'])) {
                        $hotel_name = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['hotel_name'], 'ru');
                    }
                    $limit_name = '';
                    if (array_key_exists('room_type', $EM_Booking->booking_meta['booking']) && array_key_exists('participation_type', $EM_Booking->booking_meta['booking'])) {
                        $participation_type = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['participation_type'], 'ru');
                        if ($participation_type == 'с проживанием') {
                            $limit_name = 'rooms';
                        } else {
                            $limit_name = 'no-accomodation';
                            $count = $EM_Booking->get_spaces();
                        }
                    }
                    foreach ($waiting_list_limits as $key => $value) {
                        if ((!empty($hotel_name) && str_contains($hotel_name, $key)) || (!empty($limit_name) && ($key == $limit_name)) || ($key == "bookings")) {
                            $waiting_list_limits[$key] = $value - $count;
                        }
                    }
                    break;
            }
        }

        $rooms_available = false;
        foreach ($waiting_list_limits as $key => $value) {
            if ($value > 0) {
                $rooms_available = true;
            }
        }
        if (!$rooms_available) {
            return array(0, $waiting_list_limits);
        }

        if (empty($booking_hotel_name)) {
            return array(2, $waiting_list_limits);
        }
            
        $booking_hotel_rooms_available = true;
        foreach ($waiting_list_limits as $key => $value) {
            if (str_contains($booking_hotel_name, $key) && ($value <= 0)) {
                $booking_hotel_rooms_available = false;
            }
        }
        if ($booking_hotel_rooms_available) {
            return array(2, $waiting_list_limits);
        } else {
            return array(1, $waiting_list_limits);
        }
    }

    public static function check_room_limit($EM_Event, $adult_num, $child_num) {
        // check that event has enough rooms of specific type available - as per "room_limit" meta-data variable
        // return status (bool)

        $room_name = strval($adult_num) . '+' . strval($child_num);

        $room_limit = get_post_meta($EM_Event->post_id, '_room_limit', true);
        if (empty($room_limit) || (strlen($room_limit) <= 3)) {
            return true;
        }

        // "room_limit" meta-data variable contains comma-separated list of <adult_num>+<child_num>=<limit>
        // e.g. "3+0=15,3+2=8"
        $room_limit_array = explode(",", $room_limit);
        foreach ($room_limit_array as $room_limit_str) {
            $room_limit_data = explode("=", $room_limit_str);
            if ((count($room_limit_data) != 2) || !is_numeric($room_limit_data[1]))
                continue;

            if ($room_limit_data[0] == $room_name) {
                $value = intval($room_limit_data[1]);

                $count = 0;
                $EM_Bookings = $EM_Event->get_bookings();
                foreach ($EM_Bookings->bookings as $EM_Booking) {
                    switch ($EM_Booking->booking_status) {
                        case 0:
                        case 1:
                        case 5:
                        case 7:
                            if (array_key_exists('room_type', $EM_Booking->booking_meta['booking'])) {
                                $participation_type == 'с проживанием';
                                if (array_key_exists('participation_type', $EM_Booking->booking_meta['booking'])) {
                                    $participation_type = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['participation_type'], 'ru');
                                }
                                if ($participation_type != 'без проживания') {
                                    EM_Limmud_Booking::calculate_participants($EM_Booking);
                                    if (($adult_num == EM_Limmud_Booking::$adult_num) && ($child_num == EM_Limmud_Booking::$child_num)) {
                                        $count++;
                                    }
                                }
                            }
                            break;
                    }
                }

                return ($count < $value);
            }
        }

        return true;
    }

    public static function em_booking_form_before_tickets($EM_Event) {
        list($waiting_list_status, $waiting_list_limits) = self::check_waiting_list($EM_Event);
        if ($waiting_list_status == 0) {
            ?>
            <div class="info">
                [:ru]В связи с большим количеством поступивших заявок, места с проживанием закончились. Вы можете записаться в лист ожидания и мы свяжемся с вами, если освободятся места.[:he]עקב ביקוש רב המקומות עם לינה אזלו. אתם/ן יכולים/ות להירשם לרשימת המתנה וניצור אתכם/ן קשר במידה והמקומות יתפנו.[:]
            </div>
            <?php
            return;
        }

        if (empty($waiting_list_limits)) {
            return;
        }

        /*
        // specific logic for Limmud 2022
        if (array_key_exists("King Solomon", $waiting_list_limits) && array_key_exists("Club", $waiting_list_limits) && array_key_exists("Astoria", $waiting_list_limits)) {
            if ($waiting_list_limits["King Solomon"] > 0) {
                if ($waiting_list_limits["Club"] > 0) {
                    if ($waiting_list_limits["Astoria"] > 0) {
                        ?>
                        <div id="hotels-solomon-club-astoria">
                            &nbsp;
                        </div>
                        <?php
                    } else {
                        ?>
                        <div id="hotels-solomon-club" class="info">
                            [:ru]В связи с большим количеством поступивших заявок, места в гостинице Astoria закончились. Вы можете заказать места в гостиницах King Solomon / המלך שלמה и Club Hotel.[:he]עקב ביקוש רב המקומות במלון אסטוריה אזלו. אתם יכולים להזמין מקומות במלונות המלך שלמה וקלאב הוטל.[:]
                        </div>
                        <?php
                    }
                } else {
                    if ($waiting_list_limits["Astoria"] > 0) {
                        ?>
                        <div id="hotels-solomon-astoria" class="info">
                            [:ru]В связи с большим количеством поступивших заявок, места в гостинице Club Hotel закончились. Вы можете заказать места в гостиницах King Solomon / המלך שלמה и Astoria.[:he]עקב ביקוש רב המקומות במלון קלאב הוטל אזלו. אתם יכולים להזמין מקומות במלונות המלך שלמה ואסטוריה.[:]
                        </div>
                        <?php
                    } else {
                        ?>
                        <div id="hotels-solomon" class="info">
                            [:ru]В связи с большим количеством поступивших заявок, места в гостиницах Club Hotel и Astoria закончились. Вы можете заказать места в гостинице King Solomon / המלך שלמה.[:he]עקב ביקוש רב המקומות במלונות קלאב הוטל ואסטוריה אזלו. אתם יכולים להזמין מקומות במלון המלך שלמה.[:]
                        </div>
                        <?php
                    }
                }
            } else {
                if ($waiting_list_limits["Club"] > 0) {
                    if ($waiting_list_limits["Astoria"] > 0) {
                        ?>
                        <div id="hotels-club-astoria" class="info">
                            [:ru]В связи с большим количеством поступивших заявок, места в гостинице King Solomon / המלך שלמה закончились. Вы можете заказать места в гостиницах Club Hotel и Astoria.[:he]עקב ביקוש רב המקומות במלון המלך שלמה אזלו. אתם יכולים להזמין מקומות במלונות קלאב הוטל ואסטוריה.[:]
                        </div>
                        <?php
                    } else {
                        ?>
                        <div id="hotels-club" class="info">
                            [:ru]В связи с большим количеством поступивших заявок, места в гостиницах King Solomon / המלך שלמה и Astoria закончились. Вы можете заказать места в гостинице Club Hotel.[:he]עקב ביקוש רב המקומות במלונות המלך שלמה ואסטוריה אזלו. אתם יכולים להזמין מקומות במלון קלאב הוטל.[:]
                        </div>
                        <?php
                    }
                } else {
                    if ($waiting_list_limits["Astoria"] > 0) {
                        ?>
                        <div id="hotels-astoria" class="info">
                            [:ru]В связи с большим количеством поступивших заявок, места в гостиницах King Solomon / המלך שלמה и Club Hotel закончились. Вы можете заказать места в гостинице Astoria.[:he]עקב ביקוש רב המקומות במלונות המלך שלמה וקלאב הוטל אזלו. אתם יכולים להזמין מקומות במלון אסטוריה.[:]
                        </div>
                        <?php
                    }
                }
            }
            return;
        }

        if (array_key_exists("King Solomon", $waiting_list_limits) && array_key_exists("Club", $waiting_list_limits)) {
            if ($waiting_list_limits["King Solomon"] > 0) {
                if ($waiting_list_limits["Club"] > 0) {
                    ?>
                    <div id="hotels-solomon-club">
                        &nbsp;
                    </div>
                    <?php
                } else {
                    ?>
                    <div id="hotels-solomon" class="info">
                        [:ru]В связи с большим количеством поступивших заявок, места в гостинице Club Hotel закончились. Вы можете заказать места в гостинице King Solomon / המלך שלמה.[:he]עקב ביקוש רב המקומות במלון קלאב הוטל אזלו. אתם יכולים להזמין מקומות במלון המלך שלמה.[:]
                    </div>
                    <?php
                }
            } else {
                if ($waiting_list_limits["Club"] > 0) {
                    ?>
                    <div id="hotels-club" class="info">
                        [:ru]В связи с большим количеством поступивших заявок, места в гостинице King Solomon / המלך שלמה закончились. Вы можете заказать места в гостинице Club Hotel.[:he]עקב ביקוש רב המקומות במלון המלך שלמה אזלו. אתם יכולים להזמין מקומות במלון קלאב הוטל.[:]
                    </div>
                    <?php
                }
            }
            return;
        }
        */
    }

    public static function em_booking_validate($result, $EM_Booking) {
        if (array_key_exists('additional_emails', $EM_Booking->booking_meta['booking'])) {
            $additional_emails = $EM_Booking->booking_meta['booking']['additional_emails'];
            $additional_emails = preg_replace('/[\s,]+/', ' ', $additional_emails);
            $additional_emails = trim($additional_emails);
            $additional_emails_array = explode(' ', $additional_emails);
            foreach ($additional_emails_array as $additional_email) {
                if (!empty($additional_email) && !is_email($additional_email)) {
                    $EM_Booking->add_error(__('[:ru]Неправильный дополнительный Email адрес[:he]כתובת דוא"ל נוספת לא חוקית[:]') . ' ' . $additional_email);
                    $result = false;
                }
            }
        }

        if (!array_key_exists('room_type', $EM_Booking->booking_meta['booking'])) {
            return $result;
        }

        if (array_key_exists('participation_type', $EM_Booking->booking_meta['booking'])) {
            $participation_type = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['participation_type'], 'ru');
            if ($participation_type == 'без проживания') {
                return $result;
            }
        }

        if (array_key_exists('hotel_name', $EM_Booking->booking_meta['booking'])) {
            $hotel_name = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['hotel_name'], 'ru');
            if (empty($EM_Booking->booking_status)) {
                list($waiting_list_status, $waiting_list_limits) = self::check_waiting_list($EM_Booking->get_event(), $hotel_name);
                if ($waiting_list_status == 1) {
                    $EM_Booking->add_error(__('[:ru]Места в выбранной гостинице закончились - выберите другую гостиницу[:he]במלון הנבחר נגמרו מקומות - נא לבחור מלון אחר[:]'));
                    $result = false;
                }
            }
        }

        EM_Limmud_Booking::calculate_participants($EM_Booking);
        $adult_num = EM_Limmud_Booking::$adult_num;
        $child_num = EM_Limmud_Booking::$child_num;
        if (!self::check_room_limit($EM_Booking->get_event(), $adult_num, $child_num)) {
            if ($child_num > 0) {
                $EM_Booking->add_error(__('[:ru]Номера для ' . strval($adult_num) . ' взрослых и ' . strval($child_num) . ' детей закончились[:he]חדרים ל-' . strval($adult_num) . ' מבוגרים/ות ו-' . strval($child_num) . ' ילדים/ות נגמרו[:]'));
            } else {
                if ($adult_num == 1) {
                    $EM_Booking->add_error(__('[:ru]Одноместные номера закончились[:he]חדרים ליחידים נגמרו[:]'));
                } elseif ($adult_num == 2) {
                    $EM_Booking->add_error(__('[:ru]Двухместные номера закончились[:he]חדרים זוגיים נגמרו[:]'));
                } elseif ($adult_num == 3) {
                    $EM_Booking->add_error(__('[:ru]Трехместные номера закончились[:he]חדרים לשלוש/ה מבוגרים/ות נגמרו[:]'));
                } else {
                    $EM_Booking->add_error(__('[:ru]Номера для ' . strval($adult_num) . ' взрослых закончились[:he]חדרים ל-' . strval($adult_num) . ' מבוגרים/ות נגמרו[:]'));
                }
            }
            $result = false;
        }
        return $result;    
    }

    public static function em_bookings_get_pending_spaces($count, $EM_Bookings) {
        $EM_Event = $EM_Bookings->get_event();
        foreach ($EM_Bookings->bookings as $EM_Booking) {
            // Awaiting Payment (booking_status == 5) bookings are appended by Events Manager Pro gateway_offline class
            if (($EM_Booking->booking_status == 7) || ($EM_Booking->booking_status == 8)) {
                $count += $EM_Booking->get_spaces();
            }
        }
		return $count;
	}

    public static function em_bookings_single_metabox_footer($EM_Booking) {
        ?>
        <div id="em-booking-link" class="postbox">
            <h3>
                <?php esc_html_e( 'Payment Link', 'events-limmud'); ?>
            </h3>
            <div class="inside">
                <a href="<?php echo EM_Limmud_Booking::get_payment_link($EM_Booking); ?>"><?php echo EM_Limmud_Booking::get_payment_link($EM_Booking); ?></a>
            </div>
        </div>
        <?php
    }

    public static function em_booking_email_after_send($EM_Booking) {
        if (!array_key_exists('additional_emails', $EM_Booking->booking_meta['booking'])) {
            return;
        }

        $additional_emails = $EM_Booking->booking_meta['booking']['additional_emails'];
        $additional_emails = preg_replace('/[\s,]+/', ' ', $additional_emails);
        $additional_emails = trim($additional_emails);
        $additional_emails_array = explode(' ', $additional_emails);

        $msg = $EM_Booking->email_messages();
        if( !empty($msg['user']['subject']) ) {
            $msg['user']['subject'] = $EM_Booking->output($msg['user']['subject'], 'raw');
            $msg['user']['body'] = $EM_Booking->output($msg['user']['body'], 'email');
            $attachments = array();
            if( !empty($msg['user']['attachments']) && is_array($msg['user']['attachments']) ) {
                $attachments = $msg['user']['attachments'];
            }
            foreach ($additional_emails_array as $additional_email) {
                if (!empty($additional_email)) {
                    // ignore failures in email_send
                    $EM_Booking->email_send( $msg['user']['subject'], $msg['user']['body'], $additional_email, $attachments );
                }
            }
        }
    }
}

EM_Limmud_Misc::init();
