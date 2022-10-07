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
        <h4>Limmud 2022 reports</h4>
        <p>There reports collect data from all relevant events and use pre-defined formats. Generic split and columns to export settings are irrelevant.</p>
        <p>Full bookings report <input type="checkbox" name="limmud_export" value="1" />
        <a href="#" title="Complete information about all bookings (including cancelled)">?</a></p>
        <p>Accomodation report <input type="checkbox" name="limmud_accomodation" value="1" />
        <a href="#" title="Accomodation report (only approved and pending bookings)">?</a></p>
        <p>Hotel report <input type="checkbox" name="limmud_hotel" value="1" />
        <a href="#" title="Hotel report (only approved bookings with accomodation)">?</a></p>
        <p>Transport report <input type="checkbox" name="limmud_transport" value="1" />
        <a href="#" title="Transport report (only approved and pending bookings)">?</a></p>
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

    public static function intercept_csv_export() {
        if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'export_bookings_csv' && !empty($_REQUEST['limmud_export']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'export_bookings_csv')) {

            header("Content-Type: application/octet-stream; charset=utf-8");
            header("Content-Disposition: Attachment; filename=".sanitize_title(get_bloginfo())."-bookings-export.csv");
            do_action('em_csv_header_output');
            echo "\xEF\xBB\xBF"; // UTF-8 for MS Excel (a little hacky... but does the job)

            $handle = fopen("php://output", "w");
            $delimiter = !defined('EM_CSV_DELIMITER') ? ',' : EM_CSV_DELIMITER;
            $delimiter = apply_filters('em_csv_delimiter', $delimiter);

            $headers = array('id', 'name', 'email', 'status', 'event_id', 'ticket_name', 'ticket_price', 'phone', 'city', 'ticket_days', 'ticket_type', 'room_type', 'bus_needed', 'special_needs', 'comment', 'first_name', 'last_name', 'sex', 'birthday', 'age', 'passport', 'role', 'secret');
            fputcsv($handle, $headers, $delimiter);

            $events = EM_Events::get(array('scope'=>'all'));
            foreach ($events as $EM_Event) {
                if (($EM_Event->event_id != 19) && ($EM_Event->event_id != 20) && ($EM_Event->event_id != 21)) {
                    continue;
                }
                foreach ($EM_Event->get_bookings()->bookings as $EM_Booking) {
					$event_date = date("U", $EM_Booking->get_event()->start()->getTimestamp());
                    $attendees_data = EM_Attendees_Form::get_booking_attendees($EM_Booking);
                    foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
                        $EM_Form = EM_Booking_Form::get_form($event_id, $EM_Booking);
                        $row = array();
                        $row[] = $EM_Booking->booking_id;
                        $row[] = $EM_Booking->get_person()->get_name();
                        $row[] = $EM_Booking->get_person()->user_email;
                        $row[] = $EM_Booking->get_status(true);
                        $row[] = $EM_Booking->get_event()->event_id;
                        $row[] = apply_filters('translate_text', $EM_Ticket_Booking->get_ticket()->ticket_name, 'ru');
                        $row[] = $EM_Ticket_Booking->get_ticket()->get_price(true);
                        $row[] = '"' . $EM_Booking->get_person()->phone . '"';
                        $row[] = $EM_Booking->booking_meta['registration']['dbem_city'];

						$row[] = self::booking_field('ticket_days', $EM_Form, $EM_Booking);
						$row[] = self::booking_field('ticket_type', $EM_Form, $EM_Booking);
						$row[] = self::booking_field('room_type', $EM_Form, $EM_Booking);
						$row[] = self::booking_field('bus_needed', $EM_Form, $EM_Booking);

						$row[] = self::booking_field('special_needs', $EM_Form, $EM_Booking, false);
						$row[] = self::booking_field('dbem_comment', $EM_Form, $EM_Booking, false);

                        if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
                            foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
                                $full_row = $row;
                                foreach( $attendee_data as $field_label => $field_value) {
                                    $value = apply_filters('translate_text', $field_value, 'ru');
                                    if ($field_value != 'n/a') {
                                        $full_row[] = apply_filters('translate_text', $field_value, 'ru');
                                    } else {
                                        $full_row[] = '';
                                    }

                                    $label = apply_filters('translate_text', $field_label, 'ru');
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

            $headers = array('event', 'ticket_name', 'order#', 'name', 'surname', 'sex', 'role', 'age_group', 'age', 'ticket_type', 'special_needs', 'comment', 'status', 'meal');
            fputcsv($handle, $headers, $delimiter);

            $orders = array();

            $events = EM_Events::get(array('scope'=>'all'));
            foreach ($events as $EM_Event) {
                if (($EM_Event->event_id != 19) && ($EM_Event->event_id != 20) && ($EM_Event->event_id != 21)) {
                    continue;
                }
                foreach ($EM_Event->get_bookings()->bookings as $EM_Booking) {
                    $order = array();
                    $order['event'] = $EM_Event->event_id;
                    $order['id'] = $EM_Booking->booking_id;
                    $order['person'] = $EM_Booking->get_person()->get_name();
                    $order['email'] = $EM_Booking->get_person()->user_email;
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

                    $order['children_num'] = 0;
                    $order['toddlers_num'] = 0;
                    $order['meal'] = 2;

                    if (strpos($order['comment'], 'MEAL#1') !== false) {
                        $order['meal'] = 1;
                    }

                    // populate arrays from attendees data
					$event_date = date("U", $EM_Booking->get_event()->start()->getTimestamp());
                    $attendees_data = EM_Attendees_Form::get_booking_attendees($EM_Booking);
                    foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
                        $ticket_name = apply_filters('translate_text', $EM_Ticket_Booking->get_ticket()->ticket_name, 'ru');  
						if ($ticket_name == 'Количество участников') {
							if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
								foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
									$person = array();
									$person['role'] = 'участник';
									foreach( $attendee_data as $field_label => $field_value) {
										$label = apply_filters('translate_text', $field_label, 'ru');
										if ($label == 'Имя (на английском)') {
											$person['name'] = $field_value; 
										}
										if ($label == 'Фамилия (на английском)') {
											$person['surname'] = $field_value; 
										}
										if ($label == 'Пол') {
											$person['sex'] = apply_filters('translate_text', $field_value, 'ru'); 
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
													if ($age >= 3) {
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
									}
									
									if ($person['age_group'] == 'adult') {
										$order['adults'][] = $person;
									} elseif ($person['age_group'] == 'child') {
										$order['children'][] = $person;
									} elseif ($person['age_group'] == 'toddler') {
										$order['toddlers'][] = $person;
									}
								}
							}
						}

                        if (($ticket_name == 'Adult') || ($ticket_name == 'Adult in Triple') || ($ticket_name == 'Single') || 
						    ($ticket_name == 'Child') || ($ticket_name == 'No Accomodation - 1 Day') || ($ticket_name == 'No Accomodation - 3 Days')) {
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
                        if (($ticket['name'] == 'Adult') || ($ticket['name'] == 'Adult in Triple') || ($ticket['name'] == 'Single')) {
                            $order['tickets'][$key]['people'][] = $order['adults'][$adult_id++];
                        } elseif ($ticket['name'] == 'Child') {
                            $order['tickets'][$key]['people'][] = $order['children'][$child_id++];
                        } else {
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
                        $person['sex'] = '';
                        $person['role'] = '';
                        $person['age_group'] = '';
                        $person['age'] = '';
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
                       $row[] = $person['sex'];
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
                       fputcsv($handle, $row, $delimiter);
                   }
               }
            }

            fclose($handle);
            exit();
        }

        if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'export_bookings_csv' && !empty($_REQUEST['limmud_hotel']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'export_bookings_csv')) {

            header("Content-Type: application/octet-stream; charset=utf-8");
            header("Content-Disposition: Attachment; filename=".sanitize_title(get_bloginfo())."-bookings-hotel.csv");
            do_action('em_csv_header_output');
            echo "\xEF\xBB\xBF"; // UTF-8 for MS Excel (a little hacky... but does the job)

            $handle = fopen("php://output", "w");
            $delimiter = !defined('EM_CSV_DELIMITER') ? ',' : EM_CSV_DELIMITER;
            $delimiter = apply_filters('em_csv_delimiter', $delimiter);

            $headers = array('hotel_name', 'order#', 'num_of_adults', 'num_of_children', 'num_of_toddlers', 'special_needs', 'comment', 'name1', 'surname1', 'age1', 'role1', 'name2', 'surname2', 'age2', 'role2', 'name3', 'surname3', 'age3', 'role3', 'name4', 'surname4', 'age4', 'role4', 'name5', 'surname5', 'age5', 'role5');
            fputcsv($handle, $headers, $delimiter);

            $orders = array();

            $events = EM_Events::get(array('scope'=>'all'));
            foreach ($events as $EM_Event) {
                if (($EM_Event->event_id != 19) && ($EM_Event->event_id != 21)) {
                    continue;
                }
                foreach ($EM_Event->get_bookings()->bookings as $EM_Booking) {
                    $order = array();
                    $order['event'] = $EM_Event->event_id;
                    $order['id'] = $EM_Booking->booking_id;
                    $order['person'] = $EM_Booking->get_person()->get_name();
                    $order['email'] = $EM_Booking->get_person()->user_email;
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
                    $order['no_accomodation'] = false;
                    $order['meal'] = 2;

                    if (strpos($order['comment'], 'MEAL#1') !== false) {
                        $order['meal'] = 1;
                    }

                    // populate arrays from attendees data
					$event_date = date("U", $EM_Booking->get_event()->start()->getTimestamp());
                    $attendees_data = EM_Attendees_Form::get_booking_attendees($EM_Booking);
                    foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
                        $ticket_name = apply_filters('translate_text', $EM_Ticket_Booking->get_ticket()->ticket_name, 'ru');
						if ($ticket_name == 'Количество участников') {
							if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
								foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
									$person = array();
									$person['role'] = 'участник';
									foreach( $attendee_data as $field_label => $field_value) {
										$label = apply_filters('translate_text', $field_label, 'ru');
										if ($label == 'Имя (на английском)') {
											$person['name'] = $field_value;
										}
										if ($label == 'Фамилия (на английском)') {
											$person['surname'] = $field_value;
										}
										if ($label == 'Пол') {
											$person['sex'] = apply_filters('translate_text', $field_value, 'ru');
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
													if ($age >= 3) {
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
									}

									if ($person['age_group'] == 'adult') {
										$order['adults'][] = $person;
									} elseif ($person['age_group'] == 'child') {
										$order['children'][] = $person;
									} elseif ($person['age_group'] == 'toddler') {
										$order['toddlers'][] = $person;
									}
								}
							}
						}

                        if (($ticket_name == 'Adult') || ($ticket_name == 'Adult in Triple') || ($ticket_name == 'Single') ||
						    ($ticket_name == 'Child') || ($ticket_name == 'No Accomodation - 1 Day') || ($ticket_name == 'No Accomodation - 3 Days')) {
                            $ticket_data = array();
                            $ticket_data['name'] = $ticket_name;
							$ticket_data['ticket_type'] = self::booking_field('ticket_type', $EM_Form, $EM_Booking);

                            $ticket_data['people'] = array();
                            for ($i = 0; $i < $EM_Ticket_Booking->get_spaces(); $i++) {
                                $order['tickets'][] = $ticket_data;
                            }
                        }

                        if (($ticket_name == 'No Accomodation - 1 Day') || ($ticket_name == 'No Accomodation - 3 Days')) {
                            $order['no_accomodation'] = true;
                        }
                    }

                    if ($order['no_accomodation']) {
                        continue;
                    }

                    // populate tickets
                    $adult_id = 0;
                    $child_id = 0;
                    foreach($order['tickets'] as $key => $ticket) {
                        if (($ticket['name'] == 'Adult') || ($ticket['name'] == 'Adult in Triple') || ($ticket['name'] == 'Single')) {
                            $order['tickets'][$key]['people'][] = $order['adults'][$adult_id++];
                        } elseif ($ticket['name'] == 'Child') {
                            $order['tickets'][$key]['people'][] = $order['children'][$child_id++];
                        } else {
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
                        $person['sex'] = '';
                        $person['role'] = '';
                        $person['age_group'] = '';
                        $person['age'] = '';
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
               $row = array();
               $row[] = $order['hotel_name'];
               $row[] = $order['id'];
               $row[] = strval(count($order['adults']));
               $row[] = strval(count($order['children']));
               $row[] = strval(count($order['toddlers']));
               $row[] = $order['special_needs'];
               $row[] = $order['comment'];
               $ticket_count = 5;
               foreach($order['tickets'] as $ticket) {
                   foreach($ticket['people'] as $person) {
                       $row[] = $person['name'];
                       $row[] = $person['surname'];
                       $row[] = $person['age'];
                       $row[] = $person['role'];
                       $ticket_count--;
                   }
               }
               while ($ticket_count > 0) {
                   $row[] = '';
                   $row[] = '';
                   $row[] = '';
                   $ticket_count--;
               }
               fputcsv($handle, $row, $delimiter);
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
                if (($EM_Event->event_id != 19) && ($EM_Event->event_id != 21)) {
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

                        if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
                            foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
                                $person = array();
                                foreach( $attendee_data as $field_label => $field_value) {
                                    $label = apply_filters('translate_text', $field_label, 'ru');
                                    if ($label == 'Имя (на английском)') {
                                        $person['name'] = $field_value;
                                    }
                                    if ($label == 'Фамилия (на английском)') {
                                        $person['surname'] = $field_value;
                                    }
                                    if ($label == 'Пол') {
                                        $person['sex'] = apply_filters('translate_text', $field_value, 'ru');
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
                                                if ($age >= 3) {
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

                                if ($person['role'] == 'презентер') {
                                    if (strpos($order['comment'], '#child_program') !== false) {
                                        $person['role'] = 'мадрих детской программы';
                                    }
                                }

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
