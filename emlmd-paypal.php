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

    public static function create_transaction($booking_id, $transaction_sum)
    {
        $EM_Booking = em_get_booking($booking_id);
        if ($EM_Booking->booking_status != 5) {
            echo '{"order_id":""}';
            return;        
        }        

        if (empty($transaction_sum) || ($EM_Booking->get_price() == $transaction_sum)) {
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
                    $discount += -$EM_Ticket_Booking->get_price() * $EM_Ticket_Booking->get_spaces();
    			}
            }
            krsort($tickets);
    
            $price = floor($EM_Booking->get_price());
            $invoice_id = 'LIMMUD-REG#' . $EM_Booking->booking_id;
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
        $result = false;        
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
                            if (self::get_total_paid($EM_Booking) >= $price) {
                                $EM_Booking->approve();
                                $result = True;                            
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

	public static function show_buttons($EM_Booking) 
    {
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
                    return fetch('paypal.json', {
                        method: 'post',
                        headers: {
                            'content-type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=paypal-create-transaction&booking_id=<?php echo $EM_Booking->booking_id ?>'
                    }).then(function(res) {
                        return res.json();
                    }).then(function(data) {
                        if (!data.order_id) {
                            window.location.reload();
                        }
                        return data.order_id;
                    });
                },
                onApprove: function(data) {
                    document.getElementById('payment-buttons-container').style.display = 'none';
                    document.getElementById('payment-authorize-container').style.display = 'block';
                    return fetch('paypal.json', {
                        method: 'post',
                        headers: {
                            'content-type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=paypal-capture-transaction&order_id=' + data.orderID
                    }).then(function(res) {
                        return res.json();
                    }).then(function(data) {
                        if (data.result) {
                            window.location.href = '<?php echo get_post_permalink(get_option('dbem_booking_success_page')) ?>&booking_id=<?php echo $EM_Booking->booking_id ?>';
                        } else {
                            window.location.reload();
                        }                    
                    })
                }
            }).render('#paypal-button-container');
        </script>
    <?php
    }

	public static function show_partial_buttons($EM_Booking) 
    {
	?>
        <p class="input-group input-text">
          <label for="paypal-transaction-sum"><?php echo "[:ru]Сумма оплаты[:he]סכום לתשלום[:]" ?></label>
          <input type="text" id="paypal-transaction-sum" value="<?php echo floor($EM_Booking->get_price() - self::get_total_paid($EM_Booking)) ?>">
        </p>
        <script src="https://www.paypal.com/sdk/js?client-id=<?php 
            if (get_option('dbem_paypal_status') == "live") { 
                echo get_option('dbem_paypal_live_client_id');; 
            } else { 
                echo get_option('dbem_paypal_sandbox_client_id');; 
            } ?>&currency=<?php echo get_option('dbem_bookings_currency', 'ILS') ?>"></script>
        <div id="paypal-partial-button-container" style="display: none; max-width: 360px;"></div>
        <script type="text/javascript">
            window.onload = function() { document.getElementById('paypal-partial-button-container').style.display = 'block'; };
            paypal.Buttons({
                style: {
                    label: 'pay'
                },
                createOrder: function() {
                    var transaction_sum = document.getElementById('paypal-transaction-sum').value;
                    if (!transaction_sum) {
                        return('{"order_id":""}');
                    }
                    return fetch('paypal.json', {
                        method: 'post',
                        headers: {
                            'content-type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=paypal-create-transaction&booking_id=<?php echo $EM_Booking->booking_id ?>&transaction_sum=' + transaction_sum
                    }).then(function(res) {
                        return res.json();
                    }).then(function(data) {
                        if (!data.order_id) {
                            window.location.reload();
                        }
                        return data.order_id;
                    });
                },
                onApprove: function(data) {
                    document.getElementById('payment-buttons-container').style.display = 'none';
                    document.getElementById('payment-authorize-container').style.display = 'block';
                    return fetch('paypal.json', {
                        method: 'post',
                        headers: {
                            'content-type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=paypal-capture-transaction&order_id=' + data.orderID
                    }).then(function(res) {
                        return res.json();
                    }).then(function(data) {
                        if (data.result) {
                            window.location.href = '<?php echo get_post_permalink(get_option('dbem_booking_success_page')) ?>&booking_id=<?php echo $EM_Booking->booking_id ?>';
                        } else {
                            window.location.reload();
                        }                    
                    })
                }
            }).render('#paypal-partial-button-container');
        </script>
    <?php
    }
}

EM_Limmud_Paypal::init();