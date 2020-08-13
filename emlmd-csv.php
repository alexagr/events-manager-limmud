<?php

/**
 * Limmud-specific export to CSV
 */

class EM_Limmud_CSV {
    public static function init() {
        add_action('init', array(__CLASS__, 'intercept_csv_export'), 10);
        add_action('em_bookings_table_export_options', array(__CLASS__, 'em_bookings_table_export_options'), 11);
    }

    public static function em_bookings_table_export_options() {
        ?>
        <hr />
        <h4>Limmud 2019 reports</h4>
        <p>There reports collect data from all relevant events and use pre-defined formats. Generic split and columns to export settings are irrelevant.</p> 
        <p>Full bookings report <input type="checkbox" name="limmud_export" value="1" />
        <a href="#" title="Complete information about all bookings (including cancelled)">?</a></p>
        <p>Accomodation report <input type="checkbox" name="limmud_accomodation" value="1" />
        <a href="#" title="Accomodation report (only approved and pending bookings)">?</a></p>
        <p>Transport report <input type="checkbox" name="limmud_transport" value="1" />
        <a href="#" title="Transport report (only approved and pending bookings)">?</a></p>
        <hr />
        <?php
    }

    public static function intercept_csv_export() {
        if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'export_bookings_csv' && !empty($_REQUEST['limmud_export']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'export_bookings_csv')) {

            header("Content-Type: application/octet-stream; charset=utf-8");
            header("Content-Disposition: Attachment; filename=".sanitize_title(get_bloginfo())."-bookings-export.csv");
            do_action('em_csv_header_output');
            echo "\xEF\xBB\xBF"; // UTF-8 for MS Excel (a little hacky... but does the job)

            $handle = fopen("php://output", "w");
            $delimiter = !defined('EM_CSV_DELIMITER') ? ',' : EM_CSV_DELIMITER;
            $delimiter = apply_filters('em_csv_delimiter', $delimiter);

            $headers = array('secret_code', 'id', 'name', 'email', 'status', 'event_id', 'ticket_name', 'ticket_price', 'phone', 'address', 'city', 'room_type', 'bus_needed', 'ticket_type', 'special_needs', 'comment', 'book_type', 'keep_sabbath', 'child_program', 'first_name', 'last_name', 'birthday', 'israeli', 'passport', 'role');
            fputcsv($handle, $headers, $delimiter);

            $events = EM_Events::get(array('scope'=>'all'));
            foreach ($events as $EM_Event) {
                if (($EM_Event->event_id != 10) && ($EM_Event->event_id != 11) && ($EM_Event->event_id != 12)) {
                    continue;
                }
                foreach ($EM_Event->get_bookings()->bookings as $EM_Booking) {
                    $attendees_data = EM_Attendees_Form::get_booking_attendees($EM_Booking);
                    foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
                        $EM_Form = EM_Booking_Form::get_form($event_id, $EM_Booking);
                        $row = array();
                        if ($EM_Event->event_id == 12) {
                            $row[] = $EM_Form->get_formatted_value($EM_Form->form_fields['secret_code'], $EM_Booking->booking_meta['booking']['secret_code']);
                        } else {
                            $row[] = '';
                        }
                        $row[] = $EM_Booking->booking_id;
                        $row[] = $EM_Booking->get_person()->get_name();
                        $row[] = $EM_Booking->get_person()->user_email;
                        $row[] = $EM_Booking->get_status(true);
                        $row[] = $EM_Booking->get_event()->event_id;
                        $row[] = apply_filters('translate_text', $EM_Ticket_Booking->get_ticket()->ticket_name, 'ru');
                        $row[] = $EM_Ticket_Booking->get_ticket()->get_price(true);
                        $row[] = $EM_Booking->get_person()->phone;
                        $row[] = $EM_Booking->booking_meta['registration']['dbem_address'];
                        $row[] = $EM_Booking->booking_meta['registration']['dbem_city'];

                        $event_id = $EM_Booking->get_event()->event_id;
                        if ($EM_Event->event_id == 11) {
                            // self accomodation
                            $row[] = 'N/A';
                            $row[] = 'N/A';
                            $row[] = apply_filters('translate_text', $EM_Form->get_formatted_value($EM_Form->form_fields['ticket_type'], $EM_Booking->booking_meta['booking']['ticket_type']), 'ru');
                        } elseif (($EM_Event->event_id == 10) || ($EM_Event->event_id == 12)) {
                            $row[] = apply_filters('translate_text', $EM_Form->get_formatted_value($EM_Form->form_fields['room_type'], $EM_Booking->booking_meta['booking']['room_type']), 'ru');
                            $row[] = apply_filters('translate_text', $EM_Form->get_formatted_value($EM_Form->form_fields['bus_needed'], $EM_Booking->booking_meta['booking']['bus_needed']), 'ru');
                            $row[] = 'N/A';
                        }
                        $row[] = $EM_Form->get_formatted_value($EM_Form->form_fields['special_needs'], $EM_Booking->booking_meta['booking']['special_needs']);
                        $row[] = $EM_Form->get_formatted_value($EM_Form->form_fields['dbem_comment'], $EM_Booking->booking_meta['booking']['dbem_comment']);
                        $row[] = apply_filters('translate_text', $EM_Form->get_formatted_value($EM_Form->form_fields['book_type'], $EM_Booking->booking_meta['booking']['book_type']), 'ru');
                        $row[] = apply_filters('translate_text', $EM_Form->get_formatted_value($EM_Form->form_fields['keep_sabbath'], $EM_Booking->booking_meta['booking']['keep_sabbath']), 'ru');
                        $row[] = apply_filters('translate_text', $EM_Form->get_formatted_value($EM_Form->form_fields['child_program'], $EM_Booking->booking_meta['booking']['child_program']), 'ru');
                        
                        if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
                            foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
                                $full_row = $row;
                                foreach( $attendee_data as $field_value) {
                                    $value = apply_filters('translate_text', $field_value, 'ru');
                                    if (($value == 'мужской') || ($value == 'женский')) {
                                        continue;
                                    }
                                    if ($field_value != 'n/a') {
                                        $full_row[] = apply_filters('translate_text', $field_value, 'ru');
                                    } else {
                                        $full_row[] = '';
                                    }
                                }
                                fputcsv($handle, $full_row, $delimiter);
                            }
                        }
                    }
                }
            }
            fclose($handle);
            exit();
        }

        if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'export_bookings_csv' && !empty($_REQUEST['limmud_accomodation']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'export_bookings_csv')) {

            header("Content-Type: application/octet-stream; charset=utf-8");
            header("Content-Disposition: Attachment; filename=".sanitize_title(get_bloginfo())."-bookings-accomodation.csv");
            do_action('em_csv_header_output');
            echo "\xEF\xBB\xBF"; // UTF-8 for MS Excel (a little hacky... but does the job)

            $handle = fopen("php://output", "w");
            $delimiter = !defined('EM_CSV_DELIMITER') ? ',' : EM_CSV_DELIMITER;
            $delimiter = apply_filters('em_csv_delimiter', $delimiter);

            $headers = array('event', 'ticket_name', 'order#', 'name', 'surname', 'birthday', 'role', 'ticket_type', 'special_needs', 'comment', 'status', 'book', 'keep_sabbath', 'child_program', 'secret', 'meal');
            fputcsv($handle, $headers, $delimiter);

            $orders = array();

            $events = EM_Events::get(array('scope'=>'all'));
            foreach ($events as $EM_Event) {
                if (($EM_Event->event_id != 10) && ($EM_Event->event_id != 11) && ($EM_Event->event_id != 12)) {
                    continue;
                }
                foreach ($EM_Event->get_bookings()->bookings as $EM_Booking) {
                    $order = array();
                    $order['event'] = $EM_Event->event_id;
                    $order['id'] = $EM_Booking->booking_id;
                    $order['person'] = $EM_Booking->get_person()->get_name();
                    $order['email'] = $EM_Booking->get_person()->user_email;
                    $order['status'] = $EM_Booking->get_status(true);
                    $order['secret'] = $EM_Booking->booking_meta['booking']['secret_code'];
                    if (($order['status'] != 'Approved') && ($order['status'] != 'Awaiting Payment')) {
                        continue;
                    }
                    $order['adults'] = array();
                    $order['children'] = array();
                    $order['toddlers'] = array();
                    $order['tickets'] = array();

                    $event_id = $EM_Booking->get_event()->event_id;
                    $EM_Form = EM_Booking_Form::get_form($event_id, $EM_Booking);
                    $order['special_needs'] = $EM_Form->get_formatted_value($EM_Form->form_fields['special_needs'], $EM_Booking->booking_meta['booking']['special_needs']);
                    $order['comment'] = $EM_Form->get_formatted_value($EM_Form->form_fields['dbem_comment'], $EM_Booking->booking_meta['booking']['dbem_comment']);
                    $order['book'] = apply_filters('translate_text', $EM_Form->get_formatted_value($EM_Form->form_fields['book_type'], $EM_Booking->booking_meta['booking']['book_type']), 'ru');
                    $order['keep_sabbath'] = apply_filters('translate_text', $EM_Form->get_formatted_value($EM_Form->form_fields['keep_sabbath'], $EM_Booking->booking_meta['booking']['keep_sabbath']), 'ru');
                    $order['child_program'] = apply_filters('translate_text', $EM_Form->get_formatted_value($EM_Form->form_fields['child_program'], $EM_Booking->booking_meta['booking']['child_program']), 'ru');
                    $order['children_num'] = 0;
                    $order['toddlers_num'] = 0;
                    $order['meal'] = 2;

                    if (strpos($order['comment'], 'MEAL#1') !== false) {
                        $order['meal'] = 1;
                    }

                    // populate arrays from tickets and attendees data
                    $attendees_data = EM_Attendees_Form::get_booking_attendees($EM_Booking);
                    foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
                        $ticket_name = apply_filters('translate_text', $EM_Ticket_Booking->get_ticket()->ticket_name, 'ru');  

                        $people = array();
                        if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
                            foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
                                $person = array();
                                $i = 0;
                                $person['role'] = 'n/a';
                                foreach( $attendee_data as $field_value) {
                                    if ($i == 0) {
                                        $person['role'] = 'участник';
                                        $person['name'] = $field_value; 
                                    }
                                    if ($i == 1) {
                                        $person['surname'] = $field_value; 
                                    }
                                    if ($i == 2) {
                                        $person['birthday'] = $field_value; 
                                    }
                                    if ((($EM_Event->event_id == 10) || ($EM_Event->event_id == 11)) && ($i == 3)) {
                                        $person['birthday'] = $field_value; 
                                    }
                                    if (($EM_Event->event_id == 12) && ($i == 5)) {
                                        $person['role'] = apply_filters('translate_text', $field_value, 'ru');
                                    }
                                    $i++;
                                }
                                if ($ticket_name == 'Дети (от 3 до 12 лет)') {
                                    $order['meal'] = 1;
                                    $person['role'] = 'ребенок';
                                }
                                if ($ticket_name == 'Младенцы (до 3 лет)') {
                                    $order['meal'] = 1;
                                    $person['role'] = 'младенец';
                                }
                                $people[] = $person;
                            }
                        }

                        if ($ticket_name == 'Взрослые и подростки (старше 12 лет)') {
                            $order['adults'] = array_merge($order['adults'], $people);
                        }

                        if ($ticket_name == 'Дети (от 3 до 12 лет)') {
                            if (strpos($order['comment'], 'CHILD#ADULT') !== false) {
                                $order['adults'] = array_merge($order['adults'], $people);
                            } else {
                                $order['children'] = array_merge($order['children'], $people);
                            }
                        }

                        if ($ticket_name == 'Младенцы (до 3 лет)') {
                            $order['toddlers'] = array_merge($order['toddlers'], $people);
                        }

                        if ((strpos($ticket_name, 'Adult') === 0) ||
                            (strpos($ticket_name, 'Child') === 0) ||
                            (strpos($ticket_name, 'No accomodation') === 0) ||
                            (strpos($ticket_name, 'Ticket') === 0)) {
                            $ticket_data = array();
                            $ticket_data['name'] = $ticket_name;
                            if ($ticket_name == 'Ticket 1 Day') {
                                $ticket_data['ticket_type'] = apply_filters('translate_text', $EM_Form->get_formatted_value($EM_Form->form_fields['ticket_type'], $EM_Booking->booking_meta['booking']['ticket_type']), 'ru');;
                            } else {
                                $ticket_data['ticket_type'] = '';
                            }
                            
                            $ticket_data['people'] = array();
                            for ($i = 0; $i < $EM_Ticket_Booking->get_spaces(); $i++) {
                                $order['tickets'][] = $ticket_data;
                            }
                        }
                    }

                    // populate tickets
                    $adult_id = 0;
                    $child_id = 0;
                    foreach($order['tickets'] as $key => $ticket) {
                        if (strpos($ticket['name'], 'Adult') === 0) {
                            $order['tickets'][$key]['people'][] = $order['adults'][$adult_id++];
                        } elseif (strpos($ticket['name'], 'Child') === 0) {
                            $order['tickets'][$key]['people'][] = $order['children'][$child_id++];
                        } elseif ((strpos($ticket['name'], 'No accomodation') === 0) || (strpos($ticket['name'], 'Ticket') === 0)) {
                            if ($adult_id < count($order['adults'])) {
                                $order['tickets'][$key]['people'][] = $order['adults'][$adult_id++];
                            } else {
                                $order['tickets'][$key]['people'][] = $order['children'][$child_id++];
                            }
                        }
                    }
                    
                    // verify that the order has tickets for all people
                    if (($adult_id < count($order['adults'])) || ($child_id < count($order['children']))) {
                        $ticket_data = array();
                        $ticket_data['name'] = "Not enough tickets";
                        $ticket_data['ticket_type'] = "";
                        $ticket_data['people'] = array();
                        $person = array();
                        $person['name'] = '';
                        $person['surname'] = '';
                        $person['birthday'] = '';
                        $person['role'] = '';
                        $ticket_data['people'][] = $person;
                        $order['tickets'][] = $ticket_data;
                    }

                    if (count($order['toddlers']) >= 1) {
                        $ticket_data = array();
                        $ticket_data['name'] = 'Toddler';
                        $ticket_data['ticket_type'] = "";
                        $ticket_data['people'] = array();
                        foreach($order['toddlers'] as $toddler) {
                            $ticket_data['people'][] = $toddler;
                        }
                        $order['tickets'][] = $ticket_data;
                    }

                    $orders[$order['id']] = $order;
                }
            }

            ksort($orders);

            foreach($orders as $order) {
               foreach($order['tickets'] as $ticket) {
                   foreach($ticket['people'] as $person) {
                       $row = array();
                       $row[] = $order['event'];
                       $row[] = $ticket['name'];
                       $row[] = $order['id'];
                       $row[] = $person['name'];
                       $row[] = $person['surname'];
                       $row[] = $person['birthday'];
                       $row[] = $person['role'];
                       $row[] = $ticket['ticket_type'];
                       $row[] = $order['special_needs'];
                       $row[] = $order['comment'];
                       $row[] = $order['status'];
                       $row[] = $order['book'];
                       $row[] = $order['keep_sabbath'];
                       $row[] = $order['child_program'];
                       $row[] = $order['secret'];
                       if ((strpos($ticket['name'], 'No accomodation') === false) && (strpos($ticket['name'], 'Ticket') === false)) {
                           $row[] = $order['meal'];
                       } else {
                           $row[] = '';
                       }
                       fputcsv($handle, $row, $delimiter);
                   }
               }
            }

            fclose($handle);
            exit();
        }

        if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'export_bookings_csv' && !empty($_REQUEST['limmud_transport']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'export_bookings_csv')) {

            header("Content-Type: application/octet-stream; charset=utf-8");
            header("Content-Disposition: Attachment; filename=".sanitize_title(get_bloginfo())."-bookings-transport.csv");
            do_action('em_csv_header_output');
            echo "\xEF\xBB\xBF"; // UTF-8 for MS Excel (a little hacky... but does the job)

            $handle = fopen("php://output", "w");
            $delimiter = !defined('EM_CSV_DELIMITER') ? ',' : EM_CSV_DELIMITER;
            $delimiter = apply_filters('em_csv_delimiter', $delimiter);

            $headers = array('ticket_id', 'ticket_type', 'order#', 'destination', 'name', 'surname', 'birthday', 'role', 'toddlers', 'special_needs', 'comment', 'status');
            fputcsv($handle, $headers, $delimiter);

            $orders = array();

            $events = EM_Events::get(array('scope'=>'future'));
            foreach ($events as $EM_Event) {
                if (($EM_Event->event_id != 10) && ($EM_Event->event_id != 12)) {
                    continue;
                }
                foreach ($EM_Event->get_bookings()->bookings as $EM_Booking) {

                    $order = array();
                    $order['event_id'] = $EM_Event->event_id;
                    $order['id'] = $EM_Booking->booking_id;
                    $order['person'] = $EM_Booking->get_person()->get_name();
                    $order['email'] = $EM_Booking->get_person()->user_email;
                    $order['phone'] = $EM_Booking->get_person()->phone;
                    $order['status'] = $EM_Booking->get_status(true);
                    if (($order['status'] != 'Approved') && ($order['status'] != 'Awaiting Payment')) {
                        continue;
                    }
                    $order['people'] = array();
                    $order['toddlers'] = array();
                    $order['tickets'] = array();

                    $EM_Form = EM_Booking_Form::get_form($EM_Event->event_id, $EM_Booking);
                    $order['bus_needed'] = apply_filters('translate_text', $EM_Form->get_formatted_value($EM_Form->form_fields['bus_needed'], $EM_Booking->booking_meta['booking']['bus_needed']), 'ru');
                    $order['special_needs'] = $EM_Form->get_formatted_value($EM_Form->form_fields['special_needs'], $EM_Booking->booking_meta['booking']['special_needs']);
                    $order['comment'] = $EM_Form->get_formatted_value($EM_Form->form_fields['dbem_comment'], $EM_Booking->booking_meta['booking']['dbem_comment']);

                    // populate arrays from tickets and attendees data
                    $attendees_data = EM_Attendees_Form::get_booking_attendees($EM_Booking);
                    foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
                        $ticket_name = apply_filters('translate_text', $EM_Ticket_Booking->get_ticket()->ticket_name, 'ru');  

                        $people = array();
                        if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
                            foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
                                $person = array();
                                $i = 0;
                                $person['role'] = 'участник';
                                foreach( $attendee_data as $field_value) {
                                    if ($i == 0) {
                                        $person['name'] = $field_value; 
                                    }
                                    if ($i == 1) {
                                        $person['surname'] = $field_value; 
                                    }
                                    if ($i == 2) {
                                        $person['birthday'] = $field_value; 
                                    }
                                    if (($EM_Event->event_id == 10) && ($i == 3)) {
                                        $person['birthday'] = $field_value; 
                                    }
                                    if (($EM_Event->event_id == 12) && ($i == 5)) {
                                        $person['role'] = apply_filters('translate_text', $field_value, 'ru');
                                    }
                                    $i++;
                                }
                                if ( $person['role'] == 'презентер') {
                                    if (strpos($order['comment'], '#child_program') !== false) {
                                        $person['role'] = 'мадрих детской программы';
                                    }
                                }
                                if ($ticket_name == 'Младенцы (до 3 лет)') {
                                    $person['role'] = 'младенец';
                                }
                                $people[] = $person;
                            }
                        }

                        if (($ticket_name == 'Взрослые и подростки (старше 12 лет)') ||
                            ($ticket_name == 'Дети (от 3 до 12 лет)')) {
                            $order['people'] = array_merge($order['people'], $people);
                        }

                        if ($ticket_name == 'Младенцы (до 3 лет)') {
                            $order['toddlers'] = array_merge($order['toddlers'], $people);
                        }

                        if ($ticket_name == 'Transportation') {
                            $ticket_data = array();
                            $ticket_data['name'] = $ticket_name;
                            $ticket_data['people'] = array();
                            
                            for ($i = 0; $i < $EM_Ticket_Booking->get_spaces(); $i++) {
                                $ticket_data['bus_needed'] = $order['bus_needed'];
                                if (strpos($order['comment'], 'TRANSPORT'.($i+1).'#TA') !== false) {
                                    $ticket_data['bus_needed'] = 'Тель Авив';
                                }
                                if (strpos($order['comment'], 'TRANSPORT'.($i+1).'#J') !== false) {
                                    $ticket_data['bus_needed'] = 'Иерусалим';
                                }
                                if (strpos($order['comment'], 'TRANSPORT'.($i+1).'#H') !== false) {
                                    $ticket_data['bus_needed'] = 'Хайфа';
                                }
                                $order['tickets'][] = $ticket_data;
                            }
                        }
                    }
                    
                    if (count($order['tickets']) == 0) {
                        continue;
                    }

                    // populate tickets
                    $person_id = 0;
                    foreach($order['tickets'] as $key => $room) {
                        $order['tickets'][$key]['people'][] = $order['people'][$person_id++];
                    }
                    
                    // verify that the order has tickets for all people
                    if ($person_id < count($order['people'])) {
                        $ticket_data = array();
                        $ticket_data['name'] = "Not enough tickets";
                        $ticket_data['bus_needed'] = '';
                        $ticket_data['people'] = array();
                        $person = array();
                        $person['name'] = '';
                        $person['surname'] = '';
                        $person['birthday'] = '';
                        $person['role'] = '';
                        $ticket_data['people'][] = $person;
                        $order['tickets'][] = $ticket_data;
                    }

                    $toddler_names = '';
                    $i = 0;
                    foreach($order['toddlers'] as $toddler) {
                        if ($i++ > 0) {
                            $toddler_names .= ', ';
                        }
                        $toddler_names .= $toddler['name'];
                        $toddler_names .= ' ';
                        $toddler_names .= $toddler['surname'];
                        $toddler_names .= ' ';
                        $toddler_names .= $toddler['birthday'];
                    }
                    $order['tickets'][0]['toddler_names'] = $toddler_names;

                    $orders[$order['id']] = $order;
                }
            }

            ksort($orders);

            $ticket_count = 1;
            foreach($orders as $order) {
               foreach($order['tickets'] as $ticket) {
                   $i = 0;
                   foreach($ticket['people'] as $person) {
                       $row = array();
                       if ($ticket['name'] == 'Not enough tickets') {
                           $row[] = '';
                       } else {
                           $row[] = $ticket_count++;
                       }
                       $row[] = $ticket['name'];
                       // $row[] = $order['event'];
                       $row[] = $order['id'];
                       $row[] = $ticket['bus_needed'];
                       $row[] = $person['name'];
                       $row[] = $person['surname'];
                       $row[] = $person['birthday'];
                       $row[] = $person['role'];
                       if ($i++ == 0) {
                           $row[] = $ticket['toddler_names'];
                       } else {
                           $row[] = '';
                       }
                       $row[] = $order['special_needs'];
                       $row[] = $order['comment'];
                       $row[] = $order['status'];
                       fputcsv($handle, $row, $delimiter);
                   }
               }
            }

            fclose($handle);
            exit();
        }
    }
}

EM_Limmud_CSV::init();
