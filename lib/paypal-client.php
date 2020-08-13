<?php

if (!defined('ABSPATH'))
   exit;

require_once ( plugin_dir_path(__FILE__) . 'vendor/autoload.php' );

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\LiveEnvironment;
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
            $clientId = "ASFUnYFhZt3h3qMUYDv3yUL9qH_LeVBGLQeSaX9uoJmEq4_qa42cstpSDGEegGyePPAiCl9xlz3CfGuU";
            $clientSecret = "EGSsY9M3SK1xU11V7vCVfA9-GC-PHB4_-hXXryfFtPcZv71cwqypiEwUYW-WtTiexfURkxp6uRl-FUp5";
            $env = new LiveEnvironment($clientId, $clientSecret);
        } else {
            $clientId = "AaSgxHz1Nhh_Vy6B_nzpdCoNJZM3lK8tIW_xVuaAw1tn6os52AqJ6megXDuu-i6JxMYGkyD6GUurgPab";
            $clientSecret = "EIEX2_FIoRB4y6YZ7Xb17jfcSaJptuLUC5MMKf_Gc9bLbwj_-LycgVEqF3ZK6YFexhU3_a982FQ9e3Xd";
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
            EM_Pro::log($e->getMessage(), 'paypal', True);
        } 
        catch (\Exception $e) {
            $response = NULL;
            EM_Pro::log($e->getMessage(), 'paypal', True);
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
            EM_Pro::log($e->getMessage(), 'paypal', True);
        } 
        catch (\Exception $e) {
            $response = NULL;
            EM_Pro::log($e->getMessage(), 'paypal', True);
        } 
        return $response;
    }

}
?>