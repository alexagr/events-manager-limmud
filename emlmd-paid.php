<?php

class EM_Limmud_Paid {
    public static function init() {
        add_action('parse_request',array(__CLASS__,'handle_request'), 10);
    }
    
    public static function handle_request() {
        if (!empty($_REQUEST['action']) && ($_REQUEST['action']  == 'paid-generate-url') && !empty($_REQUEST['booking_id'])) {
            self::generate_url($_REQUEST['booking_id'], $_REQUEST['payment_type'], $_REQUEST['language'], $_REQUEST['transaction_sum']);
            die(); 
        }

        global $wp;
        if (str_starts_with($wp->request, 'paid-callback-status') && !empty($_REQUEST['notify_type']) && !empty($_REQUEST['transaction_id'])) {
            $transaction_id = $_REQUEST['transaction_id'];
            $transaction_id_values = explode('#', $transaction_id);
            if (substr($transaction_id_values[0], 0, 6) == 'LIMMUD') {
                $booking_id = $transaction_id_values[1];
                self::sale_callback($booking_id, $_REQUEST['notify_type'], $_REQUEST['payme_sale_id'], $_REQUEST['payme_transaction_id'], $_REQUEST['price'], $_REQUEST['currency'], $_REQUEST['sale_created']);
                die(); 
            }
        }
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
        $data['transaction_gateway'] = 'paid';
        
        if( !empty($id) ) {
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT transaction_id, transaction_status, transaction_gateway_id, transaction_total_amount FROM ".$table_transaction." WHERE transaction_gateway = %s AND transaction_gateway_id = %s", 'paid', $id ) );
        }
        
        if( !empty($existing->transaction_gateway_id) && ($id == $existing->transaction_gateway_id) && ($amount == $existing->transaction_total_amount) && ($status == $existing->transaction_status)) {
            // do not record duplicate transactions
            return;
        }

        $wpdb->insert( $table_transaction, $data );
    }

    public static function get_total_paid($EM_Booking) {
        global $wpdb;
        if( EM_MS_GLOBAL ){
            $prefix = $wpdb->base_prefix;
        }else{
            $prefix = $wpdb->prefix;
        }
        $table_transaction = $prefix.'em_transactions';

        $total = $wpdb->get_var('SELECT SUM(transaction_total_amount) FROM '.EM_TRANSACTIONS_TABLE." WHERE booking_id={$EM_Booking->booking_id} AND transaction_status='sale-complete'");
        return (int)$total;
    }

    public static function get_total_reversed($EM_Booking) {
        global $wpdb;
        if( EM_MS_GLOBAL ){
            $prefix = $wpdb->base_prefix;
        }else{
            $prefix = $wpdb->prefix;
        }
        $table_transaction = $prefix.'em_transactions';

        $total = $wpdb->get_var('SELECT SUM(transaction_total_amount) FROM '.EM_TRANSACTIONS_TABLE." WHERE booking_id={$EM_Booking->booking_id} AND transaction_status='sale-chargeback'");
        return (int)$total;
    }

    public static function generate_url($booking_id, $payment_type, $language, $transaction_sum)
    {
        $EM_Booking = em_get_booking($booking_id);
        if ($EM_Booking->booking_status != 5) {
            echo '{"order_id":""}';
            return;        
        }        

        $full_payment = false;
        if (empty($transaction_sum) || (floor($EM_Booking->get_price()) == $transaction_sum)) {
            $full_payment = true;
        }
        if (self::get_total_paid($EM_Booking) > 0) {
            $full_payment = false;
            if (empty($transaction_sum)) {
                $transaction_sum = floor($EM_Booking->get_price());
            }
        }         

        $payment_reversed = self::get_total_reversed($EM_Booking);

        $event_year = date("Y", date("U", $EM_Booking->get_event()->start()->getTimestamp()));
        $transaction_id = 'LIMMUD-' . $event_year . '#' . $EM_Booking->booking_id;;
        $order_number = strval(intval($event_year) * 1000000 + intval($EM_Booking->booking_id));

        $tickets = array();
        if ($full_payment) {
            $i = 0;
            $discount = $EM_Booking->get_price_discounts_amount('post');
            foreach($EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking) {
                $i += 1;
                if ($EM_Ticket_Booking->get_price() >= 1) {
                    $tickets[$EM_Ticket_Booking->get_price() * 1000 + $i] = $EM_Ticket_Booking;
                } else if ($EM_Ticket_Booking->get_price() < 0) {
                    $discount += -$EM_Ticket_Booking->get_price();
    			}
            }

            if ($discount > 0) {
                $tickets = array();
            } else {
                krsort($tickets);
            }
    
            $price = floor($EM_Booking->get_price());
			
            if ($payment_reversed > 0) {
                $transaction_id = $transaction_id . '#' . strval($payment_reversed);
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
            $count = intval($count) + $payment_reversed;

            $transaction_id = $transaction_id . '#' . strval($count);
        }

        if (get_option('dbem_payment_mode') == "live") { 
            $url = 'https://live.payme.io/api/generate-sale';
            $api_key = get_option('dbem_paid_live_api_key');
        } else {
            $url = 'https://sandbox.payme.io/api/generate-sale';
            $api_key = get_option('dbem_paid_sandbox_api_key');
        }

        if ($language == 'he-IL') {
            $lang = 'he';
        } else {
            $lang = 'en';
        }

        if ($payment_type == 'bit') {
            $payment_method = 'bit';
        } else {
            $payment_method = 'credit-card';
        }

        $redirect_url = get_post_permalink(get_option('dbem_payment_redirect_page')) . '&booking_id=' . $EM_Booking->booking_id . '&secret=' . EM_Limmud_Booking::get_secret($EM_Booking, 'payment_redirect');
        if ($full_payment) {
            $redirect_url = $redirect_url . '&payment_type=full';
        } else {
            $redirect_url = $redirect_url . '&payment_type=partial';
        }

        $body = array(
            'layout' => 'dynamic',
            'currency' => get_option('dbem_bookings_currency', 'ILS'),
            'language' => $lang,
            'sale_type' => 'sale',
            'sale_price' => $price * 100,
            'installments' => get_option('dbem_paid_installments', '1'),
            'product_name' => 'Limmud FSU Israel ' . $event_year,
            'capture_buyes' => false,
            'transaction_id' => $transaction_id,
            'sale_return_url' => $redirect_url,
            'seller_payme_id' => $api_key,
            'sale_callback_url' => get_site_url() . '/paid-callback-status',
            'sale_payment_method' => $payment_method,
            'sale_send_notification' => false,
            'order_number' => $order_number,
            'items' => array(),
            'buyer_name' => $EM_Booking->get_person()->first_name . ' ' . $EM_Booking->get_person()->last_name,
            'buyer_email' => $EM_Booking->get_person()->user_email
        );

        if (get_option('dbem_paid_3d_secure', 'disable') == 'enable') {
            $body['services'] = array(
                array(
                    'name' => '3DSecure',
                    'settings' => array(
                        'active' => true
                    )
                )
            );
        }

        if (empty($tickets)) {
            $body['items'][0] = array(
                'name' => 'Registration',
                'quantity' => 1,
                'unit_price' => $price * 100,
                'total' => $price * 100
            );
        } else {
            $ticket_num = 0;
            foreach ($tickets as $idx => $ticket) {
                $ticket_price = floor($ticket->get_price());
                $body['items'][$ticket_num] = array(
                    'name' => $ticket->get_ticket()->name,
                    'quantity' => $ticket->get_spaces(),
                    'unit_price' => $ticket_price * 100 / $ticket->get_spaces(),
                    'total' => $price * 100
                );
                $ticket_num += 1;
            }
        }

        $json_body = json_encode($body);

        $args = array(
            'body' => $json_body,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 10,
            'blocking' => true
        );

        $response = wp_remote_post($url, $args);
        if ( is_wp_error( $response ) ) {
            echo '{"status_code": "1", "status_error_details": "Wordpress error"}';
        } else {
            $response_body = wp_remote_retrieve_body($response);
            echo $response_body;
        }
    }

    public static function callback_status($booking_id, $notify_type, $payme_sale_id, $payme_transaction_id, $amount, $currency, $timestamp)
    {
        $result = "failed";        

        $EM_Booking = em_get_booking($booking_id);
        if (!empty($EM_Booking->booking_id)) {

            self::record_transaction($EM_Booking, $amount, $currency, $timestamp, $payme_sale_id, $notify_type);

            if ($notify_type == 'sale-complete') {
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

        $return = array('result' => $result);        
        echo json_encode($return);        
    }

	public static function show_buttons($EM_Booking, $partial=false, $scroll=false) 
    {
        ?><div id="payment-controls"><?php
        if ($partial) {
	?>
        <p class="input-group input-text">
          <label for="paid-transaction-sum"><?php echo "[:ru]Сумма оплаты[:he]סכום לתשלום[:]" ?></label>
          <input type="text" id="paid-transaction-sum" value="<?php echo floor($EM_Booking->get_price() - self::get_total_paid($EM_Booking)) ?>">
        </p>
    <?php
        }
    ?>
        <a class="button" id="pay-by-card">[:ru]Оплатить кредитной картой[:he]תשלום עם כרטיס אשראי[:]</a> &nbsp; &nbsp; 
        <a class="button" id="pay-by-bit">[:ru]Оплатить через Bit[:he]תשלום דרף Bit[:]</a>
        <div id="payment-error" style="display: none; color: firebrick;"></div>
        </div>
        <br>

        <div id="generate-url-spinner" style="display: none">
            <p>[:ru]Подключаюсь к платежной системе[:he]מתחבר למערכת סליקה[:]</p>
            <div class="spinner"></div>
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

        <iframe title="Payment Form" id="paid-iframe" style="border: none; display: none;"></iframe> 

        <script type="text/javascript">
            window.onload = function() { 
    <?php if ($scroll) { ?>
                let paymentLink = document.getElementById('payment-link');
                if (paymentLink !== null) {
                    paymentLink.scrollIntoView();
                }
    <?php } ?>
            };

            function generate_url(paymentType) {
                document.getElementById('payment-controls').style.display = "none";
                document.getElementById('generate-url-spinner').style.display = "block";
                document.getElementById('payment-error').innerText = "";
                document.getElementById('payment-error').style.display = "none";

                let bookingId = <?php echo $EM_Booking->booking_id ?>;
                let language = "<?php echo get_bloginfo('language') ?>";
                let body = "action=paid-generate-url&booking_id=" + bookingId + "&payment_type=" + paymentType + "&language=" + language;
                <?php if ($partial) { ?>
                    let transactionSum = document.getElementById("paid-transaction-sum");
                    if (transactionSum !== null) {
                        body += "&transaction_sum=" + transactionSum.value;
                    }
                <?php } ?>
                fetch('paid.json', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: body
                    })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('generate-url-spinner').style.display = "none";
                    if (data.status_code == 0) {
                        let iframe = document.getElementById("paid-iframe");
                        iframe.src = data.sale_url;
                        iframe.style.display = "block";
                        // hide booking status in the summary, so that users won't be confused when payment is completed
                        document.getElementById('booking-status').style.display = "none";
                    } else {
                        throw new Error(data.status_error_details);
                    }
                })
                .catch((error) => {
                    document.getElementById('payment-error').innerText = error;
                    document.getElementById('payment-error').style.display = "block";
                    document.getElementById('payment-controls').style.display = "block";
                    document.getElementById('generate-url-spinner').style.display = "none";
                });
            }

            document.getElementById("pay-by-card").addEventListener("click", function() {
                generate_url("card");
            });

            document.getElementById("pay-by-bit").addEventListener("click", function() {
                generate_url("bit");
            });

        </script>
    <?php
    }
}

EM_Limmud_Paid::init();