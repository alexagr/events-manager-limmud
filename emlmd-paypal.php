<?php

class EM_Limmud_Paypal {
    public static function init() {
        add_action('init',array(__CLASS__,'init_actions'), 10);
    }
    
    public static function init_actions() {
        if (!empty($_REQUEST['action']) && ($_REQUEST['action']  == 'paypal-create-transaction') && !empty($_REQUEST['booking_id'])) {
            EM_Limmud_Paypal::create_transaction($_REQUEST['booking_id'], $_REQUEST['transaction_sum']);
            die(); 
        }
        if (!empty($_REQUEST['action']) && ($_REQUEST['action']  == 'paypal-capture-transaction') && !empty($_REQUEST['order_id'])) {
            EM_Limmud_Paypal::capture_transaction($_REQUEST['order_id']);
            die(); 
        }
    }

    public static function get_total_paid($EM_Booking) {
        global $wpdb;
        if( EM_MS_GLOBAL ){
            $prefix = $wpdb->base_prefix;
        }else{
            $prefix = $wpdb->prefix;
        }
        $table_transaction = $prefix.'em_transactions';

        $total = $wpdb->get_var('SELECT SUM(transaction_total_amount) FROM '.EM_TRANSACTIONS_TABLE." WHERE booking_id={$EM_Booking->booking_id}");
        return (int)$total;
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
    	$my_booking_summary_page_id = get_option('dbem_booking_summary_page');
		if ($my_booking_summary_page_id != 0) {
			$link = get_post_permalink($my_booking_summary_page_id) . '&booking_id=' . $EM_Booking->booking_id . '&secret=' . self::get_secret($EM_Booking);
		}
        return $link;
    }

    public static function create_transaction($booking_id, $transaction_sum)
    {
        $EM_Booking = em_get_booking($booking_id);
        if ($EM_Booking->booking_status != 5) {
            echo '{"order_id":""}';
            return;        
        }        

        $full_payment = false;
        if (empty($transaction_sum) || ($EM_Booking->get_price() == $transaction_sum)) {
            $full_payment = true;
        }
        if (self::get_total_paid($EM_Booking) > 0) {
            $full_payment = false;
            if (empty($transaction_sum)) {
                $transaction_sum = $EM_Booking->get_price();
            }
        }         

        $payment_reversed = 0;
        foreach ($EM_Booking->get_notes() as $note) {
            if ($note['note'] == 'Payment Reversed') {
                $payment_reversed++;
            }
        }

        if ($full_payment) {
            $tickets = array();
            $i = 0;
            $discount = $EM_Booking->get_price_discounts_amount('post');
            foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
                $i += 1;
                if ($EM_Ticket_Booking->get_price() >= 0) {
                	if ($EM_Ticket_Booking->get_price() >= 1) {
                    	$tickets[$EM_Ticket_Booking->get_price() * 1000 + $i] = $EM_Ticket_Booking;
    				} 
                } else {
                    $discount += -$EM_Ticket_Booking->get_price();
    			}
            }
            krsort($tickets);
    
            $price = floor($EM_Booking->get_price());
            $invoice_id = 'LIMMUD-REG#' . $EM_Booking->booking_id;
			
            if ($payment_reversed > 0) {
                $invoice_id = $invoice_id . '#' . strval($payment_reversed);
            }
			
        } else {
            $tickets = array();
            $price = min($transaction_sum, floor($EM_Booking->get_price()) - self::get_total_paid($EM_Booking));
            $discount = 0;

            global $wpdb;
            if( EM_MS_GLOBAL ){
                $prefix = $wpdb->base_prefix;
            }else{
                $prefix = $wpdb->prefix;
            }
            $table_transaction = $prefix.'em_transactions';
    		$count = $wpdb->get_var('SELECT COUNT(*) FROM '.EM_TRANSACTIONS_TABLE." WHERE booking_id={$EM_Booking->booking_id}");

            if ($payment_reversed > 0) {
                $count = strval(intval($count) + $payment_reversed);
            }

            $invoice_id = 'LIMMUD-REG#' . $EM_Booking->booking_id . '#' . $count;
        }


        $body = array(
            'intent' => 'CAPTURE',
            'payer' => 
                array(
                    'name' =>
                        array(
                            'given_name' => $EM_Booking->get_person()->first_name,
                            'surname' => $EM_Booking->get_person()->last_name
                        ),
                    'address' =>
                        array(
                            'country_code' => 'IL'
                        )
                ),
            'application_context' =>
                array(
                    'brand_name' => 'Limmud FSU Israel',
                    'locale' => 'en-IL',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                    'payment_method' =>
                        array(
                            'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED'
                        )
                ),
            'purchase_units' =>
                array(
                    0 =>
                        array(
                            'amount' =>
                                array(
                                    'currency_code' => get_option('dbem_bookings_currency', get_option('dbem_bookings_currency', 'ILS')),
                                    'value' => strval($price),
                                    'breakdown' =>
                                        array(
                                            'item_total' =>
                                                array(
                                                    'currency_code' => get_option('dbem_bookings_currency', 'ILS'),
                                                    'value' => strval($price + $discount)
                                                )
                                        )
                                ),
                            'invoice_id' => $invoice_id
                        )
                ),
            'items' => array()
        );
        
        if ($discount > 0) {
            $body['purchase_units'][0]['amount']['breakdown']['discount'] = array(
                'currency_code' => get_option('dbem_bookings_currency', 'ILS'), 
                'value' => strval($discount)
            );
        }
        
        $ticket_num = 0;
        foreach ($tickets as $idx => $ticket) {
            $ticket_price = floor($ticket->get_price());
            $body['purchase_units'][0]['items'][$ticket_num] = array(
                'name' => $ticket->get_ticket()->name,
                'unit_amount' =>
                    array(
                        'currency_code' => get_option('dbem_bookings_currency', 'ILS'),
                        'value' => strval($ticket_price / $ticket->get_spaces())
                    ),
                'quantity' => strval($ticket->get_spaces()),
                'category' => 'DIGITAL_GOODS'
            );
            $ticket_num += 1;
        }

        if (empty($tickets)) {
            $body['purchase_units'][0]['items'][$ticket_num] = array(
                'name' => 'Registration',
                'unit_amount' =>
                    array(
                        'currency_code' => get_option('dbem_bookings_currency', 'ILS'),
                        'value' => strval($price)
                    ),
                'quantity' => '1',
                'category' => 'DIGITAL_GOODS'
            );
        }
        
        include_once( plugin_dir_path(__FILE__) . "lib/paypal-client.php" );
        $response = PayPalClient::createOrder($body);
        if (!is_null($response) && ($response->statusCode == 201)) {
            echo '{"order_id":"' . strval($response->result->id) . '"}';
        }
    }

    public static function capture_transaction($order_id)
    {
        include_once( plugin_dir_path(__FILE__) . "lib/paypal-client.php" );
        $response = PayPalClient::captureOrder($order_id);
        $result = "failed";        
        # echo json_encode($response->result, JSON_PRETTY_PRINT);
        if (!is_null($response) && (($response->statusCode == 201) || ($response->statusCode == 200))) {
            $status = $response->result->status;            
            $timestamp = $response->result->create_time;
            $id = $response->result->id;
            if ($status == 'COMPLETED') {
                foreach($response->result->purchase_units as $purchase_unit) {
                    $amount = $purchase_unit->amount->value;
                    $currency = $purchase_unit->amount->currency_code;
                    $invoice_id = $purchase_unit->invoice_id;

                    $invoice_id_values = explode('#', $invoice_id);
                    $booking_id = -1;
                    if ($invoice_id_values[0] == 'LIMMUD-REG') {
                        if (!empty($invoice_id_values[1])) {
                            $booking_id = $invoice_id_values[1];
                        } 
                    }

                    if ($booking_id >= 0) {
                        $EM_Booking = em_get_booking($booking_id);
                        if (!empty($EM_Booking->booking_id)) {
                            EM_Limmud_Paypal::record_transaction($EM_Booking, $amount, $currency, $timestamp, $id, $status);
                            $price = floor($EM_Booking->get_price());
                            $total_paid = self::get_total_paid($EM_Booking);
                            if ($total_paid >= $price) {
                                $result = "completed";                            
                                $EM_Booking->approve();
                            } else {
                                $result = "partially completed";                            

                                $msg = array( 'user'=> array('subject'=>'', 'body'=>''), 'admin'=> array('subject'=>'', 'body'=>''));
                                $msg['user']['subject'] = get_option('dbem_bookings_email_partial_payment_subject');
                                $msg['user']['body'] = get_option('dbem_bookings_email_partial_payment_body');
                                //admins should get something (if set to)
                                $msg['admin']['subject'] = get_option('dbem_bookings_contact_email_partial_payment_subject');
                                $msg['admin']['body'] = get_option('dbem_bookings_contact_email_partial_payment_body');

                                $msg['user']['body'] = str_replace('#_AMOUNT', $EM_Booking->format_price($amount), $msg['user']['body']);
                                $msg['admin']['body'] = str_replace('#_AMOUNT', $EM_Booking->format_price($amount), $msg['admin']['body']);

                                $msg['user']['body'] = str_replace('#_BOOKINGTOTALPAID', $EM_Booking->format_price($total_paid), $msg['user']['body']);
                                $msg['admin']['body'] = str_replace('#_BOOKINGTOTALPAID', $EM_Booking->format_price($total_paid), $msg['admin']['body']);
        
                                $output_type = get_option('dbem_smtp_html') ? 'html':'email';
                                if (!empty($msg['user']['subject'])) {
                                    $msg['user']['subject'] = $EM_Booking->output($msg['user']['subject'], 'raw');
                                    $msg['user']['body'] = $EM_Booking->output($msg['user']['body'], $output_type);
                                    $EM_Booking->email_send( $msg['user']['subject'], $msg['user']['body'], $EM_Booking->get_person()->user_email);
                                }
                                if (!empty($msg['admin']['subject'])) {
                    				$admin_emails = str_replace(' ','',get_option('dbem_bookings_notify_admin'));
                    				$admin_emails = apply_filters('em_booking_admin_emails', explode(',', $admin_emails), $EM_Booking); //supply emails as array
                                    $EM_Event = $EM_Booking->get_event();
                    				if( get_option('dbem_bookings_contact_email') == 1 && !empty($EM_Event->get_contact()->user_email) ){
                    				    //add event owner contact email to list of admin emails
                    				    $admin_emails[] = $EM_Event->get_contact()->user_email;
                    				}
                    				foreach($admin_emails as $key => $email){ if( !is_email($email) ) unset($admin_emails[$key]); } //remove bad emails
                    				if( !empty($admin_emails) ){
                                        $msg['admin']['subject'] = $EM_Booking->output($msg['admin']['subject'], 'raw');
                                        $msg['admin']['body'] = $EM_Booking->output($msg['admin']['body'], $output_type);
                                        $EM_Booking->email_send( $msg['admin']['subject'], $msg['admin']['body'], $admin_emails);
                                    }
                                }
                            }
                        }
                    }                    
                }
            }                
        }
        $return = array('result' => $result);        
        echo json_encode($return);        
    }

    // record transaction in Event Manager Pro table
    public static function record_transaction($EM_Booking, $amount, $currency, $timestamp, $id, $status) {
        if (!defined('EMP_VERSION')) {
            return;
        }

        global $wpdb;
        if( EM_MS_GLOBAL ){
            $prefix = $wpdb->base_prefix;
        }else{
            $prefix = $wpdb->prefix;
        }
        $table_transaction = $prefix.'em_transactions';
        
        $data = array();
        $data['booking_id'] = $EM_Booking->booking_id;
        $data['transaction_gateway_id'] = $id;
        $data['transaction_timestamp'] = $timestamp;
        $data['transaction_currency'] = $currency;
        $data['transaction_status'] = $status;
        $data['transaction_total_amount'] = $amount;
        $data['transaction_note'] = $EM_Booking->booking_id;
        $data['transaction_gateway'] = 'paypal';
        
        if( !empty($id) ){
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT transaction_id, transaction_status, transaction_gateway_id, transaction_total_amount FROM ".$table_transaction." WHERE transaction_gateway = %s AND transaction_gateway_id = %s", 'paypal', $id ) );
        }
        
        if( !empty($existing->transaction_gateway_id) && $amount == $existing->transaction_total_amount && $status != $existing->transaction_status ) {
            // Update only if txn id and amounts are the same (e.g. pending payments changing status)
            $wpdb->update( $table_transaction, $data, array('transaction_id' => $existing->transaction_id) );
        } else {
            // Insert
            $wpdb->insert( $table_transaction, $data );
        }
    }

	public static function show_buttons($EM_Booking, $partial=false) 
    {
        if ($partial) {
	?>
        <p class="input-group input-text">
          <label for="paypal-transaction-sum"><?php echo "[:ru]Сумма оплаты[:he]סכום לתשלום[:]" ?></label>
          <input type="text" id="paypal-transaction-sum" value="<?php echo floor($EM_Booking->get_price() - self::get_total_paid($EM_Booking)) ?>">
        </p>
    <?php
        } 
    ?>
        <script src="https://www.paypal.com/sdk/js?client-id=<?php 
            if (get_option('dbem_paypal_status') == "live") { 
                echo get_option('dbem_paypal_live_client_id'); 
            } else { 
                echo get_option('dbem_paypal_sandbox_client_id'); 
            } ?>&currency=<?php echo get_option('dbem_bookings_currency', 'ILS') ?>"></script>
        <div id="paypal-button-container" style="display: none; max-width: 360px;"></div>
        <script type="text/javascript">
            window.onload = function() { document.getElementById('paypal-button-container').style.display = 'block'; };
            paypal.Buttons({
                style: {
                    label: 'pay'
                },
                createOrder: function() {
    <?php if ($partial) { ?>
                    var transaction_sum = document.getElementById('paypal-transaction-sum').value;
                    if (!transaction_sum) {
                        return('{"order_id":""}');
                    }
    <?php } ?>
                    return fetch('paypal.json', {
                        method: 'post',
                        headers: {
                            'content-type': 'application/x-www-form-urlencoded'
                        },
    <?php if ($partial) { ?>
                        body: 'action=paypal-create-transaction&booking_id=<?php echo $EM_Booking->booking_id ?>&transaction_sum=' + transaction_sum
    <?php } else { ?>
                        body: 'action=paypal-create-transaction&booking_id=<?php echo $EM_Booking->booking_id ?>'
    <?php } ?>
                    }).then(function(res) {
                        return res.json();
                    }).then(function(data) {
                        if (!data.order_id) {
                            var elem = document.getElementById('payment-impossible-container');
                            elem.style.display = 'block';
                            elem.scrollIntoView();
                            setTimeout(5000, window.location.reload());
                        }
                        document.getElementById('paypal-transaction-sum').readOnly = true;
                        return data.order_id;
                    });
                },
                onApprove: function(data) {
                    document.getElementById('payment-buttons-container').style.display = 'none';
                    var elem = document.getElementById('payment-authorize-container');
                    elem.style.display = 'block';
                    elem.scrollIntoView();
                    return fetch('paypal.json', {
                        method: 'post',
                        headers: {
                            'content-type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=paypal-capture-transaction&order_id=' + data.orderID
                    }).then(function(res) {
                        return res.json();
                    }).then(function(data) {
                        document.getElementById('payment-authorize-container').style.display = 'none';
                        if (data.result == 'completed') {
                            var elem = document.getElementById('payment-success-container');
                            elem.style.display = 'block';
                            elem.scrollIntoView();
                            window.location.href = '<?php echo get_post_permalink(get_option('dbem_booking_success_page')) ?>&booking_id=<?php echo $EM_Booking->booking_id ?>&secret=<?php echo self::get_secret($EM_Booking, 'payment_success') ?>';
                        } else {
                            if (data.result == 'partially completed') {
                                var elem = document.getElementById('payment-success-container');
                                elem.style.display = 'block';
                                elem.scrollIntoView();
                                window.location.href = '<?php echo get_post_permalink(get_option('dbem_partial_payment_success_page')) ?>&booking_id=<?php echo $EM_Booking->booking_id ?>&secret=<?php echo self::get_secret($EM_Booking, 'partial_payment_success') ?>';
                            } else {
                                var elem = document.getElementById('payment-failed-container');
                                elem.style.display = 'block';
                                elem.scrollIntoView();
                                setTimeout(5000, window.location.reload());
                            }
                        }                    
                    })
                }
            }).render('#paypal-button-container');
        </script>
    <?php
    }
}

EM_Limmud_Paypal::init();