<?php

class EM_Limmud_Payplus {
    public static function init() {
        add_action('init',array(__CLASS__,'init_actions'), 10);
    }

    public static function init_actions() {
        if (!empty($_REQUEST['action']) && ($_REQUEST['action']  == 'payplus-generate-url') && !empty($_REQUEST['booking_id'])) {
            self::log('payplus-generate-url ' . print_r($_REQUEST, true), 'debug');
            self::generate_url($_REQUEST['booking_id'], $_REQUEST['language'], $_REQUEST['transaction_sum']);
            die();
        }

        if (!empty($_REQUEST['action']) && ($_REQUEST['action']  == 'payplus-callback-status')) {
            if (!empty($_REQUEST['booking_id'])) {
                $request_body = file_get_contents('php://input');
                if (!empty($request_body)) {
                    $body_data = json_decode($request_body, true);
                    self::log('payplus-callback-status ' . print_r($body_data, true), 'debug');
                    self::transaction_callback($body_data);
                }
                die();
            }
        }
    }

    public static function log($message, $level) {
        if ($level != 'debug1') {
            EM_Pro::log($message, 'payplus', true);
        }
    }

    public static function generate_url($booking_id, $language, $transaction_sum)
    {
        $EM_Booking = em_get_booking($booking_id);
        if ($EM_Booking->booking_status != 5) {
            echo '{"results": {"code": 1, "status": "error", "description": "Booking is in wrong status (' . strval($EM_Booking->booking_status) . ')"}}';
            return;
        }

        $full_price = floor($EM_Booking->get_price());

        if (empty($transaction_sum)) {
            $price = $full_price;
        } else {
            $price = $transaction_sum;
        }

        $total_paid = (int)$EM_Booking->get_total_paid();
        $price = min($price, $full_price - $total_paid);

        if (get_option('dbem_payment_mode') == "live") {
            $payment_page_uid = get_option('dbem_payplus_payment_page_uid');
        } else {
            $payment_page_uid = get_option('dbem_payplus_dev_payment_page_uid');
        }

        if ($language == 'he-IL') {
            $lang = 'he';
        } else {
            $lang = 'ru';
        }
        $lang = 'he';

        $redirect_url = get_post_permalink(get_option('dbem_payplus_redirect_page')) . '&booking_id=' . $EM_Booking->booking_id . '&amount=' . $price . '&secret=' . EM_Limmud_Booking::get_secret($EM_Booking, 'payment_redirect');
        if ($price + $total_paid < $full_price) {
            $redirect_url = $redirect_url . '&payment_type=partial';
        } else {
            $redirect_url = $redirect_url . '&payment_type=full';
        }

        $data = array(
            'payment_page_uid' => $payment_page_uid,
            'language_code' => $lang,
            'amount' => $price,
            'currency_code' => get_option('dbem_bookings_currency', 'ILS'),
            'sendEmailApproval' => true,
            'sendEmailFailure' => false,
            'refURL_success' => $redirect_url . '&status=success',
            'refURL_failure' => $redirect_url . '&status=failure',
            'refURL_callback' => get_site_url() . '/events-manager-limmud?action=payplus-callback-status&booking_id=' . $EM_Booking->booking_id,
            'customer' => array(
                'customer_name' => $EM_Booking->get_person()->first_name . ' ' . $EM_Booking->get_person()->last_name,
                'email' => $EM_Booking->get_person()->user_email
            ),
            'more_info' => $booking_id,
            'more_info_1' => date('mdHis'),
            'payments' => (int)get_option('dbem_payplus_payments', '1')
        );

        if (get_option('dbem_payplus_3d_secure', 'disable') == 'enable') {
            $data['secure3d'] = array(
                'activate' => true
            );
        }

        $body = json_encode($data);

        self::log('generate_url ' . $body, 'debug');

        [$response_status, $response_body] = self::send_request('PaymentPages/generateLink', $body);
        echo $response_body;
    }

    public static function send_request($url_path, $body)
    {
        if (get_option('dbem_payment_mode') == "live") {
            $url_base = 'https://restapi.payplus.co.il/api/v1.0/';
            $api_key = get_option('dbem_payplus_api_key');
            $secret_key = get_option('dbem_payplus_secret_key');
        } else {
            $url_base = 'https://restapidev.payplus.co.il/api/v1.0/';
            $api_key = get_option('dbem_payplus_dev_api_key');
            $secret_key = get_option('dbem_payplus_dev_secret_key');
        }

        $args = array(
            'body' => $body,
            'headers' => array(
                'Authorization' => '{"api_key": "' . $api_key . '", "secret_key": "' . $secret_key . '"}',
                'domain' => home_url(),
                'User-Agent' => "WordPress",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ),
            'timeout' => 10,
            'blocking' => true
        );

        $response = wp_remote_post($url_base . $url_path, $args);
        $response_code = wp_remote_retrieve_response_code($response);
        $response_text = wp_remote_retrieve_response_message($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        if ($response_code >= 300) {
            return [false, json_encode([
                "results" => [
                    "code" => 1,
                    "status" => "error",
                    "description" => "Cannot generate payment link: " . strval($response_code) . ' ' . print_r($response_text, true) . ' - ' . print_r($response_body, true)
                ]
            ])];
        }

        if (!isset($response_headers['user-agent'])) {
            return [false, json_encode([
                "results" => [
                    "code" => 1,
                    "status" => "error",
                    "description" => print_r($response_body, true)
                ]
            ])];
        }
        $user_agent = $response_headers['user-agent'];
        if ($user_agent !== 'PayPlus') {
            return [false, json_encode([
                "results" => [
                    "code" => 1,
                    "status" => "error",
                    "description" => "Invalid response: incorrect user-agent header"
                ]
            ])];
        }
        if (!isset($response_headers['hash'])) {
            return [false, json_encode([
                "results" => [
                    "code" => 1,
                    "status" => "error",
                    "description" => "Invalid response: missing hash header"
                ]
            ])];
        }
        $hash = $response_headers['hash'];

        $genHash = base64_encode(hash_hmac('sha256', $response_body, $secret_key, true));
        if ($genHash !== $hash) {
            return [false, json_encode([
                "results" => [
                    "code" => 1,
                    "status" => "error",
                    "description" => "Invalid response: hash mismatch"
                ]
            ])];
        }

        return [true, $response_body];
    }

    public static function transaction_callback($data)
    {
        $result = "failed";

        $transaction_type = isset($data['transaction_type']) ? $data['transaction_type'] : '';
        if (strtolower($transaction_type) === 'charge' && isset($data['transaction']) && is_array($data['transaction'])) {
            $uid = isset($data['transaction']['uid']) ? $data['transaction']['uid'] : '';
            $payment_request_uid = isset($data['transaction']['payment_request_uid']) ? $data['transaction']['payment_request_uid'] : '';

            $body = json_encode(array(
                'transaction_uid' => $uid,
                'payment_request_uid' => $payment_request_uid
            ));
            [$response_status, $response_body] = self::send_request('PaymentPages/ipn', $body);

            if ($response_status) {
                self::log('ipn response ' . print_r($response_body, true), 'debug');
                $response_data = json_decode($response_body, true);
                if (is_array($response_data) && isset($response_data['data']) && is_array($response_data['data'])) {
                    $params = $response_data['data'];
                    $date = isset($params['date']) ? $params['date'] : '';
                    $status_code = isset($params['status_code']) ? $params['status_code'] : '';
                    $amount = isset($params['amount']) ? $params['amount'] : 0;
                    $currency = isset($params['currency']) ? $params['currency'] : '';
                    $number = isset($params['number']) ? $params['number'] : '';
                    $booking_id = isset($params['more_info']) ? $params['more_info'] : '';
                    $payment_time = isset($params['more_info_1']) ? $params['more_info_1'] : '';

                    if ($status_code === '000') {
                        $status = 'COMPLETED';
                    } else {
                        $status = 'FAILED ' . $status_code;
                    }

                    if (!empty($uid) && !empty($booking_id) && ($amount > 0)) {
                        $EM_Booking = em_get_booking($booking_id);
                        if (!empty($EM_Booking->booking_id)) {
                            $record_status = EM_Limmud_Misc::record_transaction($EM_Booking->booking_id, 'payplus', $amount, $currency, $date, $number . '-' . $payment_time, $status, $EM_Booking->booking_id);
                            if ($record_status) {
                                $result = EM_Limmud_Misc::payment_callback($EM_Booking, $amount);
                            }
                        }
                    }
                } else {
                    self::log('invalid ipn response ' . print_r($response_body, true), 'error');
                }
            } else {
                self::log('ipn error ' . print_r($response_body, true), 'error');
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
          <label for="payplus-transaction-sum"><?php echo "[:ru]Сумма оплаты[:he]סכום לתשלום[:]" ?></label>
          <input type="text" id="payplus-transaction-sum" value="<?php echo floor($EM_Booking->get_price()) - (int)$EM_Booking->get_total_paid() ?>">
        </p>
    <?php
        }
    ?>
        <a class="button" id="pay">[:ru]Оплатить[:he]לשלם[:]</a> &nbsp; &nbsp;
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

        <script type="text/javascript">
            window.onload = function() {
    <?php if ($scroll) { ?>
                let paymentLink = document.getElementById('payment-link');
                if (paymentLink !== null) {
                    paymentLink.scrollIntoView();
                }
    <?php } ?>
            };

            function generate_url() {
                document.getElementById('payment-controls').style.display = "none";
                document.getElementById('generate-url-spinner').style.display = "block";
                document.getElementById('payment-error').innerText = "";
                document.getElementById('payment-error').style.display = "none";

                let bookingId = <?php echo $EM_Booking->booking_id ?>;
                let language = "<?php echo get_bloginfo('language') ?>";
                let body = "action=payplus-generate-url&booking_id=" + bookingId + "&language=" + language;
                <?php if ($partial) { ?>
                    let transactionSum = document.getElementById("payplus-transaction-sum");
                    if (transactionSum !== null) {
                        body += "&transaction_sum=" + transactionSum.value;
                    }
                <?php } ?>
                fetch('events-manager-limmud', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: body
                    })
                .then(response => response.json())
                .then(response => {
                    document.getElementById('generate-url-spinner').style.display = "none";
                    if (response.results.code == 0) {
                        // hide booking status in the summary, so that users won't be confused when payment is completed
                        document.getElementById('booking-status').style.display = "none";
                        window.location.href = response.data.payment_page_link;
                    } else {
                        throw new Error(response.results.description);
                    }
                })
                .catch((error) => {
                    document.getElementById('payment-error').innerText = error;
                    document.getElementById('payment-error').style.display = "block";
                    document.getElementById('payment-controls').style.display = "block";
                    document.getElementById('generate-url-spinner').style.display = "none";
                });
            }

            document.getElementById("pay").addEventListener("click", function() {
                generate_url();
            });

        </script>
    <?php
    }
}

EM_Limmud_Payplus::init();