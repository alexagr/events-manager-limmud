<?php

/**
 *
 * @wordpress-plugin
 * Plugin Name:       Events Manager for Limmud FSU Israel
 * Description:       Limmud FSU Israel extensions for Events Manager plugin
 * Version:           1.0
 * Author:            Alex Agranov
 * License:           GNU General Public License v3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 */

define('EM_LIMMUD_VERSION', 1.0);

class EM_Limmud {

    public static function init() {

        //check that Events Manager is installed
        if (!defined('EM_VERSION')) {
            add_action('admin_notices', array(__CLASS__, 'em_install_warning'));
            add_action('network_admin_notices', array(__CLASS__, 'em_install_warning'));
            return false;
        }

        //check that PayPal IPN for WordPress is installed
        if (!defined('PIW_PLUGIN_URL')) {
            add_action('admin_notices', array(__CLASS__, 'ipn_install_warning'));
            add_action('network_admin_notices', array(__CLASS__, 'ipn_install_warning'));
            return false;
        }

        if (is_admin()) {
            include('emlmd-options.php');
            include('emlmd-csv.php');
        }
        include('emlmd-email.php');
        include('emlmd-discount.php');
        include('emlmd-misc.php');
        include('emlmd-tickets.php');
        include('emlmd-frontend.php');
        include('emlmd-secret.php');
        include('emlmd-paypal.php');
        include('emlmd-paid.php');
        include('emlmd-booking.php');
    }
        

    public static function em_install_warning() {
        ?>
        <div class="error"><p>Please make sure you install Events Manager as well. You can search and install this plugin from your plugin installer or download it <a href="http://wordpress.org/extend/plugins/events-manager/">here</a>. <em>Only admins see this message</em></p></div>
        <?php
    }

    public static function ipn_install_warning() {
        ?>
        <div class="error"><p>Please make sure you install Paypal IPN for WordPress as well. You can search and install this plugin from your plugin installer or download it <a href="http://wordpress.org/plugins/paypal-ipn/">here</a>. <em>Only admins see this message</em></p></div>
        <?php
    }
}

add_action('plugins_loaded', 'EM_Limmud::init');


register_activation_hook(__FILE__, 'emlmd_activation');
register_deactivation_hook(__FILE__,'emlmd_deactivation');

function emlmd_activation() {
    wp_schedule_event(time(), 'hourly', 'emlmd_hourly_hook');
}

function emlmd_deactivation() {
    wp_clear_scheduled_hook('emlmd_hourly_hook');
}

?>