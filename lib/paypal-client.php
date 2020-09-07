<?php

if (!defined('ABSPATH'))
   exit;

require_once ( plugin_dir_path(__FILE__) . 'vendor/autoload.php' );

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;

ini_set('error_reporting', E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

class PayPalClient
{
    public static function client() 
    {
        if (get_option('dbem_paypal_status') == "live") {
            $clientId = get_option('dbem_paypal_live_client_id');
            $clientSecret = get_option('dbem_paypal_live_secret');
            $env = new ProductionEnvironment($clientId, $clientSecret);
        } else {
            $clientId = get_option('dbem_paypal_sandbox_client_id');
            $clientSecret = get_option('dbem_paypal_sandbox_secret');
            $env = new SandboxEnvironment($clientId, $clientSecret);
        }
        return new PayPalHttpClient($env);
    }

    public static function createOrder($body)
    {
        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        $request->body = $body;
        $client = PayPalClient::client();
        try {
            $response = $client->execute($request);
        }
        catch (\Throwable $e) {
            $response = NULL;
            EM_Pro::log($e->getMessage(), 'paypal', true);
        } 
        catch (\Exception $e) {
            $response = NULL;
            EM_Pro::log($e->getMessage(), 'paypal', true);
        } 
        return $response;
    }

    public static function captureOrder($orderId)
    {
        $request = new OrdersCaptureRequest($orderId);
        $request->prefer('return=representation');
        $client = PayPalClient::client();
        try {
            $response = $client->execute($request);
        }
        catch (\Throwable $e) {
            $response = NULL;
            EM_Pro::log($e->getMessage(), 'paypal', true);
        } 
        catch (\Exception $e) {
            $response = NULL;
            EM_Pro::log($e->getMessage(), 'paypal', true);
        } 
        return $response;
    }

}
?>