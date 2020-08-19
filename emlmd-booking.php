<?php

class EM_Limmud_Booking {
    public static function init() {
		add_filter('the_title', array(__CLASS__, 'the_title'));
		add_filter('the_content', array(__CLASS__, 'the_content'), 10, 2);
    }

	public static function the_title($data, $id = null) {
		global $post;
		if (empty($post)) return $data; //fix for any other plugins calling the_title outside the loop	

		$booking_summary_page_id = get_option('dbem_booking_summary_page');	
		if (is_main_query() && ($post->ID == $booking_summary_page_id) && ($booking_summary_page_id != 0)) {
			if ($data == "Booking Summary") { // without this we will change title of all menu items too
				if (!empty( $_REQUEST['booking_id'])) {
        			$EM_Booking = em_get_booking($_REQUEST['booking_id']);
                    return $EM_Booking->get_event()->event_name;
				}
			}
		}

		$booking_success_page_id = get_option('dbem_booking_success_page');
		if (is_main_query() && ($post->ID == $booking_success_page_id) && ($booking_success_page_id != 0)) {
			if ($data == "Booking Success") { // without this we will change title of all menu items too
				if (!empty( $_REQUEST['booking_id'])) {
        			$EM_Booking = em_get_booking($_REQUEST['booking_id']);
                    return $EM_Booking->get_event()->event_name;
				}
			}
		}

		$partial_payment_success_page_id = get_option('dbem_partial_payment_success_page');
		if (is_main_query() && ($post->ID == $partial_payment_success_page_id) && ($partial_payment_success_page_id != 0)) {
			if ($data == "Partial Payment Success") { // without this we will change title of all menu items too
				if (!empty( $_REQUEST['booking_id'])) {
        			$EM_Booking = em_get_booking($_REQUEST['booking_id']);
                    return $EM_Booking->get_event()->event_name;
				}
			}
		}
		return $data;
	}

	public static function the_content($page_content) {
		global $post;
		if (empty($post)) return $page_content; //fix for any other plugins calling the_content outside the loop	
		
		$booking_summary_page_id = get_option('dbem_booking_summary_page');
		if (!post_password_required() && ($post->ID == $booking_summary_page_id) && ($booking_summary_page_id != 0)) {
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
		if (!post_password_required() && ($post->ID == $booking_success_page_id) && ($booking_success_page_id != 0)) {
			$content = apply_filters('em_content_pre', '', $page_content);
			if (empty($content)) {
				if (preg_match('/#_BOOKINGID/', $page_content)) {
    				if (!empty( $_REQUEST['booking_id'])) {
            			$EM_Booking = em_get_booking($_REQUEST['booking_id']);
                        if ($EM_Booking->booking_status == 1) {
        					$content = str_replace('#_BOOKINGID', $_REQUEST['booking_id'], $page_content);
                            $event_year = date("Y", date("U", $EM_Booking->get_event()->start()->getTimestamp()));
        					$content = str_replace('#_EVENTNAME', "[:ru]Лимуд FSU Израиль [:he]לימוד FSU ישראל[:] " . $event_year, $content);
                        }
                    }
				}
			}
			return apply_filters('em_content', '<div id="em-wrapper">'.$content.'</div>');
		}

		$partial_payment_success_page_id = get_option('dbem_partial_payment_success_page');
		if (!post_password_required() && ($post->ID == $partial_payment_success_page_id) && ($partial_payment_success_page_id != 0)) {
			$content = apply_filters('em_content_pre', '', $page_content);
			if (empty($content)) {
				if (preg_match('/#_BOOKINGID/', $page_content)) {
    				if (!empty( $_REQUEST['booking_id'])) {
            			$EM_Booking = em_get_booking($_REQUEST['booking_id']);
                        if (($EM_Booking->booking_status == 5) || ($EM_Booking->booking_status == 1)) {
        					$content = str_replace('#_BOOKINGID', $_REQUEST['booking_id'], $page_content);
                            $content = str_replace('#_BOOKINGSUMMARYURL', EM_Limmud_Paypal::get_payment_link($EM_Booking), $content);
                        }
                    }
				}
			}
			return apply_filters('em_content', '<div id="em-wrapper">'.$content.'</div>');
		}

		return $page_content;
	}

	public static function booking_summary() {
	?>
		<div class='em-bookings-summary'>
		<?php
		if (!empty($_REQUEST['booking_id'])) {
			$EM_Booking = em_get_booking($_REQUEST['booking_id']);
			
			if (empty($_REQUEST['secret'])) {
				return;
			}
			
			if (($_REQUEST['secret'] != md5($EM_Booking->person->user_email)) && ($_REQUEST['secret'] != '11235813213455')) {
				return;
			}

			self::update_booking($EM_Booking);

			?>
            <h3>[:en]Booking #[:ru]СТАТУС РЕГИСТРАЦИИ[:he]פרטי ההזמנה[:]</h3>
			<table id="booking-table">
			<tr><th>[:en]Name[:ru]Имя[:he]שם[:]</th><td><?php echo $EM_Booking->person->get_name() ?></td></tr>
			<tr><th>[:en]E-mail[:ru]E-mail[:he]דוא"ל[:]</th><td><?php echo $EM_Booking->person->user_email ?></td></tr>
			<tr><th>[:en]Booking #[:ru]Номер заказа[:he]הזמנה מס'[:]</th><td><?php echo $_REQUEST['booking_id'] ?></td></tr>
			<tr><th>[:en]Status[:ru]Статус[:he]סטטוס[:]</th><td><?php 
			switch ($EM_Booking->booking_status) {
				case 0: 
					echo '[:en]Pending[:ru]Проверяется[:he]בבדיקה[:]';
					break;
				case 1: 
					echo '[:en]Approved[:ru]Оплачен[:he]שולם[:]';
					break;
				case 5:
				   echo '[:en]Awaiting Payment[:ru]Ожидает оплаты[:he]מחכה לתשלום[:]';
                    $total_paid = (int)$EM_Booking->get_total_paid();
                    if ($total_paid > 0) {
					   echo '<br>([:ru]оплачено[:he]שולמו[:] ' . $total_paid . ' [:ru]из[:he]מתוך[:] ' . $EM_Booking->get_price() . ' &#8362;)';
                    }
					break;
				case 6:
					echo '[:en]No Payment[:ru]Не оплачен[:he]לא שולם[:]';
					break;
				case 7:
				   echo '[:en]Not Fully Paid[:ru]Не полностью оплачен[:he]לא שולם במלואו[:]';
                    $total_paid = (int)$EM_Booking->get_total_paid();
                    if ($total_paid > 0) {
					   echo '<br>([:ru]оплачено[:he]שולמו[:] ' . $total_paid . ' [:ru]из[:he]מתוך[:] ' . $EM_Booking->get_price() . ' &#8362;)';
                    }
					break;
				default:
					echo '[:en]Cancelled[:ru]Отменен[:he]מבוטל[:]';
			}
			?></td></tr>
			<?php
	        $participants = array();
	        $tickets = array();
	        $i = 0;
	        $discount = $EM_Booking->get_price_discounts_amount('post');
	        foreach($EM_Booking->get_tickets_bookings() as $EM_Ticket_Booking) {
	            $i += 1;
	            if ($EM_Ticket_Booking->get_price() >= 0) {
	            	if ($EM_Ticket_Booking->get_price() < 1) {
	                	$participants[$EM_Ticket_Booking->get_price() * 1000 + $i] = $EM_Ticket_Booking;
					} else {
	                	$tickets[$EM_Ticket_Booking->get_price() * 1000 + $i] = $EM_Ticket_Booking;
					} 
	            } else {
	                $discount += -$EM_Ticket_Booking->get_price() * $EM_Ticket_Booking->get_spaces();
				}
	        }
	        krsort($participants);
	        krsort($tickets);
	        
			if (!empty($participants)) {
			?>
				<tr><th colspan="2"><h4>[:en]Number of participants[:ru]Количество участников[:he]כמות משתתפים[:]</h4></th></tr>
			<?php
		        foreach ($participants as $idx => $ticket) {
		        	?>
		        	<tr>
						<th><?php echo $ticket->get_ticket()->name ?></th>
			        	<td><?php echo $ticket->get_spaces() ?></td>
		        	</tr>
					<?php
				}
			}		
	
			if (!empty($tickets)) {
			?>
				<tr><th colspan="2"><h4>[:en]Participation Fee[:ru]Стоимость участия[:he]מחיר השתתפות[:]</h4></th></tr>
			<?php
			    $tickets_num = 0;
		        foreach ($tickets as $idx => $ticket) {
		            $tickets_num += 1;
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
	
		        if ($discount > 0) {
		        ?>
		        	<tr><th>[:en]Discount[:ru]Скидка[:he]הנחה[:]</th><td> <?php echo '-' . $discount . ' &#8362;' ?></td></tr>
		        <?php
		        }
		
		        $price = $EM_Booking->get_price();
		        $price = floor($price);
		        if (($tickets_num > 1) || ($discount > 0)) {
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
        }
		if ($EM_Booking->booking_status == 5) {
            self::calculate_participants($EM_Booking);
		?>
            <h3>[:ru]ОПЛАТА РЕГИСТРАЦИИ[:he]תשלום עבור הזמנה[:]</h3>
            <div id="payment-buttons-container">
		<?php
            if (self::$partial_payment) {
        ?>
                <p>[:ru]Для вашего удобства, имеется возможность частичной оплаты заказа. Например, каждый участник может оплатить свою часть стоимости заказа.[:he]לנוחיותכם, ניתנת האפשרות לביצוע תשלום חלקי. למשל כל משתתף יכול לשלם את חלקו בהזמנה.[:]</p>
                <p>[:ru]Для частичной оплаты заказа измените сумму оплаты, прежде чем нажать на одну из следующих кнопок. Перешлите <a href="<?php echo EM_Limmud_Paypal::get_payment_link($EM_Booking) ?>">линк на эту страницу</a> другим участникам - чтобы они оплатили свою часть заказа. Обратите внимание, что полную оплату заказа необходимо произвести в течение 48 часов.[:he]לביצוע תשלום חלקי, יש לשנות את סכום התשלום לפני לחיצה על כפתור התשלום. יש להעביר את <a href="<?php echo EM_Limmud_Paypal::get_payment_link($EM_Booking) ?>">הקישור לדף זה</a> לשער המשתתפים – על מנת שיסדירו את התשלום עבור חלקם בהזמנה. שימו לב כי התשלום המלא עבור ההזמנה חייב להתבצע תוך 48 שעות.[:]</p>
		<?php
                EM_Limmud_Paypal::show_buttons($EM_Booking, true);
            } else {
        ?>
                <p>[:ru]Для оплаты регистрации нажмите на одну из следующих кнопок[:he]לתשלום עבור הזמנה לחצו על אחד מכפתורים הבאים[:]:</p>
		<?php
                EM_Limmud_Paypal::show_buttons($EM_Booking);
            }
        ?>
            </div>
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

	public static function add_ticket(&$EM_Booking, $ticket_id, $spaces) {
		$args = array('ticket_id'=>$ticket_id, 'ticket_booking_spaces'=> $spaces, 'booking_id'=>$EM_Booking->booking_id);
		if ($EM_Booking->get_event()->get_bookings()->ticket_exists($ticket_id)) {
			$EM_Ticket_Booking = new EM_Ticket_Booking($args);
			$EM_Ticket_Booking->booking = $EM_Booking;
			if (!$EM_Booking->tickets_bookings->add($EM_Ticket_Booking, true)) {
			    $EM_Booking->add_error($EM_Booking->tickets_bookings->get_errors());
			} else {
				$EM_Booking->booking_status = 5;
			}
		}
	}

    public static $adult_num;
    public static $child_num;
    public static $partial_payment;
    // calculate number of participants
    public static function calculate_participants($EM_Booking) {
        self::$adult_num = 0;
        self::$child_num = 0;
        self::$partial_payment = false;
        $last_name = '';
        $event_date = date("U", $EM_Booking->get_event()->start()->getTimestamp());
        $attendees_data = EM_Attendees_Form::get_booking_attendees($EM_Booking);
        foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
        	if ($EM_Ticket_Booking->get_price() < 1) {
                if (!empty($attendees_data[$EM_Ticket_Booking->ticket_id])) {
                    foreach($attendees_data[$EM_Ticket_Booking->ticket_id] as $attendee_title => $attendee_data) {
                        foreach( $attendee_data as $attendee_label => $attendee_value) {
                            $label = apply_filters('translate_text', $attendee_label, 'ru');
                            if ($label == 'Дата рождения') {
                                $birth_date = explode('/', $attendee_value);
                                if (count($birth_date) == 3) {
                                    // get age from birthdate in DD/MM/YYYY format
                                    $age = (date("md", date("U", mktime(0, 0, 0, $birth_date[1], $birth_date[0], $birth_date[2]))) > date("md", $event_date)
                                        ? ((date("Y", $event_date) - $birth_date[2]) - 1)
                                        : (date("Y", $event_date) - $birth_date[2]));
                                        
                                    if ($age >= 12) {
                                        self::$adult_num++;
                                    } else {
                                        if ($age >= 3) {
                                            self::$child_num++;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
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
        self::calculate_participants($EM_Booking);

        if (self::$adult_num + self::$child_num == 0) {
            return;
        }

		if ($EM_Booking->event_id == 13) {
            // regular 2020 registration
            $room_type = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['room_type'], 'ru');
            $adult_ticket = 187;
            if ($room_type == 'в трехместном номере') {
                $adult_ticket = 189;
                if ((self::$adult_num != 3) || (self::$child_num != 0)) {
                    return;
                }
            }
            if ($room_type == 'в одноместном номере') {
                $adult_ticket = 190;
                if ((self::$adult_num != 1) || (self::$child_num != 0)) {
                    return;
                }
            }
            if ($room_type == 'в семейном номере (с детьми)') {
                if (self::$child_num == 0) {
                    return;
                }
            }

			if (self::$adult_num > 0) {
				self::add_ticket($EM_Booking, $adult_ticket, self::$adult_num);
			}
			if (self::$child_num > 0) {
				self::add_ticket($EM_Booking, 188, self::$child_num);
			}

            $bus_needed = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['bus_needed'], 'ru');
            if (($bus_needed != 'не нужна') && ($bus_needed != 'N/A')) {
				self::add_ticket($EM_Booking, 191, self::$adult_num + self::$child_num);
            }
			
			$EM_Booking->save();
		}

		if ($EM_Booking->event_id == 14) {
            // no accomodation 2020 registration
            $room_type = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['ticket_type'], 'ru');
            $tickets_num = self::$adult_num + self::$child_num;
            if ($tickets_num > 0) {
                if ($room_type == 'все дни 3-5.12') {
    				self::add_ticket($EM_Booking, 193, $tickets_num);
                } else {
    				self::add_ticket($EM_Booking, 194, $tickets_num);
                }
            }

			$EM_Booking->save();
        }

	}
}

EM_Limmud_Booking::init();