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
        <h4>Limmud 2025 reports</h4>
        <p>There reports collect data from all relevant events and use pre-defined formats. Generic split and columns to export settings are irrelevant.</p>
        <!--p>Full bookings report <input type="checkbox" name="limmud_full" value="1" />
        <a href="#" title="Complete information about all bookings (including cancelled)">?</a></p>
        <p>Accomodation report <input type="checkbox" name="limmud_approved" value="1" />
        <a href="#" title="Approved and pending bookings">?</a></p-->
        <p>Hotel report <input type="checkbox" name="limmud_hotel" value="1" />
        <a href="#" title="Report for hotel (only approved bookings with accomodation)">?</a></p>
        <!--p>Transport report <input type="checkbox" name="limmud_transport" value="1" />
        <a href="#" title="Transport report (only approved and pending bookings)">?</a></p> -->
        <hr />
        <?php
    }

    public static function booking_field($field_name, $EM_Form, $EM_Booking, $translate=true) {
		if (array_key_exists($field_name, $EM_Booking->booking_meta['booking'])) {
			$value = $EM_Form->get_formatted_value($EM_Form->form_fields[$field_name], $EM_Booking->booking_meta['booking'][$field_name]);
			if ($translate) {
				return apply_filters('translate_text', $value, 'ru');
			} else {
				return $value;
			}
		} else {
			return '';
		}
	}

    public static function full_report() {
        /*
        Full export - all bookings and all tickets
        */
        header("Content-Type: application/octet-stream; charset=utf-8");
        header("Content-Disposition: Attachment; filename=".sanitize_title(get_bloginfo())."-full-" . date('Y-m-d-h-i', time()) . ".csv");
        do_action('em_csv_header_output');
        echo "\xEF\xBB\xBF"; // UTF-8 for MS Excel (a little hacky... but does the job)

        $handle = fopen("php://output", "w");
        $delimiter = !defined('EM_CSV_DELIMITER') ? ',' : EM_CSV_DELIMITER;
        $delimiter = apply_filters('em_csv_delimiter', $delimiter);

        $headers = array('id', 'name', 'email', 'status', 'event_id', /* 'ticket_name', 'ticket_price', */ 'phone', 'city', 'past_participation', /* 'ticket_days', 'ticket_type', 'room_type', 'bus_needed', */ 'special_needs', 'comment', 'first_name', 'last_name', 'birthday', 'age', 'passport', 'role', 'secret');
        fputcsv($handle, $headers, $delimiter);

        $events = EM_Events::get(array('scope'=>'all'));
        foreach ($events as $EM_Event) {
            if (($EM_Event->event_id != 26) && ($EM_Event->event_id != 27)) {
                continue;
            }
            /*
            if (!empty($_REQUEST['limmud_regular']) && ($EM_Event->event_id != 26)) {
                continue;
            }
            if (!empty($_REQUEST['limmud_volunteers']) && ($EM_Event->event_id != 27)) {
                continue;
            }
            */
            $event_date = date("U", $EM_Event->start()->getTimestamp());
            foreach ($EM_Event->get_bookings()->bookings as $EM_Booking) {
                $attendees_data = EM_Attendees_Form::get_booking_attendees($EM_Booking);
                foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
                    $EM_Form = EM_Booking_Form::get_form($event_id, $EM_Booking);
                    $row = array();
                    $row[] = $EM_Booking->booking_id;
                    $row[] = $EM_Booking->get_person()->get_name();
                    $row[] = $EM_Booking->get_person()->user_email;
                    $row[] = $EM_Booking->get_status(true);
                    $row[] = $EM_Booking->get_event()->event_id;
                    /*
                    $row[] = apply_filters('translate_text', $EM_Ticket_Booking->get_ticket()->ticket_name, 'ru');
                    $row[] = $EM_Ticket_Booking->get_ticket()->get_price(true);
                    */
                    if (apply_filters('translate_text', $EM_Ticket_Booking->get_ticket()->ticket_name, 'ru') != "Количество участников") {
                        continue;
                    }
                    $row[] = '"' . $EM_Booking->get_person()->phone . '"';
                    $row[] = $EM_Booking->booking_meta['registration']['dbem_city'];
                    $row[] = self::booking_field('past_participation', $EM_Form, $EM_Booking);

                    /*
                    $row[] = self::booking_field('ticket_days', $EM_Form, $EM_Booking);
                    $row[] = self::booking_field('ticket_type', $EM_Form, $EM_Booking);
                    $row[] = self::booking_field('room_type', $EM_Form, $EM_Booking);
                    $row[] = self::booking_field('bus_needed', $EM_Form, $EM_Booking);
                    */

                    $row[] = self::booking_field('special_needs', $EM_Form, $EM_Booking, false);
                    $row[] = self::booking_field('dbem_comment', $EM_Form, $EM_Booking, false);

                    if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
                        foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
                            $full_row = $row;
                            foreach( $attendee_data as $field_label => $field_value) {
                                $label = apply_filters('translate_text', $field_label, 'ru');
                                if ($label == 'Пол') {
                                    continue;
                                }
                                $value = apply_filters('translate_text', $field_value, 'ru');
                                if ($field_value != 'n/a') {
                                    $full_row[] = apply_filters('translate_text', $field_value, 'ru');
                                } else {
                                    $full_row[] = '';
                                }

                                if ($label == 'Дата рождения') {
                                    $birth_date = explode('/', $field_value);
                                    if (count($birth_date) == 3) {
                                        // get age from birthdate in DD/MM/YYYY format
                                        $age = (date("md", date("U", mktime(0, 0, 0, $birth_date[1], $birth_date[0], $birth_date[2]))) > date("md", $event_date)
                                            ? ((date("Y", $event_date) - $birth_date[2]) - 1)
                                            : (date("Y", $event_date) - $birth_date[2]));
                                        $full_row[] = $age;
                                    } else {
                                        $full_row[] = '';
                                    }
                                }
                            }
                            fputcsv($handle, $full_row, $delimiter);
                        }
                    }
                }
            }
        }
        fclose($handle);
    }

    public static function approved_report() {
        /*
        Only Approved and Awaiting Payment orders
        */

        header("Content-Type: application/octet-stream; charset=utf-8");
        header("Content-Disposition: Attachment; filename=".sanitize_title(get_bloginfo())."-approved-" . date('Y-m-d-h-i', time()) . ".csv");
        do_action('em_csv_header_output');
        echo "\xEF\xBB\xBF"; // UTF-8 for MS Excel (a little hacky... but does the job)

        $handle = fopen("php://output", "w");
        $delimiter = !defined('EM_CSV_DELIMITER') ? ',' : EM_CSV_DELIMITER;
        $delimiter = apply_filters('em_csv_delimiter', $delimiter);

        $headers = array('event', 'ticket_name', 'hotel_name', 'order#', 'name', 'surname', 'role', 'age_group', 'age', 'ticket_type', 'special_needs', 'comment', 'status', 'meal', 'email', 'phone');
        fputcsv($handle, $headers, $delimiter);

        $orders = array();

        $events = EM_Events::get(array('scope'=>'all'));
        foreach ($events as $EM_Event) {
            if (($EM_Event->event_id != 22) && ($EM_Event->event_id != 23) && ($EM_Event->event_id != 24)) {
                continue;
            }
            $event_date = date("U", $EM_Event->start()->getTimestamp());
            foreach ($EM_Event->get_bookings()->bookings as $EM_Booking) {
                $order = array();
                $order['event'] = $EM_Event->event_id;
                $order['id'] = $EM_Booking->booking_id;
                $order['person'] = $EM_Booking->get_person()->get_name();
                $order['email'] = $EM_Booking->get_person()->user_email;
                $order['phone'] = '"' . $EM_Booking->get_person()->phone . '"';
                $order['status'] = $EM_Booking->get_status(true);
                if (($order['status'] != 'Approved') && ($order['status'] != 'Awaiting Payment')) {
                    continue;
                }
                $order['adults'] = array();
                $order['children'] = array();
                $order['toddlers'] = array();
                $order['tickets'] = array();

                $event_id = $EM_Booking->get_event()->event_id;
                $EM_Form = EM_Booking_Form::get_form($event_id, $EM_Booking);
                $order['special_needs'] = self::booking_field('special_needs', $EM_Form, $EM_Booking, false);
                $order['comment'] = self::booking_field('dbem_comment', $EM_Form, $EM_Booking, false);
                $order['hotel_name'] = self::booking_field('hotel_name', $EM_Form, $EM_Booking);;

                $order['children_num'] = 0;
                $order['toddlers_num'] = 0;
                $order['meal'] = 2;

                if (strpos($order['comment'], '#MEAL_1') !== false) {
                    $order['meal'] = 1;
                }

                // populate arrays from attendees data
                $attendees_data = EM_Attendees_Form::get_booking_attendees($EM_Booking);
                foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
                    $ticket_name = apply_filters('translate_text', $EM_Ticket_Booking->get_ticket()->ticket_name, 'ru');
                    if ($ticket_name == 'Количество участников') {
                        if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
                            $i = 0;
                            foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
                                $person = array();
                                $person['role'] = 'участник';
                                if (($i == 0) && str_contains($order['comment'], '#PRESENTER') && !str_contains($order['comment'], '#PRESENTER_'))
                                    $person['role'] = 'презентер';
                                if (($i > 0) && str_contains($order['comment'], '#PRESENTER_' . strval($i+1)))
                                    $person['role'] = 'презентер';
                                if (($i == 0) && str_contains($order['comment'], '#VOLUNTEER') && !str_contains($order['comment'], '#VOLUNTEER_'))
                                    $person['role'] = 'волонтер';
                                if (($i > 0) && str_contains($order['comment'], '#VOLUNTEER_' . strval($i+1)))
                                    $person['role'] = 'волонтер';
                                $i += 1;
                                foreach( $attendee_data as $field_label => $field_value) {
                                    $label = apply_filters('translate_text', $field_label, 'ru');
                                    if ($label == 'Имя (на английском)') {
                                        $person['name'] = $field_value;
                                    }
                                    if ($label == 'Фамилия (на английском)') {
                                        $person['surname'] = $field_value;
                                    }
                                    if ($label == 'Дата рождения') {
                                        $person['birthday'] = $field_value;
                                        $birth_date = explode('/', $field_value);
                                        if (count($birth_date) == 3) {
                                            // get age from birthdate in DD/MM/YYYY format
                                            $age = (date("md", date("U", mktime(0, 0, 0, $birth_date[1], $birth_date[0], $birth_date[2]))) > date("md", $event_date)
                                                ? ((date("Y", $event_date) - $birth_date[2]) - 1)
                                                : (date("Y", $event_date) - $birth_date[2]));

                                            $person['age'] = $age;
                                            if ($age >= 18) {
                                                $person['age_group'] = 'adult';
                                            } else {
                                                if ($age >= 12) {
                                                    $person['age_group'] = 'youth';
                                                } elseif ($age >= 2) {
                                                    $person['age_group'] = 'child';
                                                } else {
                                                    $person['age_group'] = 'toddler';
                                                }
                                                $order['meal'] = 1;
                                            }
                                        } else {
                                            $person['age'] = '';
                                            $person['age_group'] = '';
                                        }
                                    }
                                    if ($label == 'Участвует в качестве') {
                                        $person['role'] = apply_filters('translate_text', $field_value, 'ru');
                                    }
                                    if (($person['role'] == 'организатор') || ($person['role'] == 'презентер')) {
                                        $order['meal'] = 1;
                                    }
                                }

                                if (($person['age_group'] == 'adult') || ($person['age_group'] == 'youth')) {
                                    $order['adults'][] = $person;
                                } elseif ($person['age_group'] == 'child') {
                                    $order['children'][] = $person;
                                } elseif ($person['age_group'] == 'toddler') {
                                    $order['toddlers'][] = $person;
                                }
                            }
                        }
                    }

                    if (preg_match("/^\s*(Adult|Adult in Triple|Single|Child|No Accomodation - 1 Day|No Accomodation - 3 Days)\s*$/", $ticket_name)) {
                        $ticket_data = array();
                        $ticket_data['name'] = $ticket_name;
                        $ticket_data['ticket_type'] = self::booking_field('ticket_type', $EM_Form, $EM_Booking);

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
                    if (preg_match("/^\s*(Adult|Adult in Triple|Single)\s*$/", $ticket['name'])) {
                        $order['tickets'][$key]['people'][] = $order['adults'][$adult_id++];
                    } elseif (preg_match("/^\s*Child\s*$/", $ticket['name'])) {
                        $order['tickets'][$key]['people'][] = $order['children'][$child_id++];
                    } else {
                        if ($adult_id < count($order['adults'])) {
                            $order['tickets'][$key]['people'][] = $order['adults'][$adult_id++];
                        } else {
                            $order['tickets'][$key]['people'][] = $order['children'][$child_id++];
                        }
                    }
                }

                /*
                // verify that the order has tickets for all people
                if (($adult_id < count($order['adults'])) || ($child_id < count($order['children']))) {
                    $ticket_data = array();
                    $ticket_data['name'] = "Not enough tickets: " . strval($adult_id) . " < " . strval(count($order['adults'])) . " || " . strval($child_id) . " < " . strval(count($order['children']));
                    $ticket_data['ticket_type'] = "";
                    $ticket_data['people'] = array();
                    $person = array();
                    $person['name'] = '';
                    $person['surname'] = '';
                    $person['role'] = '';
                    $person['age_group'] = '';
                    $person['age'] = '';
                    $ticket_data['people'][] = $person;
                    $order['tickets'][] = $ticket_data;
                }
                */

                // we use "per room" tickets in 2023 - hence let's just add missing tickets for adults and children
                if ($adult_id < count($order['adults'])) {
                    $ticket_data = array();
                    $ticket_data['name'] = 'Adult';
                    $ticket_data['ticket_type'] = "";
                    $ticket_data['people'] = array();
                    for ($i = $adult_id; $i < count($order['adults']); $i++) {
                        $ticket_data['people'][] = $order['adults'][$i];
                    }
                    $order['tickets'][] = $ticket_data;
                }
                if ($child_id < count($order['children'])) {
                    $ticket_data = array();
                    $ticket_data['name'] = 'Child';
                    $ticket_data['ticket_type'] = "";
                    $ticket_data['people'] = array();
                    for ($i = $child_id; $i < count($order['children']); $i++) {
                        $ticket_data['people'][] = $order['children'][$i];
                    }
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
                    $row[] = $order['hotel_name'];
                    $row[] = $order['id'];
                    $row[] = $person['name'];
                    $row[] = $person['surname'];
                    $row[] = $person['role'];
                    $row[] = $person['age_group'];
                    $row[] = $person['age'];
                    $row[] = $ticket['ticket_type'];
                    $row[] = $order['special_needs'];
                    $row[] = $order['comment'];
                    $row[] = $order['status'];
                    if (strpos($ticket['name'], 'No Accomodation') === false) {
                        $row[] = $order['meal'];
                    } else {
                        $row[] = '';
                    }
                    $row[] = $order['email'];
                    $row[] = $order['phone'];
                    fputcsv($handle, $row, $delimiter);
                }
            }
        }

        fclose($handle);
    }

    public static function calculate_participants($EM_Booking, $event_date) {
        $adults_num = 0;
        $children_num = 0;
        $names = array();
        $attendees_data = EM_Attendees_Form::get_booking_attendees($EM_Booking);
        foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
            $ticket_name = apply_filters('translate_text', $EM_Ticket_Booking->get_ticket()->ticket_name, 'ru');
            if ($ticket_name == 'Количество участников') {
                if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
                    $i = 0;
                    foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
                        $first_name = '';
                        $last_name = '';
                        $age = 0;
                        $role = '';
                        foreach( $attendee_data as $field_label => $field_value) {
                            $label = apply_filters('translate_text', $field_label, 'ru');
                            if ($label == 'Имя (на английском)') {
                                $first_name = $field_value;
                            }
                            if ($label == 'Фамилия (на английском)') {
                                $last_name = $field_value;
                            }
                            if ($label == 'Дата рождения') {
                                $birth_date = explode('/', $field_value);
                                if (count($birth_date) == 3) {
                                    // get age from birthdate in DD/MM/YYYY format
                                    $age = (date("md", date("U", mktime(0, 0, 0, $birth_date[1], $birth_date[0], $birth_date[2]))) > date("md", $event_date)
                                        ? ((date("Y", $event_date) - $birth_date[2]) - 1)
                                        : (date("Y", $event_date) - $birth_date[2]));

                                    if ($age >= 18) {
                                        $adults_num++;
                                    } else {
                                        $children_num++;
                                    }
                                }
                            }
                            if ($label == 'Участвует в качестве') {
                                $role = apply_filters('translate_text', $field_value, 'ru');
                            }
                        }
                        $full_name = trim($first_name . ' ' . $last_name);
                        if ($role == 'волонтер') {
                            $full_name .= ' [V]';
                        } elseif ($role == 'презентер') {
                            $full_name .= ' [P]';
                        } elseif ($role == 'организатор') {
                            $full_name .= ' [O]';
                        }
                        $full_name .= ' (' . strval($age) . ')';
                        $names[] = $full_name;
                    }
                }
            }
        }

        return [$names, $adults_num, $children_num];
    }

    public static function hotel_report() {
        /*
        Report for hotel - single line per room
        */

        header("Content-Type: application/octet-stream; charset=utf-8");
        header("Content-Disposition: Attachment; filename=".sanitize_title(get_bloginfo())."-hotel-" . date('Y-m-d-h-i', time()) . ".csv");
        do_action('em_csv_header_output');
        echo "\xEF\xBB\xBF"; // UTF-8 for MS Excel (a little hacky... but does the job)

        $handle = fopen("php://output", "w");
        $delimiter = !defined('EM_CSV_DELIMITER') ? ',' : EM_CSV_DELIMITER;
        $delimiter = apply_filters('em_csv_delimiter', $delimiter);

        $headers = array('id', 'event', 'status', 'people', 'names', 'email', 'phone', 'city');
        $orders = array();

        $events = EM_Events::get(array('scope'=>'all'));
        foreach ($events as $EM_Event) {
            if (($EM_Event->event_id != 29) && ($EM_Event->event_id != 31)) {  // 29 is regular registration, 31 is volunteers
                continue;
            }
            $event_date = date("U", $EM_Event->start()->getTimestamp());

            # add all event form fields to $headers
            $hidden_fields = array('user_email', 'dbem_phone', 'dbem_city', 'photo_use', 'personal_data', 'additional_emails', 'ticket_days', 'past_participation', 'past_participation_2', 'hebrew_knowledge');
            $EM_Form = EM_Booking_Form::get_form($EM_Event);
            foreach ($EM_Form->form_fields as $fieldid => $field) {
                if (in_array($fieldid, $hidden_fields) || in_array($fieldid, $headers)) continue;
                if ($field['type'] == 'html') { $hidden_fields[] = $fieldid; continue; }
                $headers[] = $fieldid;
            }

            # iterate over bookings an populate $orders array
            foreach ($EM_Event->get_bookings()->bookings as $EM_Booking) {
                if (!in_array($EM_Booking->booking_status, [0, 1, 5, 7])) {
                    continue;
                }

                $order = array();
                $order['id'] = intval($EM_Booking->booking_id);
                $order['event'] = intval($EM_Event->event_id);

                # booking status
                if (array_key_exists($EM_Booking->booking_status, $EM_Booking->status_array)) {
                    $order['status'] = $EM_Booking->status_array[$EM_Booking->booking_status];
                    if (in_array($EM_Booking->booking_status, [5, 7])) {
                        $total_paid = (int)$EM_Booking->get_total_paid();
                        if ($total_paid > 0) {
                            $order['status'] .= " (paid " . $total_paid . " of " . $EM_Booking->get_price() . ")";
                        }
                    }
                } else {
                    $order['status'] = $EM_Booking->booking_status;
                }

                # calculate names and number of participants
                [$names, $adults_num, $children_num] = self::calculate_participants($EM_Booking, $event_date);

                if ($children_num > 0) {
                    $order['people'] = $adults_num . " + " . $children_num;
                } else {
                    $order['people'] = $adults_num;
                }

                $order['names'] = implode(', ', $names);

                # calculate all emails
                $EM_Person = $EM_Booking->get_person();
                $emails = array($EM_Person->user_email);
                if (array_key_exists('additional_emails', $EM_Booking->booking_meta['booking'])) {
                    $additional_emails = preg_split('/[\s,]+/', $EM_Booking->booking_meta['booking']['additional_emails']);
                    foreach ($additional_emails as $email) {
                        if (!empty($email) && !in_array($email, $emails)) {
                            $emails[] = $email;
                        }
                    }
                }
                $order['email'] = implode(', ', $emails);

                $order['phone'] = $EM_Person->phone;
                $order['city'] = $EM_Booking->booking_meta['registration']['dbem_city'];

                # add all additional form fields
                foreach ($EM_Form->form_fields as $fieldid => $field) {
                    if (in_array($fieldid, $headers) && array_key_exists($fieldid, $EM_Booking->booking_meta['booking'])) {
                        $value = $EM_Form->get_formatted_value($field, $EM_Booking->booking_meta['booking'][$fieldid]);
                        if (is_string($value) && str_starts_with($value, '[:')) {
                            $value = apply_filters('translate_text', $value, 'ru');
                        }
                        $order[$fieldid] = strval($value);
                    }
                }

                if ($order['participation_type'] == 'без проживания') {
                    continue;
                }

                $orders[$order['id']] = $order;
            }
        }

        # sort orders by id
        ksort($orders);

        fputcsv($handle, $headers, $delimiter);

        foreach($orders as $order) {
            $row = array();
            foreach ($headers as $header) {
                if (array_key_exists($header, $order)) {
                    $row[] = $order[$header];
                } else {
                    $row[] = '';
                }
            }
            fputcsv($handle, $row, $delimiter);
        }

        fclose($handle);
    }

    public static function transport_report() {
        /*
        Transport report
        */

        header("Content-Type: application/octet-stream; charset=utf-8");
        header("Content-Disposition: Attachment; filename=".sanitize_title(get_bloginfo())."-transport-" . date('Y-m-d-h-i', time()) . ".csv");
        do_action('em_csv_header_output');
        echo "\xEF\xBB\xBF"; // UTF-8 for MS Excel (a little hacky... but does the job)

        $handle = fopen("php://output", "w");
        $delimiter = !defined('EM_CSV_DELIMITER') ? ',' : EM_CSV_DELIMITER;
        $delimiter = apply_filters('em_csv_delimiter', $delimiter);

        $headers = array('ticket_id', 'ticket_type', 'order#', 'destination', 'name', 'surname', 'birthday', 'role', 'toddlers', 'special_needs', 'comment', 'status', 'hotel', 'email');
        fputcsv($handle, $headers, $delimiter);

        $orders = array();

        $events = EM_Events::get(array('scope'=>'future'));
        foreach ($events as $EM_Event) {
            if (($EM_Event->event_id != 22) && ($EM_Event->event_id != 24)) {
                continue;
            }
            $event_date = date("U", $EM_Event->start()->getTimestamp());
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
                $order['hotel_name'] = self::booking_field('hotel_name', $EM_Form, $EM_Booking);;

                // populate arrays from tickets and attendees data
                $attendees_data = EM_Attendees_Form::get_booking_attendees($EM_Booking);
                foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
                    $ticket_name = apply_filters('translate_text', $EM_Ticket_Booking->get_ticket()->ticket_name, 'ru');

                    if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
                        $i = 0;
                        foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
                            $person = array();
                            $person['role'] = 'участник';
                            if (($i == 0) && str_contains($order['comment'], '#PRESENTER') && !str_contains($order['comment'], '#PRESENTER_'))
                                $person['role'] = 'презентер';
                            if (($i > 0) && str_contains($order['comment'], '#PRESENTER_' . strval($i+1)))
                                $person['role'] = 'презентер';
                            if (($i == 0) && str_contains($order['comment'], '#VOLUNTEER') && !str_contains($order['comment'], '#VOLUNTEER_'))
                                $person['role'] = 'волонтер';
                            if (($i > 0) && str_contains($order['comment'], '#VOLUNTEER_' . strval($i+1)))
                                $person['role'] = 'волонтер';
                            $i += 1;
                            foreach( $attendee_data as $field_label => $field_value) {
                                $label = apply_filters('translate_text', $field_label, 'ru');
                                if ($label == 'Имя (на английском)') {
                                    $person['name'] = $field_value;
                                }
                                if ($label == 'Фамилия (на английском)') {
                                    $person['surname'] = $field_value;
                                }
                                if ($label == 'Дата рождения') {
                                    $person['birthday'] = $field_value;
                                    $birth_date = explode('/', $field_value);
                                    if (count($birth_date) == 3) {
                                        // get age from birthdate in DD/MM/YYYY format
                                        $age = (date("md", date("U", mktime(0, 0, 0, $birth_date[1], $birth_date[0], $birth_date[2]))) > date("md", $event_date)
                                            ? ((date("Y", $event_date) - $birth_date[2]) - 1)
                                            : (date("Y", $event_date) - $birth_date[2]));

                                        $person['age'] = $age;
                                        if ($age >= 12) {
                                            $person['age_group'] = 'adult';
                                        } else {
                                            if ($age >= 2) {
                                                $person['age_group'] = 'child';
                                            } else {
                                                $person['age_group'] = 'toddler';
                                            }
                                        }
                                    } else {
                                        $person['age'] = '';
                                        $person['age_group'] = '';
                                    }
                                }
                                if ($label == 'Участвует в качестве') {
                                    $person['role'] = apply_filters('translate_text', $field_value, 'ru');
                                }
                            }

                            if (($person['role'] == 'презентер') && (strpos($order['comment'], '#CHILDREN') !== false)) {
                                $person['role'] = 'мадрих детской программы';
                            }

                            $person['bus_needed'] = '';
                            if ((strpos($order['comment'], '#BUS_' . strval($i+1) . '_NO') !== false) || (strpos($order['comment'], '#BUS_' . strval($i+1) . '_NA') !== false))
                                continue;
                            if (strpos($order['comment'], '#BUS_' . strval($i+1) . '_TA') !== false)
                                $person['bus_needed'] = 'Тель Авив';
                            if (strpos($order['comment'], '#BUS_' . strval($i+1) . '_J') !== false)
                                $person['bus_needed'] = 'Иерусалим';
                            if (strpos($order['comment'], '#BUS_' . strval($i+1) . '_H') !== false)
                                $person['bus_needed'] = 'Хайфа';

                            if ($person['age_group'] == 'adult' || $person['age_group'] == 'child') {
                                $order['people'][] = $person;
                            } elseif ($person['age_group'] == 'toddler') {
                                $order['toddlers'][] = $person;
                            }
                        }
                    }

                    if (($ticket_name == 'Transportation') || ($ticket_name == 'Transportation Haifa')) {
                        $ticket_data = array();
                        $ticket_data['name'] = $ticket_name;
                        $ticket_data['people'] = array();
                        $ticket_data['bus_needed'] = $order['bus_needed'];

                        for ($i = 0; $i < $EM_Ticket_Booking->get_spaces(); $i++) {
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
                    if ($order['people'][$person_id]['bus_needed'] != '') {
                        $order['tickets'][$key]['bus_needed'] = $order['people'][$person_id]['bus_needed'];
                    }
                    $order['tickets'][$key]['people'][] = $order['people'][$person_id];
                    $person_id++;
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
                    $row[] = $order['hotel_name'];
                    $row[] = $order['email'];
                    fputcsv($handle, $row, $delimiter);
                }
            }
        }

        fclose($handle);
    }

    public static function intercept_csv_export() {
        if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'export_bookings_csv' && (!empty($_REQUEST['limmud_full']) || !empty($_REQUEST['limmud_regular']) || !empty($_REQUEST['limmud_volunteers'])) && wp_verify_nonce($_REQUEST['_wpnonce'], 'export_bookings_csv')) {
            self::full_report();
            exit();
        }

        if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'export_bookings_csv' && !empty($_REQUEST['limmud_approved']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'export_bookings_csv')) {
            self::approved_report();
            exit();
        }

        if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'export_bookings_csv' && !empty($_REQUEST['limmud_hotel']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'export_bookings_csv')) {
            self::hotel_report();
            exit();
        }

        if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'export_bookings_csv' && !empty($_REQUEST['limmud_transport']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'export_bookings_csv')) {
            self::transport_report();
            exit();
        }
    }
}

EM_Limmud_CSV::init();
