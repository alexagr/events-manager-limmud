<?php
class EM_Limmud_Emails {

    public static function init() {
        add_filter('em_booking_email_messages', array(__CLASS__, 'email_messages'), 100, 2);
        add_filter('em_booking_output_placeholder',array(__CLASS__,'placeholders'),2,3);
    }

    public static function email_messages( $msg, $EM_Booking ) {
        if ($EM_Booking->booking_status == 5) {
            $msg['user']['subject'] = get_option('dbem_bookings_email_awaiting_payment_subject');
            $msg['user']['body'] = get_option('dbem_bookings_email_awaiting_payment_body');
            //admins should get something (if set to)
            $msg['admin']['subject'] = get_option('dbem_bookings_contact_email_awaiting_payment_subject');
            $msg['admin']['body'] = get_option('dbem_bookings_contact_email_awaiting_payment_body');
        }
        if ($EM_Booking->booking_status == 6) {
            $msg['user']['subject'] = get_option('dbem_bookings_email_no_payment_subject');
            $msg['user']['body'] = get_option('dbem_bookings_email_no_payment_body');
            //admins should get something (if set to)
            $msg['admin']['subject'] = get_option('dbem_bookings_contact_email_no_payment_subject');
            $msg['admin']['body'] = get_option('dbem_bookings_contact_email_no_payment_body');
        }
        if ($EM_Booking->booking_status == 7) {
            $msg['user']['subject'] = get_option('dbem_bookings_email_partially_paid_subject');
            $msg['user']['body'] = get_option('dbem_bookings_email_partially_paid_body');
            //admins should get something (if set to)
            $msg['admin']['subject'] = get_option('dbem_bookings_contact_email_partially_paid_subject');
            $msg['admin']['body'] = get_option('dbem_bookings_contact_email_partially_paid_body');
        }
        if ($EM_Booking->booking_status == 8) {
            $msg['user']['subject'] = get_option('dbem_bookings_email_waiting_list_subject');
            $msg['user']['body'] = get_option('dbem_bookings_email_waiting_list_body');
            //admins should get something (if set to)
            $msg['admin']['subject'] = get_option('dbem_bookings_contact_email_waiting_list_subject');
            $msg['admin']['body'] = get_option('dbem_bookings_contact_email_waiting_list_body');
        }
        // event-specific content of admin mails
        while (preg_match('/^@EVENT_(\d+)@(.+?)$/m', $msg['admin']['body'], $matches)) {
            if ($matches[1] == $EM_Booking->get_event()->event_id) {
                $msg['admin']['body'] = str_replace($matches[0], $matches[2], $msg['admin']['body']);
            } else {
                $msg['admin']['body'] = str_replace($matches[0] . "\n", '', $msg['admin']['body']);
            }
        }
        // event-specific content of user mails
        while (preg_match('/^@EVENT_(\d+)@(.+?)$/m', $msg['user']['body'], $matches)) {
            if ($matches[1] == $EM_Booking->get_event()->event_id) {
                $msg['user']['body'] = str_replace($matches[0], $matches[2], $msg['user']['body']);
            } else {
                $msg['user']['body'] = str_replace($matches[0] . "\n", '', $msg['user']['body']);
            }
        }
        return $msg;
    }

    public static function booking_details($EM_Booking, $full_result, $lang) {
        $replace = '';
        
        $tickets = array();
        foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
            if (($EM_Ticket_Booking->get_price() >= 0) && ($EM_Ticket_Booking->get_price() < 1)) {
                $tickets[$EM_Ticket_Booking->get_price() * 1000] = $EM_Ticket_Booking;
            }
        }
        krsort($tickets);
        foreach($tickets as $price => $ticket) {
            $replace = $replace . apply_filters('translate_text', $ticket->get_ticket()->name, $lang) . " : " . $ticket->get_spaces() . "\n";
            if (!empty($EM_Booking->booking_meta['attendees'][$ticket->ticket_id]) && is_array($EM_Booking->booking_meta['attendees'][$ticket->ticket_id])) {
                $i = 1; //counter
                $EM_Form = EM_Attendees_Form::get_form($EM_Booking->event_id);
                foreach ($EM_Booking->booking_meta['attendees'][$ticket->ticket_id] as $field_values) {
                    $replace = $replace . "&nbsp;&nbsp;&nbsp;&nbsp;#" . $i . "\n";
                    foreach ($EM_Form->form_fields as $fieldid => $field) {
                        if (!array_key_exists($fieldid, $EM_Form->user_fields) && $field['type'] != 'html') {
                            if (isset($field_values[$fieldid])) {
                                // do not include security sensitive data in e-mails
                                if ((strpos(apply_filters('translate_text', $field['label'], 'ru'), "Теудат зеут") !== false) ||
                                    (strpos(apply_filters('translate_text', $field['label'], 'ru'), "Гражданин Израиля") !== false)) {
                                   continue;
                                }
                                $replace = $replace . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . apply_filters('translate_text', $field['label'], $lang) . " : " . apply_filters('translate_text', $field_values[$fieldid], $lang) . "\n";
                            }
                        }
                    }
                    $i++;
                }
            }
        }
        $replace = $replace . "\n";
        if ($lang == 'ru') {
            $replace = $replace . "E-mail : " . $EM_Booking->get_person()->user_email . "\n";
            $replace = $replace . "Телефон : " . $EM_Booking->get_person()->phone . "\n";
        } else {
            $replace = $replace . "דוא&quot;ל : " . $EM_Booking->get_person()->user_email . "\n";
            $replace = $replace . "טלפון : " . $EM_Booking->get_person()->phone . "\n";
        }
        // do not include security sensitive data in e-mails
        /*
        if (!empty($EM_Booking->booking_meta['registration']['dbem_address'])) {
            if ($lang == 'ru') {
                $replace = $replace . "Адрес : " . $EM_Booking->booking_meta['registration']['dbem_address'] . "\n";
            } else {
                $replace = $replace . "כתובת : " . $EM_Booking->booking_meta['registration']['dbem_address'] . "\n";
            }
        }
        if (!empty($EM_Booking->booking_meta['registration']['dbem_city'])) {
            if ($lang == 'ru') {
                $replace = $replace . "Город : " . $EM_Booking->booking_meta['registration']['dbem_city'] . "\n";
            } else {
                $replace = $replace . "עיר : " . $EM_Booking->booking_meta['registration']['dbem_city'] . "\n";
            }
        }
        */
        $replace = $replace . "\n";
        if (!empty($EM_Booking->booking_meta['booking'])) {
            $EM_Form = EM_Booking_Form::get_form($EM_Booking->event_id);
            foreach ($EM_Form->form_fields as $fieldid => $field) {
                if (($field['type'] != 'html') && ($field['type'] != 'checkbox') && isset($EM_Booking->booking_meta['booking'][$fieldid]) && ($EM_Booking->booking_meta['booking'][$fieldid] != 'n/a') && ($EM_Booking->booking_meta['booking'][$fieldid] != 'N/A')) {
                    $replace = $replace . apply_filters('translate_text', $field['label'], $lang) . " : " . apply_filters('translate_text', $EM_Booking->booking_meta['booking'][$fieldid], $lang) . "\n";
                }                        
            }
        }
        return $replace;
    }
    
    public static function payment_details($EM_Booking, $full_result, $lang) {
        $discount = $EM_Booking->get_price_discounts_amount('post');

        $participants = array();
        $tickets = array();
        $i = 0;
        foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
            $i += 1;

            if (($EM_Ticket_Booking->get_price() >= 0) && ($EM_Ticket_Booking->get_price() < 1)) {
                $participants[$EM_Ticket_Booking->get_price() * 1000 + $i] = apply_filters('translate_text', $EM_Ticket_Booking->get_ticket()->name, $lang) . " : " . $EM_Ticket_Booking->get_spaces() . "\n"; 
            }
            else if ($EM_Ticket_Booking->get_price() >= 1) {
                $price = $EM_Ticket_Booking->get_price();
                $price = floor($price);
                if ($EM_Ticket_Booking->get_spaces() == 1) {
                    $ticket_price = $price;
                } else {
                    $ticket_price = $EM_Ticket_Booking->get_spaces() . ' * ' . floor($EM_Ticket_Booking->get_ticket()->get_price_without_tax()) . ' = ' . $price;
                } 
                $tickets[$EM_Ticket_Booking->get_price() * 1000 + $i] = apply_filters('translate_text', $EM_Ticket_Booking->get_ticket()->name, $lang) . " : " . $ticket_price . " &#8362;\n"; 
            } else if ($EM_Ticket_Booking->get_price() < 0) {
                $discount += -$EM_Ticket_Booking->get_price();
            }
        }
        krsort($participants);
        krsort($tickets);

        if ($lang == 'ru') {
            $replace = "<u>КОЛИЧЕСТВО УЧАСТНИКОВ</u>\n\n";
        } else {
            $replace = "<u>כמות משתתפים</u>\n\n";
        }
        foreach ($participants as $price => $descr) {
            $replace = $replace . $descr;
        }

        if ($EM_Booking->get_price() >= 1) {
            $replace = $replace . "\n\n";
            if ($lang == 'ru') {
                $replace = $replace . "<u>СТОИМОСТЬ УЧАСТИЯ</u>\n\n";
            } else {
                $replace = $replace . "<u>מחיר השתתפות</u>\n\n";
            }

		    $tickets_num = 0;
	        foreach ($tickets as $price => $descr) {
	            $tickets_num += 1;
	            $replace = $replace . $descr;
	        }

            if ($discount > 0) {
                if ($lang == 'ru') {
                    $replace = $replace . "Скидка : -" . $discount . " &#8362;\n";
                } else {
                    $replace = $replace . "הנחה : -" . $discount . " &#8362;\n";
                }
            }
            $price = $EM_Booking->get_price();
            $price = floor($price);
            // $price = $EM_Booking->format_price($price);                
	        if( ($tickets_num > 1) || ($discount > 0) ) {
	            $replace = $replace . "--------------\n";
	            if ($lang == 'ru') {
	                $replace = $replace . "Итого : " . $price . " &#8362;\n\n";
	            } else {
	                $replace = $replace . "סה&quot;כ : " . $price . " &#8362;\n\n";
	            }
	        }
        }
        return $replace;
    }
    
    public static function placeholders($replace, $EM_Booking, $full_result){
        if (empty($replace) || $replace == $full_result) {
            if ($full_result == '#_BOOKINGSUMMARYURL') {
                $replace = EM_Limmud_Paypal::get_payment_link($EM_Booking);
            }
            
            if ($full_result == '#_BOOKINGDETAILSRU') {
                $replace = EM_Limmud_Emails::booking_details($EM_Booking, $full_result, 'ru');
            }

            if ($full_result == '#_BOOKINGDETAILSHE') {
                $replace = EM_Limmud_Emails::booking_details($EM_Booking, $full_result, 'he');
            }
            
            if ($full_result == '#_BOOKINGPRICEROUNDED') {
                $replace = '';
                $price = $EM_Booking->get_price();
                $price = floor($price);
                $replace = $price;
                # $replace = $EM_Booking->format_price($price);                
            }

            if ($full_result == '#_BOOKINGSUMMARYPAYMENTRU') {
                $replace = EM_Limmud_Emails::payment_details($EM_Booking, $full_result, 'ru');
            }

            if ($full_result == '#_BOOKINGSUMMARYPAYMENTHE') {
                $replace = EM_Limmud_Emails::payment_details($EM_Booking, $full_result, 'he');
            }

            if ($full_result == '#_EVENTYEAR') {
                $replace = $EM_Booking->get_event()->start()->format('Y');
            }
        }
        return $replace;
    }   
}

EM_Limmud_Emails::init();