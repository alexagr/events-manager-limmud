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

    public static function check_code_unique($secret_code, $first_name, $last_name, $EM_Booking) {
		// check if code was already used
		foreach ($EM_Booking->get_event()->get_bookings()->bookings as $EM_OtherBooking) {
			if ($EM_Booking->booking_id == $EM_OtherBooking->booking_id) {
				continue;
			}
			$attendees_data = EM_Attendees_Form::get_booking_attendees($EM_OtherBooking);
			foreach($EM_OtherBooking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
				if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
					foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
						$attendee_first_name = '';
						$attendee_last_name = '';
						$attendee_secret_code = '';
						foreach( $attendee_data as $attendee_label => $attendee_value) {
							$label = apply_filters('translate_text', $attendee_label, 'ru');
							if ($label == 'Имя (на английском)') {
								$attendee_first_name = $attendee_value;
							}
							if ($label == 'Фамилия (на английском)') {
								$attendee_last_name = $attendee_value;
							}
							if ($label == 'Секретный код') {
								$attendee_secret_code = trim($attendee_value);
							}
						}
						if (($attendee_secret_code == $secret_code) && (($attendee_first_name != $first_name) || ($attendee_last_name != $last_name))) {
							return false;
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
		$secret_code_invalid = false;
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

		$promo_secret = false;
		$file = @fopen(WP_PLUGIN_DIR.'/events-manager-secrets/promo.txt', 'r');
		if ($file) {
            $promo_secret = fgets($file, 1024);
			if ($promo_secret !== false) {
				$promo_secret = str_replace("\n", '', $promo_secret);
				$promo_secret = str_replace("\r", '', $promo_secret);
			}
		}

        foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
			if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
				foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
					$participation_type = '';
					$secret_code = '';
					$first_name = '';
					$last_name = '';
					foreach( $attendee_data as $attendee_label => $attendee_value) {
						$label = apply_filters('translate_text', $attendee_label, 'ru');
						if ($label == 'Имя (на английском)') {
							$first_name = $attendee_value;
						}
						if ($label == 'Фамилия (на английском)') {
							$last_name = $attendee_value;
						}
						if ($label == 'Участвует в качестве') {
							$participation_type = apply_filters('translate_text', $attendee_value, 'ru');
						}
						if ($label == 'Секретный код') {
							$secret_code = trim($attendee_value);
                            $secret_code_needed = true;
						}
					}

					if (($participation_type != 'волонтер') && ($participation_type != 'презентер') && ($participation_type != 'организатор') && ($participation_type != 'гость')) {
						continue;
					}

					if (($admin_secret !== false) && ($secret_code == $admin_secret)) {
						array_push($secret_codes, $secret_code);
						continue;
					}

					if (($participation_type == 'гость') && ($promo_secret !== false) && ($secret_code == $promo_secret)) {
						array_push($secret_codes, $secret_code);
						continue;
					}

					if (($secret_code == '') || ($secret_code == 'n/a')) {
						$EM_Booking->add_error(__('[:en]Enter secret code[:ru]Заполните поле: Секретный код[:he]נא למלא שדה: קוד הסודי[:]'));
						$secret_code_invalid = true;
						continue;
					}

					if (!self::check_code_valid($secret_code)) {
						$EM_Booking->add_error(__('[:en]Secret code[:ru]Секретный код[:he]קוד סודי[:] ' . $secret_code . ' [:en]is invalid[:ru]неправильный[:he]לא תקין[:]'));
						$secret_code_invalid = true;
						continue;
					}

					if (!self::check_code_unique($secret_code, $first_name, $last_name, $EM_Booking)) {
						$EM_Booking->add_error(__('[:en]Secret code[:ru]Секретный код[:he]קוד סודי[:] ' . $secret_code . ' [:en]was already used[:ru]уже был использован[:he]כבר היה בשימוש[:]'));
						$secret_code_invalid = true;
						continue;
					}

					if (($participation_type == 'волонтер') && ($secret_code[0] != '1')) {
						$EM_Booking->add_error(__('[:en]Secret code[:ru]Секретный код[:he]קוד סודי[:] ' . $secret_code . ' [:en]is wrong for volunteer[:ru]не подходит для волонтера[:he]לא תקין עבור מתנדב[:]'));
						$secret_code_invalid = true;
						continue;
					}

					if (($participation_type == 'презентер') && ($secret_code[0] != '2')) {
						$EM_Booking->add_error(__('[:en]Secret code[:ru]Секретный код[:he]קוד סודי[:] ' . $secret_code . ' [:en]is wrong for presenter[:ru]не подходит для презентера[:he]לא תקין עבור מרצה[:]'));
						$secret_code_invalid = true;
						continue;
					}

					if (($participation_type == 'организатор') && (($admin_secret == false) || ($secret_code != $admin_secret))) {
						$EM_Booking->add_error(__('[:en]Secret code[:ru]Секретный код[:he]קוד סודי[:] ' . $secret_code . ' [:en]is wrong for organizing committee[:ru]не подходит для организационного комитета[:he]לא תקין עבור הועדה המארגנת[:]'));
						$secret_code_invalid = true;
						continue;
					}

					if (($participation_type == 'гость') && ($secret_code[0] != '3')  && ($secret_code[0] != '4')) {
						$EM_Booking->add_error(__('[:en]Secret code[:ru]Секретный код[:he]קוד סודי[:] ' . $secret_code . ' [:en]is wrong for guest[:ru]не подходит для гостя[:he]לא תקין עבור אורח[:]'));
						$secret_code_invalid = true;
						continue;
					}

					if (in_array($secret_code, $secret_codes)) {
						$EM_Booking->add_error(__('[:en]Secret code[:ru]Секретный код[:he]קוד סודי[:] ' . $secret_code . ' [:en]is not unique[:ru]использован более одного раза[:he]הוכנס יותר מפעם אחד[:]'));
						$secret_code_invalid = true;
						continue;
					}
					
					array_push($secret_codes, $secret_code);
				}
			}
		}
		if ($secret_code_invalid) {
			$result = false;
		} else {
	        if ($secret_code_needed && empty($secret_codes)) {
				$EM_Booking->add_error(__('[:en]At least one participant must be volunteer or presenter[:ru]Как минимум один участник должен быть волонтером или презентером[:he]לפחות משתתף אחד צריך להיות מתנדב או מרצה[:]'));
				$result = false;
        	}
		}
        return $result;    
    }
}

EM_Limmud_Secret::init();