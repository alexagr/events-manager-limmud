<?php

class EM_Limmud_Secret {
    public static function init() {
        add_filter('em_booking_validate', array(__CLASS__, 'em_booking_validate'), 15, 2); //validate object
    }

    public static function check_code_valid($secret_code) {
		// check that code is valid
		$valid = false;
		$file = @fopen(WP_PLUGIN_DIR.'/events-manager-secrets/secrets.txt', 'r'); 
		if ($file) {
			while (($str = fgets($file, 1024)) !== false) {
				$str = str_replace("\n", '', $str);
				$str = str_replace("\r", '', $str);
				if ($secret_code == $str) {
					$valid = true;
				}
			}
		}
		return $valid;
	}

    public static function check_code_unique($secret_code, $EM_Booking) {
		// check if code was already used
		foreach ($EM_Booking->get_event()->get_bookings()->bookings as $EM_OtherBooking) {
			if ($EM_Booking->booking_id == $EM_OtherBooking->booking_id) {
				continue;
			}
			$attendees_data = EM_Attendees_Form::get_booking_attendees($EM_OtherBooking);
			foreach($EM_OtherBooking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
				if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
					foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
						foreach( $attendee_data as $attendee_label => $attendee_value) {
							$label = apply_filters('translate_text', $attendee_label, 'ru');
							if (($label == 'Секретный код') && ($attendee_value == $secret_code)) {
								return false;
							}
						}
					}
				}
			}
		}
		return true;
	}
    
    public static function em_booking_validate($result, $EM_Booking) {
		$secret_codes = array();
        $secret_code_needed = false;
        $attendees_data = EM_Attendees_Form::get_booking_attendees($EM_Booking);

		$admin_secret = false;
		$file = @fopen(WP_PLUGIN_DIR.'/events-manager-secrets/admin.txt', 'r');
		if ($file) {
            $admin_secret = fgets($file, 1024);
			if ($admin_secret !== false) {
				$admin_secret = str_replace("\n", '', $admin_secret);
				$admin_secret = str_replace("\r", '', $admin_secret);
			}
		}

        foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
			if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
				foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
					$participation_type = '';
					$secret_code = '';
					foreach( $attendee_data as $attendee_label => $attendee_value) {
						$label = apply_filters('translate_text', $attendee_label, 'ru');
						if ($label == 'Участвует в качестве') {
							$participation_type = apply_filters('translate_text', $attendee_value, 'ru');
						}
						if ($label == 'Секретный код') {
							$secret_code = $attendee_value;
                            $secret_code_needed = true;
						}
					}

					if (($participation_type != 'волонтер') && ($participation_type != 'презентер') && ($participation_type != 'организатор')) {
						continue;
					}

					if (($admin_secret !== false) && ($secret_code == $admin_secret)) {
						array_push($secret_codes, $secret_code);
						continue;
					}

					if (($secret_code == '') || ($secret_code == 'n/a')) {
						$EM_Booking->add_error('Secret code is empty');
						$result = false;
						continue;
					}

					if (!self::check_code_valid($secret_code)) {
						$EM_Booking->add_error('Secret code ' . $secret_code . ' is invalid');
						$result = false;
						continue;
					}

					if (!self::check_code_unique($secret_code, $EM_Booking)) {
						$EM_Booking->add_error('Secret code ' . $secret_code . ' was already used');
						$result = false;
						continue;
					}

					if (($participation_type == 'волонтер') && ($secret_code[0] != '1')) {
						$EM_Booking->add_error('Secret code ' . $secret_code . ' is wrong');
						$result = false;
						continue;
					}

					if (($participation_type == 'презентер') && ($secret_code[0] != '2')) {
						$EM_Booking->add_error('Secret code ' . $secret_code . ' is wrong');
						$result = false;
						continue;
					}

					if (($participation_type == 'организатор') && (($admin_secret == false) || ($secret_code != $admin_secret))) {
						$EM_Booking->add_error('Secret code ' . $secret_code . ' is wrong');
						$result = false;
						continue;
					}
					
					if (in_array($secret_code, $secret_codes)) {
						$EM_Booking->add_error('Secret code ' . $secret_code . ' is not unique');
						$result = false;
						continue;
					}
					
					array_push($secret_codes, $secret_code);
				}
			}
		}
        if ($secret_code_needed && empty($secret_codes)) {
			$EM_Booking->add_error('At least one participant must be volunteer or presenter');
			$result = false;
        }
        return $result;    
    }
}

EM_Limmud_Secret::init();