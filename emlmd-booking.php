<?php

class EM_Limmud_Booking {
    public static function init() {
        add_filter('the_title', array(__CLASS__, 'the_title'));
        add_filter('the_content', array(__CLASS__, 'the_content'), 10, 2);
        add_action('wp_head', array(__CLASS__, 'wp_head'));
        add_action('template_redirect', array(__CLASS__, 'template_redirect'));
    }

    public static function get_secret($EM_Booking, $seed = false) {
        $secret_text = $EM_Booking->person->user_email;
        if (!empty($seed)) {
            $secret_text .= $seed;
        }
        return md5($secret_text);
    }

    public static function get_payment_link($EM_Booking) {
        $link = '';
    	$booking_summary_page_id = get_option('dbem_booking_summary_page');
		if ($booking_summary_page_id != 0) {
			$link = get_post_permalink($booking_summary_page_id) . '&booking_id=' . $EM_Booking->booking_id . '&secret=' . self::get_secret($EM_Booking);
		}
        return $link;
    }

    public static function the_title($data, $id = null) {
        global $post;
        if (empty($post)) return $data; //fix for any other plugins calling the_title outside the loop

        $summary_page_ids = [
            get_option('dbem_booking_summary_page'),
            get_option('dbem_booking_success_page'),
            get_option('dbem_partial_payment_success_page'),
            get_option('dbem_payplus_redirect_page')
        ];

        foreach ($summary_page_ids as $page_id) {
            if (is_main_query() && ($post->ID == $page_id) && ($page_id != 0) && !empty( $_REQUEST['booking_id'])) {
                $page_title = '';
                $post_obj = get_post($page_id);
                if ($post_obj && !is_wp_error($post_obj)) {
                    $page_title = $post_obj->post_title;
                }

                if ($data == $page_title) { // without this we will change title of all menu items too
                    $EM_Booking = em_get_booking($_REQUEST['booking_id']);
                    return $EM_Booking->get_event()->event_name;
                }
            }
        }

        return $data;
    }

    public static function wp_head() {
        global $post;
        $paid_redirect_page_id = get_option('dbem_paid_redirect_page');
        // remove footer and title for paid redirect page that is displayed inside iframe
        if (($post->ID == $paid_redirect_page_id) && ($paid_redirect_page_id != 0)) {
            ?>
            <style>
                #header, #footer, .bottom-footer {
                    display: none;
                }
                #congrats {
                    background-color: honeydew;
                    padding: 20px;
                }
            </style>
            <?php
        }
    }

    public static function expand_content($EM_Booking, $content) {
        // Expand the content with additional information or formatting
        $EM_Event = $EM_Booking->get_event();
        $event_label_data = get_post_meta($EM_Event->post_id, '_event_label', true);
        if (!empty($event_label_data) && (strlen($event_label_data) > 3)) {
            $event_label = $event_label_data;
        } else {
            $event_label = "Лимуд FSU Израиль|לימוד FSU ישראל|Limmud FSU Israel";
        }
        $event_label_parts = explode('|', $event_label);
        // Ensure $event_label_parts has at least 3 elements
        for ($i = count($event_label_parts); $i < 3; $i++) {
            $event_label_parts[] = 'Limmud FSU Israel';
        }

        $content = str_replace('#_BOOKINGID', $EM_Booking->booking_id, $content);
        $content = str_replace('#_BOOKINGSUMMARYURL', self::get_payment_link($EM_Booking), $content);
        $event_year = date("Y", date("U", $EM_Event->start()->getTimestamp()));
        $content = str_replace('#_EVENTYEAR', $event_year, $content);
        $content = str_replace('#_EVENTLABELRU', $event_label_parts[0], $content);
        $content = str_replace('#_EVENTLABELHE', $event_label_parts[1], $content);
        $content = str_replace('#_EVENTLABELEN', $event_label_parts[2], $content);

        return $content;
    }

    public static function the_content($page_content) {
        global $post;
        if (empty($post) || post_password_required() || ($post->ID == 0)) {
            return $page_content;
        }

        $booking_summary_page_id = get_option('dbem_booking_summary_page');
        if ($post->ID == $booking_summary_page_id) {
            $content = apply_filters('em_content_pre', '', $page_content);
            if (empty($content)) {
                ob_start();
                self::booking_summary();
                $content = ob_get_clean();
                if (preg_match('/CONTENTS/', $page_content)) {
                    $content = str_replace('CONTENTS', $content, $page_content);
                }
            }
            return apply_filters('em_content', '<div id="em-wrapper">'.$content.'</div>');
        }

        $booking_success_page_id = get_option('dbem_booking_success_page');
        if ($post->ID == $booking_success_page_id) {
            $content = apply_filters('em_content_pre', '', $page_content);
            if (empty($content)) {
                if (preg_match('/#_BOOKINGID/', $page_content)) {
                    if (!empty( $_REQUEST['booking_id']) && !empty( $_REQUEST['secret'])) {
                        $EM_Booking = em_get_booking($_REQUEST['booking_id']);
                        if (($EM_Booking->booking_status == 1) && (self::get_secret($EM_Booking, 'payment_success') == $_REQUEST['secret'])) {
                            $content = self::expand_content($EM_Booking, $page_content);
                        }
                    }
                }
            }
            return apply_filters('em_content', '<div id="em-wrapper">'.$content.'</div>');
        }

        $partial_payment_success_page_id = get_option('dbem_partial_payment_success_page');
        if ($post->ID == $partial_payment_success_page_id) {
            $content = apply_filters('em_content_pre', '', $page_content);
            if (empty($content)) {
                if (preg_match('/#_BOOKINGID/', $page_content)) {
                    if (!empty( $_REQUEST['booking_id']) && !empty( $_REQUEST['secret'])) {
                        $EM_Booking = em_get_booking($_REQUEST['booking_id']);
                        if ((($EM_Booking->booking_status == 5) || ($EM_Booking->booking_status == 1)) && (self::get_secret($EM_Booking, 'partial_payment_success') == $_REQUEST['secret'])) {
                            $content = self::expand_content($EM_Booking, $page_content);
                        }
                    }
                }
            }
            return apply_filters('em_content', '<div id="em-wrapper">'.$content.'</div>');
        }

        $payplus_redirect_page_id = get_option('dbem_payplus_redirect_page');
        $paid_redirect_page_id = get_option('dbem_paid_redirect_page');
        if (($post->ID == $paid_redirect_page_id) || ($post->ID == $payplus_redirect_page_id)) {
            $content = apply_filters('em_content_pre', '', $page_content);
            if (empty($content)) {
                // PayPlus transaction callback
                $status = '';
                if (!empty($_REQUEST['status'])) {
                    # PayPlus
                    $status = $_REQUEST['status'];
                }
                if (!empty($_REQUEST['payme_status'])) {
                    # Paid
                    $status = $_REQUEST['payme_status'];
                }

                $amount = 0;
                if (!empty($_REQUEST['amount'])) {
                    # PayPlus
                    $amount = $_REQUEST['amount'];
                }
                if (!empty($_REQUEST['price'])) {
                    # Paid
                    $amount = (int)$_REQUEST['price'] / 100;
                }

                if (!empty($status)) {
                    if ($status == 'success') {
                        if ((preg_match('/#_BOOKINGID/', $page_content) || preg_match('/#_PAYMENTSUMMARY/', $page_content))) {
                            if (!empty($_REQUEST['booking_id']) && !empty($_REQUEST['secret'])) {
                                $EM_Booking = em_get_booking($_REQUEST['booking_id']);
                                if (self::get_secret($EM_Booking, 'payment_redirect') == $_REQUEST['secret']) {
                                    $content = self::expand_content($EM_Booking, $page_content);
                                    $content = str_replace('#_PRICE', $amount, $content);
                                    if (!empty($_REQUEST['payment_type']) && ($_REQUEST['payment_type'] == 'full')) {
                                        $content = str_replace('#_PAYMENTSUMMARY', "[:ru]Вы успешно оплатили вашу регистрацию[:he]תשלום התקבל בהצלחה.[:]", $content);
                                    }
                                    if (!empty($_REQUEST['payment_type']) && ($_REQUEST['payment_type'] == 'partial')) {
                                        $content = str_replace('#_PAYMENTSUMMARY', "[:ru]Вы успешно оплатили часть вашей регистрации.[:he]תשלום חלקי התקבל בהצלחה.[:]", $content);
                                    }
                                }
                            }
                        }
                    } else {
                        if (!empty( $_REQUEST['status_error_details'])) {
                            $content = '<p>' . $_REQUEST['status_error_details'] . '</p>';
                        } else {
                            $content = "<p>[:ru]Оплата не удалась[:he]התשלום נכשל[:]</p>";
                        }
                    }
                }
            }
            return apply_filters('em_content', '<div id="congrats">'.$content.'</div>');
        }

        return $page_content;
    }

    public static function template_redirect() {
        $event_bookings_page_id = get_option('dbem_event_bookings_page');
        if ($event_bookings_page_id != 0 && is_page($event_bookings_page_id)) {
            if (!is_user_logged_in()) {
                $current_url = home_url( add_query_arg( null, null ) );
                wp_redirect(wp_login_url($current_url));
                exit;
            }

            if (empty($_REQUEST['event_id'])) {
                echo 'Invalid event';
                exit;
            }

            $event_id = (int)$_REQUEST['event_id'];
            $EM_Event = em_get_event($event_id);

            $content = "<style>
                h2 {
                    font-size: 24px;
                    font-family: Arial, sans-serif;
                    margin-bottom: 18px;
                }

                h3 {
                    font-size: 18px;
                    font-family: Arial, sans-serif;
                    margin-bottom: 12px;
                }

                .bookings-table-container {
                    width: 100%;
                    overflow-x: auto;
                    margin: 20px 0;
                }

                .bookings-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-family: Arial, sans-serif;
                    font-size: 14px;
                    background-color: #fff;
                }

                .bookings-table th {
                    background-color: #f5f5f5;
                    color: #333;
                    text-align: left;
                    padding: 8px 12px;
                    border-bottom: 2px solid #ddd;
                }

                .bookings-table td {
                    padding: 8px 12px;
                    border-bottom: 1px solid #eee;
                }

                .bookings-table tr:nth-child(even) {
                    background-color: #fafafa;
                }

                .bookings-table tr:hover {
                    background-color: #f1f1f1;
                }

                .summary-table {
                    max-width: 60px;
                    border-collapse: collapse;
                    font-family: Arial, sans-serif;
                    font-size: 14px;
                    background-color: #fff;
                }

                .summary-table th {
                    background-color: #f5f5f5;
                    color: #333;
                    text-align: left;
                    padding: 8px 12px;
                    border-bottom: 2px solid #ddd;
                }

                .summary-table td {
                    padding: 8px 12px;
                    border-bottom: 1px solid #eee;
                }

                .summary-table tr:first-child th:nth-child(2) {
                    background-color: #eaeaea;
                }

                .summary-table tr:nth-child(2) th:nth-child(n+4) {
                    background-color: #eaeaea;
                }

                .summary-table td:nth-child(n+4) {
                    background-color: #f3f3f3;
                }

            </style>\n\n";

            $event_date = date("U", $EM_Event->start()->getTimestamp());
            $hidden_fields = array('user_email', 'dbem_phone', 'dbem_city', 'photo_use', 'personal_data', 'additional_emails');

            $content .= "<h2>" . apply_filters('translate_text', $EM_Event->event_name, 'ru') . "</h2>\n";
            $content .= "<div class=\"bookings-table-container\">\n";
            $content .= "<table class=\"bookings-table\">\n<tr><th>ID</th><th>status</th><th>people</th><th>name</th><th>email</th><th>phone</th>";
            $EM_Form = EM_Booking_Form::get_form($EM_Event);
            foreach ($EM_Form->form_fields as $fieldid => $field) {
                if (in_array($fieldid, $hidden_fields)) continue;
                if ($field['type'] == 'html') { $hidden_fields[] = $fieldid; continue; }
                $content .= "<th>" . $fieldid . "</th>";
            }
            $content .= "</tr>\n";

            $bookings_total = 0;
            $adults_total = 0;
            $children_total = 0;
            $bookings_approved_total = 0;
            $adults_approved_total = 0;
            $children_approved_total = 0;

            foreach ($EM_Event->get_bookings()->bookings as $EM_Booking) {
                if ($EM_Booking->booking_status == 3) {  # Canceled
                    continue;
                }
                $content .= "<tr><td>" . $EM_Booking->booking_id . "</td>";
                if (array_key_exists($EM_Booking->booking_status, $EM_Booking->status_array)) {
                    $content .= "<td>" . $EM_Booking->status_array[$EM_Booking->booking_status] . "</td>";
                } else {
                    $content .= "<td>" . $EM_Booking->booking_status . "</td>";
                }

                $bookings_total++;
                if ($EM_Booking->booking_status == 1) {
                    $bookings_approved_total++;
                }

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
                                foreach( $attendee_data as $field_label => $field_value) {
                                    $label = apply_filters('translate_text', $field_label, 'ru');
                                    if ($label == 'Имя (на английском)') {
                                        $first_name = $field_value;
                                    }
                                    if ($label == 'Фамилия (на английском)') {
                                        $last_name = $field_value;
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
                                                $adults_total++;
                                                if ($EM_Booking->booking_status == 1) {
                                                    $adults_approved_total++;
                                                }
                                                $adults_num++;
                                            } else {
                                                $children_total++;
                                                if ($EM_Booking->booking_status == 1) {
                                                    $children_approved_total++;
                                                }
                                                $children_num++;
                                            }
                                        }
                                    }
                                }
                                $names[] = trim($first_name . ' ' . $last_name);
                            }
                        }
                    }
                }

                if ($children_num > 0) {
                    $content .= "<td>" . $adults_num . " + " . $children_num . "</td>";
                } else {
                    $content .= "<td>" . $adults_num . "</td>";
                }

                $EM_Person = $EM_Booking->get_person();
                $content .= "<td>" . implode(', ', $names) . "</td>";

                $emails = array($EM_Person->user_email);
                if (array_key_exists('additional_emails', $EM_Booking->booking_meta['booking'])) {
                    $additional_emails = preg_split('/[\s,]+/', $EM_Booking->booking_meta['booking']['additional_emails']);
                    foreach ($additional_emails as $email) {
                        if (!empty($email) && !in_array($email, $emails)) {
                            $emails[] = $email;
                        }
                    }
                }

                $content .= "<td>" . implode(", ", $emails) . "</td>";
                $content .= "<td>" . $EM_Person->phone . "</td>";
                foreach ($EM_Form->form_fields as $fieldid => $field) {
                    if (in_array($fieldid, $hidden_fields)) continue;
                    $content .= "<td>";
                    if (array_key_exists($fieldid, $EM_Booking->booking_meta['booking'])) {
                        $value = $EM_Form->get_formatted_value($field, $EM_Booking->booking_meta['booking'][$fieldid]);
                        if (is_string($value) && str_starts_with($value, '[:')) {
                            $value = apply_filters('translate_text', $value, 'ru');
                        }
                        $content .= strval($value);
                    }
                    $content .= "</td>";
                }
                $content .= "</tr>\n";
            }
            $content .= "</table>\n</div>\n";

            $content .= "<br><h3>Итого</h3>\n";
            $content .= "<table class=\"summary-table\" style=\"max-width: 60em;\">\n";
            $content .= "<tr><th colspan=\"3\">Заполнено</th><th colspan=\"3\">Оплачено</th></tr>\n";
            $content .= "<tr><th>Регистрации</th><th>Взрослые</th><th>Дети</th><th>Регистрации</th><th>Взрослые</th><th>Дети</th></tr>\n";
            $content .= "<tr><td>" . $bookings_total . "</td><td>" . $adults_total . "</td><td>" . $children_total . "</td><td>" . $bookings_approved_total . "</td><td>" . $adults_approved_total . "</td><td>" . $children_approved_total . "</td></tr>\n";
            $content .= "</table>\n<br>";

            echo $content;
            exit;
        }
    }

    public static function booking_summary() {
        ?>
        <div class='em-bookings-summary'>
        <?php
        if (empty($_REQUEST['booking_id']) || empty($_REQUEST['secret'])) {
            return;
        }

        $EM_Booking = em_get_booking($_REQUEST['booking_id']);

        $admin_secret = false;
        $file = @fopen(WP_PLUGIN_DIR.'/events-manager-secrets/admin.txt', 'r');
        if ($file) {
            $admin_secret = fgets($file, 1024);
            if ($admin_secret !== false) {
                $admin_secret = str_replace("\n", '', $admin_secret);
                $admin_secret = str_replace("\r", '', $admin_secret);
            }
        }

        if (($_REQUEST['secret'] != self::get_secret($EM_Booking)) && ($_REQUEST['secret'] != $admin_secret)) {
            return;
        }

        self::update_booking($EM_Booking);

        $user_email = $EM_Booking->person->user_email;
        $attendees_data = EM_Attendees_Form::get_booking_attendees($EM_Booking);
        $names = array();
        if (array_key_exists('additional_emails', $EM_Booking->booking_meta['booking'])) {
            $additional_emails = trim($EM_Booking->booking_meta['booking']['additional_emails']);
            $additional_emails = preg_replace('/[\s,]+/', ', ', $additional_emails);
            if (!empty($additional_emails)) {
                $user_email .= ', ' . $additional_emails;
            }

            foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
                if (($EM_Ticket_Booking->get_price() >= 0) && ($EM_Ticket_Booking->get_price() < 1)) {
                    if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
                        foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
                            $first_name = '';
                            $last_name = '';
                            foreach( $attendee_data as $attendee_label => $attendee_value) {
                                $label = apply_filters('translate_text', $attendee_label, 'ru');
                                if (str_contains($label, 'Имя')) {
                                    $first_name = trim($attendee_value);
                                }
                                if (str_contains($label, 'Фамилия')) {
                                    $last_name = trim($attendee_value);
                                }
                            }
                            if (!empty($first_name) && !empty($last_name)) {
                                $names[] = $first_name . ' ' . $last_name;
                            }
                        }
                    }
                }
            }
        }

        if (empty($names)) {
            $user_name = $EM_Booking->get_person()->get_name();
        } else {
            $user_name = implode(', ', $names);
        }

        ?>
        <h3>[:en]Booking #[:ru]СТАТУС РЕГИСТРАЦИИ[:he]פרטי ההזמנה[:]</h3>
        <table id="booking-table">
        <tr><th>[:en]Name[:ru]Имя[:he]שם[:]</th><td><?php echo $user_name ?></td></tr>
        <tr><th>[:en]E-mail[:ru]E-mail[:he]דוא"ל[:]</th><td><?php echo $user_email ?></td></tr>
        <tr><th>[:en]Booking #[:ru]Номер заказа[:he]הזמנה מס'[:]</th><td><?php echo $_REQUEST['booking_id'] ?></td></tr>
        <tr id="booking-status"><th>[:en]Status[:ru]Статус[:he]סטטוס[:]</th><td><?php
        switch ($EM_Booking->booking_status) {
            case 0:
                echo '[:en]Pending[:ru]Проверяется[:he]בבדיקה[:]';
                break;
            case 1:
                echo '[:en]Approved[:ru]Оплачена[:he]שולמה[:]';
                break;
            case 5:
                $total_paid = (int)$EM_Booking->get_total_paid();
                if ($total_paid > 0) {
                    echo '[:en]Awaiting Payment Completion[:ru]Ожидает завершения оплаты[:he]מחכה להשלמת התשלום[:]';
                    echo '<br>([:ru]оплачено[:he]שולמו[:] ' . $total_paid . ' [:ru]из[:he]מתוך[:] ' . $EM_Booking->get_price() . ' &#8362;)';
                } else {
                    echo '[:en]Awaiting Payment[:ru]Ожидает оплаты[:he]מחכה לתשלום[:]';
                }
                break;
            case 6:
                echo '[:en]No Payment[:ru]Не оплачена[:he]לא שולמה[:]';
                break;
            case 7:
                echo '[:en]Not Fully Paid[:ru]Не полностью оплачена[:he]לא שולמה במלואה[:]';
                $total_paid = (int)$EM_Booking->get_total_paid();
                if ($total_paid > 0) {
                    echo '<br>([:ru]оплачено[:he]שולמו[:] ' . $total_paid . ' [:ru]из[:he]מתוך[:] ' . $EM_Booking->get_price() . ' &#8362;)';
                }
                break;
            case 8:
                echo '[:en]Waiting List[:ru]Лист ожидания[:he]רשימת המתנה[:]';
                break;
            default:
                echo '[:en]Cancelled[:ru]Отменена[:he]מבוטלת[:]';
        }
        ?></td></tr>
        <?php
        $tickets = array();
        $discounts = array();
        $i = 0;
        foreach($EM_Booking->get_tickets_bookings() as $EM_Ticket_Booking) {
            $i += 1;
            if ($EM_Ticket_Booking->get_price() >= 1) {
                $tickets[$EM_Ticket_Booking->get_price() * 1000 + $i] = $EM_Ticket_Booking;
            } else if ($EM_Ticket_Booking->get_price() < 0) {
                $discounts[-$EM_Ticket_Booking->get_price() * 1000 + $i] = $EM_Ticket_Booking;
            }
        }
        $admin_discount = floor($EM_Booking->get_price_discounts_amount('post'));
        krsort($tickets);
        krsort($discounts);

        self::calculate_participants($EM_Booking);
        if ((self::$adult_num > 0) || (self::$child_num > 0)) {
            ?>
            <tr><th colspan="2"><h4>[:en]Number of participants[:ru]Количество участников[:he]כמות משתתפים[:]</h4></th></tr>
            <tr>
                <th>[:en]Adults and teenagers (12 years and older)[:ru]Взрослые и подростки (от 12 лет и старше)[:he]מבוגרים וילדים בני 12 ומעלה[:]</th>
                <td><?php echo strval(self::$adult_num) ?></td>
            </tr>
            <tr>
                <th>[:en]Children (from 2 to 11 years)[:ru]Дети от 2 до 11 лет[:he]ילדים מגיל 2 עד 11 (כולל)[:]</th>
                <td><?php echo strval(self::$child_num) ?></td>
            </tr>
            <?php
            if (self::$toddler_num > 0) {
                ?>
                <tr>
                    <th>[:en]Toddlers (up to 2 years)[:ru]Младенцы до 2 лет[:he]תינוקות עד גיל 2[:]</th>
                    <td><?php echo strval(self::$toddler_num) ?></td>
                </tr>
                <?php
            }
        }

        /*
        if (array_key_exists('room_type', $EM_Booking->booking_meta['booking'])) {
            ?>
                <tr><th colspan="2"><h4>[:en]Registration Type[:ru]Вид регистрации[:he]סוג הרשמה[:]</h4></th></tr>
            <?php
            $room_type = $EM_Booking->booking_meta['booking']['room_type'];
            if ($room_type != 'N/A') {
            ?>
                <tr>
                    <th>[:en]Registration[:ru]Регистрация[:he]הרשמה[:]</th>
                    <td>[:en]with accomodation[:ru]с проживанием[:he]עם לינה[:]</td>
                </tr>
                <tr>
                    <th>[:en]Room type[:ru]Тип номера[:he]סוג חדר[:]</th>
                    <td><?php echo $room_type; ?></td>
                </tr>
            <?php
            } else {
            ?>
                <tr>
                    <th>[:en]Registration[:ru]Регистрация[:he]הרשמה[:]</th>
                    <td>[:en]without accomodation[:ru]без проживания[:he]ללא לינה[:]</td>
                </tr>
            <?php
            }
        }
        */

        if (!empty($tickets)) {
        ?>
            <tr><th colspan="2"><h4>[:en]Participation Fee[:ru]Стоимость участия[:he]מחיר השתתפות[:]</h4></th></tr>
        <?php
            foreach ($tickets as $idx => $ticket) {
                ?>
                <tr>
                    <th><?php if (!empty($ticket->get_ticket()->ticket_description)) { echo $ticket->get_ticket()->ticket_description; } else { echo $ticket->get_ticket()->name; } ?></th>
                    <td>
                    <?php
                    $price = $ticket->get_price();
                    $price = floor($price);
                    if ($ticket->get_spaces() == 1) {
                        echo $price . ' &#8362;';
                    } else {
                        // $space_price = floor($ticket->get_ticket()->get_price_without_tax());
                        $space_price = $price / $ticket->get_spaces();
                        echo $ticket->get_spaces() . ' * ' . $space_price . ' = ' . $price . ' &#8362;';
                    }
                    ?>
                    </td>
                </tr>
                <?php
            }

            if (!empty($discounts)) {
                foreach ($discounts as $idx => $ticket) {
                    ?>
                    <tr>
                        <th><?php if (!empty($ticket->get_ticket()->ticket_description)) { echo $ticket->get_ticket()->ticket_description; } else { echo $ticket->get_ticket()->name; } ?></th>
                        <td>
                        <?php
                        $price = $ticket->get_price();
                        $price = floor($price);
                        if ($ticket->get_spaces() == 1) {
                            echo $price . ' &#8362;';
                        } else {
                            // $space_price = floor($ticket->get_ticket()->get_price_without_tax());
                            $space_price = $price / $ticket->get_spaces();
                            echo $ticket->get_spaces() . ' * ' . $space_price . ' = ' . $price . ' &#8362;';
                        }
                        ?>
                        </td>
                    </tr>
                    <?php
                }
            }

            if ($admin_discount > 0) {
            ?>
                <tr><th>[:en]Admin discount[:ru]Дополнительная скидка[:he]הנחה מיוחדת[:]</th><td> -<?php echo $admin_discount . ' &#8362;' ?></td></tr>
            <?php
            }

            $price = $EM_Booking->get_price();
            $price = floor($price);
            if ((count($tickets) > 1) || !empty($discounts)) {
            ?>
                <tr><th>[:en]Total[:ru]Итого[:he]סה&quot;כ[:]</th><td> <?php echo $price . ' &#8362;' ?></td></tr>
            <?php
            }
        }
        ?>
        </table>
        &nbsp;<br>
        </div>
        <?php

        if ($EM_Booking->booking_status == 0) {
        ?>
            <div class="info">[:ru]Ваш заказ проверяется волонтерами организационного комитета. По окончании проверки вы получите мейл с линком на страницу оплаты.[:he]הזמנתכם נבדקת על ידי מתנדבים של הוועדה המארגנת. בתום הבדיקה ישלח דוא"ל עם לינק לתשלום.[:]</div>
        <?php
        }

        if ($EM_Booking->booking_status == 1) {
        ?>
            <div class="info">[:ru]Ваша регистрация завершена. До встречи на фестивале![:he]הרשמתכם הושלמה. נתראה בקרוב בפסטיבל![:]</div>
        <?php
        }

        if ($EM_Booking->booking_status == 8) {
        ?>
            <div class="info">[:ru]В связи с большим количеством поступивших заявок, места с проживанием закончились. Ваш заказ переведён в лист ожидания. Мы свяжемся с вами, если у нас освободятся места.[:he]עקב ביקוש רב המקומות עם לינה אזלו והרשמתכם הועברה לרשימת המתנה. ניצור אתכם קשר במידה והמקומות יתפנו.[:]</div>
        <?php
        }

        if ($EM_Booking->booking_status == 5) {
        ?>
            <a id="payment-link"></a>
            <h3>[:ru]ОПЛАТА РЕГИСТРАЦИИ[:he]תשלום עבור הזמנה[:]</h3>
            <div id="payment-buttons-container">
        <?php
            if (self::$partial_payment) {
        ?>
                <p>[:ru]Для вашего удобства, имеется возможность частичной оплаты заказа. Например, каждый участник может оплатить свою часть стоимости заказа.[:he]לנוחיותכם/ן, ניתנת האפשרות לביצוע תשלום חלקי. למשל כל משתתף/ת יכול/ה לשלם את חלקו/ה בהזמנה.[:]</p>
                <p>[:ru]Для частичной оплаты заказа измените сумму оплаты, прежде чем нажать на одну из следующих кнопок. По окончании оплаты перешлите <a href="<?php echo self::get_payment_link($EM_Booking) ?>">линк на эту страницу</a> другим участникам - чтобы они оплатили свою часть заказа. Обратите внимание, что полную оплату заказа необходимо произвести в течение 48 часов.[:he]לביצוע תשלום חלקי, יש לשנות את סכום התשלום לפני לחיצה על כפתור התשלום. לאחר התשלום יש להעביר את <a href="<?php echo self::get_payment_link($EM_Booking) ?>">הקישור לדף זה</a> לשער המשתתפים/ות – על מנת שיסדירו את התשלום עבור חלקם/ן בהזמנה. שימו לב כי התשלום המלא עבור ההזמנה חייב להתבצע תוך 48 שעות.[:]</p>
        <?php
                if (get_option('dbem_payment_provider') == "paypal") {
                    EM_Limmud_Paypal::show_buttons($EM_Booking, true, !empty($_REQUEST['scroll']));
                }
                if (get_option('dbem_payment_provider') == "payplus") {
                    EM_Limmud_Payplus::show_buttons($EM_Booking, true, !empty($_REQUEST['scroll']));
                }
                /*
                if (get_option('dbem_payment_provider') == "paid") {
                    EM_Limmud_Paid::show_buttons($EM_Booking, true, !empty($_REQUEST['scroll']));
                }
                */
            } else {
        ?>
                <p>[:ru]Для оплаты регистрации нажмите на одну из следующих кнопок[:he]לתשלום עבור הזמנה לחצו על אחד מכפתורים הבאים[:]:</p>
        <?php
                if (get_option('dbem_payment_provider') == "paypal") {
                    EM_Limmud_Paypal::show_buttons($EM_Booking, false, !empty($_REQUEST['scroll']));
                }
                if (get_option('dbem_payment_provider') == "payplus") {
                    EM_Limmud_Payplus::show_buttons($EM_Booking, false, !empty($_REQUEST['scroll']));
                }
                /*
                if (get_option('dbem_payment_provider') == "paid") {
                    EM_Limmud_Paid::show_buttons($EM_Booking, false, !empty($_REQUEST['scroll']));
                }
                */
            }
        ?>
            </div>
        <?php
            if (get_option('dbem_payment_provider') == "paypal") {
        ?>
            <div id="payment-authorize-container" style="display: none">
                <p>[:ru]Авторизация платежа[:he]מקבל אסמכתא לתשלום[:]</p>
                <div id="paypal-spinner-container" class="spinner"></div>
                <style>
                    .spinner {
                      border: 12px solid #f3f3f3;
                      border-top: 12px solid #ffc133; /* #3498db */
                      border-radius: 50%;
                      width: 120px;
                      height: 120px;
                      animation: spin 2s linear infinite;
                    }

                    @keyframes spin {
                      0% { transform: rotate(0deg); }
                      100% { transform: rotate(360deg); }
                    }
                </style>
            </div>
            <div id="payment-impossible-container" class="em-booking-message-error em-booking-message" style="display: none">
                <p>[:ru]Платеж не возможен[:he]לא ניתן לבצע תשלום[:]</p>
            </div>
            <div id="payment-failed-container" class="em-booking-message-error em-booking-message" style="display: none">
                <p>[:ru]Платеж не прошел[:he]תשלום לא עבר[:]</p>
            </div>
            <div id="payment-success-container" class="em-booking-message-success em-booking-message" style="display: none">
                <p>[:ru]Платеж подтвержден[:he]תשלום בוצע[:]</p>
            </div>
        <?php
            }
        }
    }

    public static $ticket_added;
    public static $ticket_error;
    public static function add_ticket(&$EM_Booking, $ticket_id, $spaces) {
        $args = array('ticket_id'=>$ticket_id, 'ticket_booking_spaces'=> $spaces, 'booking_id'=>$EM_Booking->booking_id);
        if ($EM_Booking->get_event()->get_bookings()->ticket_exists($ticket_id)) {
            $EM_Ticket_Booking = new EM_Ticket_Booking($args);
            $EM_Ticket_Booking->booking = $EM_Booking;
            if (!$EM_Booking->tickets_bookings->add($EM_Ticket_Booking, true)) {
                $EM_Booking->add_error($EM_Booking->tickets_bookings->get_errors());
                self::$ticket_error = true;
            } else {
                self::$ticket_added = true;
            }
        }
    }

    public static $adult_num;
    public static $child_num;
    public static $toddler_num;
    public static $partial_payment;
    public static $presenter_num;
    public static $volunteer_num;
    public static $organizer_num;
    public static $vip_num;
    public static $promo_num;
    // calculate number of participants
    public static function calculate_participants($EM_Booking, $child_min_age=2, $adult_min_age=12) {
        self::$adult_num = 0;
        self::$child_num = 0;
        self::$toddler_num = 0;
        self::$partial_payment = false;
        self::$presenter_num = 0;
        self::$volunteer_num = 0;
        self::$organizer_num = 0;
        self::$vip_num = 0;
        self::$promo_num = 0;
        $last_name = '';
        $event_date = date("U", $EM_Booking->get_event()->start()->getTimestamp());
        $attendees_data = EM_Attendees_Form::get_booking_attendees($EM_Booking);
        foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
            if (($EM_Ticket_Booking->get_price() >= 0) && ($EM_Ticket_Booking->get_price() < 1)) {
                if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
                    foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
                        $age = 0;
                        $role = '';
                        $vip = false;
                        $promo = false;
                        foreach( $attendee_data as $attendee_label => $attendee_value) {
                            $label = apply_filters('translate_text', $attendee_label, 'ru');
                            if ($label == 'Дата рождения') {
                                $birth_date = explode('/', $attendee_value);
                                if (count($birth_date) == 3) {
                                    // get age from birthdate in DD/MM/YYYY format
                                    $age = (date("md", date("U", mktime(0, 0, 0, $birth_date[1], $birth_date[0], $birth_date[2]))) > date("md", $event_date)
                                        ? ((date("Y", $event_date) - $birth_date[2]) - 1)
                                        : (date("Y", $event_date) - $birth_date[2]));
                                }
                            }
                            if ($label == 'Участвует в качестве') {
                                $role = apply_filters('translate_text', $attendee_value, 'ru');
                            }
                            if ($label == 'Секретный код') {
                                if ($attendee_value[0] == '3') {
                                    $vip = true;
                                }
                                if ($attendee_value[0] == '5') {
                                    $promo = true;
                                }
                            }
                        }

                        if ($age == 0) {
                            continue;
                        }

                        if ($age >= $adult_min_age) {
                            self::$adult_num++;
                        } else {
                            if ($age >= $child_min_age) {
                                self::$child_num++;
                            } else {
                                self::$toddler_num++;
                            }
                        }

                        if ($vip) {
                            self::$vip_num++;
                        } elseif ($promo) {
                            self::$promo_num++;
                        } else {
                            if ($role == 'волонтер') {
                                self::$volunteer_num++;
                            }
                            if ($role == 'презентер') {
                                self::$presenter_num++;
                            }
                            if ($role == 'организатор') {
                                self::$organizer_num++;
                            }
                        }
                    }
                }
            }
        }

        // some people don't want to disclose their age and therefore are counted as "toddlers"
        if ((self::$adult_num == 0) && (self::$child_num == 0) && (self::$toddler_num > 0)) {
            self::$adult_num = self::$toddler_num;
            self::$toddler_num = 0;
        }

        if (self::$adult_num + self::$child_num > 0) {
            self::$partial_payment = true;
        } else {
            self::$partial_payment = false;
        }
    }

    // fill booking with real tickets
    public static function update_booking(&$EM_Booking) {
        // booking must be in 'Pending' status
        if ($EM_Booking->booking_status != 0) {
            return;
        }

        // booking must not have any real tickets
        foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
            if ($EM_Ticket_Booking->get_price() >= 1) {
                return;
            }
        }

        // calculate number of adult and child participants
        $adult_min_age = 12;
        if ($EM_Booking->event_id == 28) {
            // 2025 registration for Limmud Camp
            $child_min_age = 5;
        } else {
            $child_min_age = 2;
        }

        self::calculate_participants($EM_Booking, $child_min_age, $adult_min_age);

        if (self::$adult_num + self::$child_num == 0) {
            return;
        }

        self::$ticket_added = false;
        self::$ticket_error = false;

        if ($EM_Booking->event_id == 22) {
            // regular 2023 registration
            /*
            $room_type = $EM_Booking->booking_meta['booking']['room_type'];
            if ($room_type == "N/A") {
                return;
            }
            */

            if (self::$child_num == 0) {
                if (self::$adult_num == 1) {
                    $room_ticket = 293;
                } elseif (self::$adult_num == 2) {
                    $room_ticket = 285;
                } elseif (self::$adult_num == 3) {
                    $room_ticket = 286;
                } else {
                    return;
                }
            } else if (self::$adult_num == 2) {
                if (self::$child_num == 1) {
                    $room_ticket = 287;
                } elseif (self::$child_num == 2) {
                    $room_ticket = 288;
                } elseif (self::$child_num == 3) {
                    $room_ticket = 289;
                } else {
                    return;
                }
            } else if (self::$adult_num == 1) {
                if (self::$child_num == 1) {
                    $room_ticket = 290;
                } elseif (self::$child_num == 2) {
                    $room_ticket = 291;
                } elseif (self::$child_num == 3) {
                    $room_ticket = 292;
                } else {
                    return;
                }
            } else {
                return;
            }

            self::add_ticket($EM_Booking, $room_ticket, 1);

            $bus_needed = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['bus_needed'], 'ru');
            if (($bus_needed != 'не нужна') && ($bus_needed != 'N/A')) {
                $bus_ticket = 294;
                self::add_ticket($EM_Booking, $bus_ticket, self::$adult_num + self::$child_num);
            }
        }

        if ($EM_Booking->event_id == 23) {
            // no accomodation 2023 registration
            $ticket_type = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['ticket_type'], 'ru');
            $tickets_num = self::$adult_num + self::$child_num;
            if ($tickets_num > 0) {
                if ($ticket_type == 'все дни') {
                    self::add_ticket($EM_Booking, 296, $tickets_num);
                } else {
                    self::add_ticket($EM_Booking, 297, $tickets_num);
                }
                /*
                // last day - ticket w/o meal
                self::add_ticket($EM_Booking, 283, $tickets_num);
                */
            }
        }

        if ($EM_Booking->event_id == 24) {
            // 2023 registration for volunteers and presenters
            $participation_type = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['participation_type'], 'ru');
            $discount_promo_ticket = 0;
            if ($participation_type == 'с проживанием') {
                /*
                $room_type = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['room_type'], 'ru');
                */
                $discount_ticket = 309;
                $discount_organizer_ticket = 316;
                $discount_vip_ticket = 0;
                if (self::$child_num == 0) {
                    if (self::$adult_num == 1) {
                        $room_ticket = 307;
                        $discount_ticket = 0;
                        $discount_organizer_ticket = 0;
                        $discount_vip_ticket = 320;
                    } elseif (self::$adult_num == 2) {
                        $room_ticket = 299;
                        $discount_vip_ticket = 318;
                    } elseif (self::$adult_num == 3) {
                        $room_ticket = 300;
                        $discount_ticket = 310;
                        $discount_organizer_ticket = 317;
                        $discount_vip_ticket = 319;
                    } else {
                        return;
                    }
                } else if (self::$adult_num == 2) {
                    if (self::$child_num == 1) {
                        $room_ticket = 301;
                    } elseif (self::$child_num == 2) {
                        $room_ticket = 302;
                    } elseif (self::$child_num == 3) {
                        $room_ticket = 303;
                    } else {
                        return;
                    }
                } else if (self::$adult_num == 1) {
                    if (self::$child_num == 1) {
                        $room_ticket = 304;
                    } elseif (self::$child_num == 2) {
                        $room_ticket = 305;
                    } elseif (self::$child_num == 3) {
                        $room_ticket = 306;
                    } else {
                        return;
                    }
                } else {
                    return;
                }

                self::add_ticket($EM_Booking, $room_ticket, 1);

                $bus_needed = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['bus_needed'], 'ru');
                if (($bus_needed != 'не нужна') && ($bus_needed != 'N/A')) {
                    $bus_ticket = 308;
                    self::add_ticket($EM_Booking, $bus_ticket, self::$adult_num + self::$child_num);
                }
            } else {
                $ticket_days = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['ticket_days'], 'ru');
                $no_accomodation_ticket = 312;
                $discount_ticket = 314;
                $discount_organizer_ticket = 0;
                $discount_vip_ticket = 321;
                $tickets_num = self::$adult_num + self::$child_num;
                if ($tickets_num > 0) {
                    if ($ticket_days != 'три дня') {
                        $no_accomodation_ticket = 313;
                        $discount_ticket = 315;
                        $discount_organizer_ticket = 0;
                        $discount_vip_ticket = 315;
                    }
                    self::add_ticket($EM_Booking, $no_accomodation_ticket, $tickets_num);
                }
            }

            $discount_num = self::$volunteer_num + self::$presenter_num;
            if (($discount_num > 0) && ($discount_ticket > 0)) {
                self::add_ticket($EM_Booking, $discount_ticket, $discount_num);
            }
            if ((self::$organizer_num > 0) && ($discount_organizer_ticket > 0)) {
                self::add_ticket($EM_Booking, $discount_organizer_ticket, self::$organizer_num);
            }
            if ((self::$vip_num > 0) && ($discount_vip_ticket > 0)) {
                self::add_ticket($EM_Booking, $discount_vip_ticket, self::$vip_num);
            }
            if ((self::$promo_num > 0) && ($discount_promo_ticket > 0)) {
                self::add_ticket($EM_Booking, $discount_promo_ticket, self::$promo_num);
            }

            $bus_needed = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['bus_needed'], 'ru');
            if (($bus_needed != 'не нужна') && ($bus_needed != 'N/A')) {
                $bus_ticket = 308;
                $discount_bus_ticket = 311;
                /*
                if (str_contains($bus_needed, 'Хайфа')) {
                    $bus_ticket = 266;
                    $discount_bus_ticket = 270;
                }
                */

                self::add_ticket($EM_Booking, $bus_ticket, self::$adult_num + self::$child_num);

                $discount_num = self::$volunteer_num + self::$presenter_num + self::$organizer_num + self::$vip_num;
                if ($discount_num > 0) {
                    self::add_ticket($EM_Booking, $discount_bus_ticket, $discount_num);
                }
            }
        }

        if ($EM_Booking->event_id == 25) {
            // limmud 2023 friends meetup registration
            $donation = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['donation'], 'ru');
            $donation = preg_replace('/[^0-9]+/', '', $donation);
            if (!empty($donation)) {
                self::add_ticket($EM_Booking, 323, intval($donation) / 50);
            }
        }

        if ($EM_Booking->event_id == 26) {
            // 2024 one day registration
            $tickets_num = self::$adult_num + self::$child_num;
            if ($tickets_num > 0) {
                self::add_ticket($EM_Booking, 326, $tickets_num);
            }
        }

        if ($EM_Booking->event_id == 27) {
            // 2024 registration for volunteers
            $tickets_num = self::$adult_num + self::$child_num;
            if ($tickets_num > 0) {
                self::add_ticket($EM_Booking, 328, $tickets_num);
            }

            $discount_num = self::$volunteer_num + self::$presenter_num;
            if ($discount_num > 0) {
                self::add_ticket($EM_Booking, 329, $discount_num);
            }
        }

        if ($EM_Booking->event_id == 28) {
            // 2025 registration for Limmud Camp
            $tickets_num = self::$adult_num + self::$child_num;
            if ($tickets_num > 0) {
                self::add_ticket($EM_Booking, 331, $tickets_num);

                $transport = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['transport'], 'ru');
                if ($transport == 'да, заинтересован') {
                    self::add_ticket($EM_Booking, 332, $tickets_num);
                }
            }
        }

        if (self::$ticket_added && !self::$ticket_error) {
            $EM_Booking->booking_status = 5;
            $EM_Booking->add_note('Awaiting Payment');
            $EM_Booking->save(false);

            $price = $EM_Booking->get_price();
            $price = floor($price);
            if ($price == 0) {
                $tickets_found = false;
                foreach($EM_Booking->get_tickets_bookings() as $EM_Ticket_Booking) {
                    if ($EM_Ticket_Booking->get_price() >= 1) {
                        $tickets_found = true;
                    }
                }
                if ($tickets_found) {
                    $EM_Booking->booking_status = 1;
                    $EM_Booking->add_note('Approved');
                    $EM_Booking->save();
                }
            } else {
                $EM_Event = $EM_Booking->get_event();
                list($waiting_list_status, $waiting_list_limits) = EM_Limmud_Misc::check_waiting_list($EM_Event);
                if ($waiting_list_status == 0) {
                    $EM_Booking->booking_status = 8;
                    $EM_Booking->add_note('Waiting List');
                    $EM_Booking->save();
                }
            }
        } elseif (self::$ticket_error) {
            $EM_Booking->save(false);
        }
    }
}

EM_Limmud_Booking::init();
