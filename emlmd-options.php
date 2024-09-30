<?php

class EM_Limmud_Options {
    public static function init() {
        add_action('em_options_page_footer_emails', array(__CLASS__, 'email_options'));
        add_action('em_options_page_footer', array(__CLASS__, 'payment_options'));
        add_action('em_options_page_footer', array(__CLASS__, 'misc_options'));
    }

    public static function email_options() {
        global $save_button;
        ?>
        <div  class="postbox " id="em-opt-email-paypal" >
        <div class="handlediv" title="Click to toggle"><br /></div><h3>Limmud Email Templates</h3>
        <div class="inside">
            <table class='form-table'>
                <?php
                $email_subject_tip = __('You can disable this email by leaving the subject blank.','dbem'); 
                ?>
                <tr class="em-header"><td colspan='2'><h4><?php _e('Event Admin/Owner Emails', 'dbem'); ?></h4></td></tr>
                    <tbody class="em-subsection">
                    <tr class="em-subheader"><td colspan='2'>
                        <h5><?php _e('Awaiting payment email','dbem') ?></h5> 
                        <em><?php echo __('This is sent when a person\'s booking is ready for payment.','dbem') ?></em>
                    </td></tr>
                    <?php
                        em_options_input_text ( __( 'Awaiting payment email subject', 'em-paypal' ), 'dbem_bookings_contact_email_awaiting_payment_subject', $email_subject_tip );                 
                        em_options_textarea ( __( 'Awaiting payment email', 'em-paypal' ), 'dbem_bookings_contact_email_awaiting_payment_body', '' );
                    ?>
                    <tr class="em-subheader"><td colspan='2'>
                        <h5><?php _e('Partial payment email','dbem') ?></h5> 
                        <em><?php echo __('This is sent when a partial payment is received for person\'s booking.','dbem') ?></em>
                    </td></tr>
                    <?php
                        em_options_input_text ( __( 'Partial payment email subject', 'em-paypal' ), 'dbem_bookings_contact_email_partial_payment_subject', $email_subject_tip );                 
                        em_options_textarea ( __( 'Partial payment email', 'em-paypal' ), 'dbem_bookings_contact_email_partial_payment_body', '' );
                    ?>
                    <tr class="em-subheader"><td colspan='2'>
                        <h5><?php _e('No payment email','dbem') ?></h5> 
                        <em><?php echo __('This is sent when a person\'s booking payment time expires.','dbem') ?></em>
                    </td></tr>
                    <?php
                        em_options_input_text ( __( 'No payment email subject', 'em-paypal' ), 'dbem_bookings_contact_email_no_payment_subject', $email_subject_tip );                 
                        em_options_textarea ( __( 'No payment email', 'em-paypal' ), 'dbem_bookings_contact_email_no_payment_body', '' );
                    ?>
                    <tr class="em-subheader"><td colspan='2'>
                        <h5><?php _e('Partially paid email','dbem') ?></h5> 
                        <em><?php echo __('This is sent when a person\'s partially paid booking expires.','dbem') ?></em>
                    </td></tr>
                    <?php
                        em_options_input_text ( __( 'Partially paid email subject', 'em-paypal' ), 'dbem_bookings_contact_email_partially_paid_subject', $email_subject_tip );                 
                        em_options_textarea ( __( 'Partially paid email', 'em-paypal' ), 'dbem_bookings_contact_email_partially_paid_body', '' );
                    ?>
                    <tr class="em-subheader"><td colspan='2'>
                        <h5><?php _e('Waiting list email','dbem') ?></h5>
                        <em><?php echo __('This is sent when a person\'s booking is moved to Waiting List.','dbem') ?></em>
                    </td></tr>
                    <?php
                        em_options_input_text ( __( 'Waiting list email subject', 'em-paypal' ), 'dbem_bookings_contact_email_waiting_list_subject', $email_subject_tip );
                        em_options_textarea ( __( 'Waiting list email', 'em-paypal' ), 'dbem_bookings_contact_email_waiting_list_body', '' );
                    ?>
                <tr class="em-header"><td colspan='2'><h4><?php _e('Booked User Emails', 'dbem'); ?></h4></td></tr>
                    <tbody class="em-subsection">
                    <tr class="em-subheader"><td colspan='2'>
                        <h5><?php _e('Awaiting payment email','dbem') ?></h5> 
                        <em><?php echo __('This will be sent to the person when their booking ready for payment.','dbem') ?></em>
                    </td></tr>
                    <?php
                        em_options_input_text ( __( 'Awaiting payment email subject', 'em-paypal' ), 'dbem_bookings_email_awaiting_payment_subject', $email_subject_tip );                 
                        em_options_textarea ( __( 'Awaiting payment email', 'em-paypal' ), 'dbem_bookings_email_awaiting_payment_body', '' );
                    ?>
                    <tr class="em-subheader"><td colspan='2'>
                        <h5><?php _e('Partial payment email','dbem') ?></h5> 
                        <em><?php echo __('This will be sent to the person when partial payment is received for their booking.','dbem') ?></em>
                    </td></tr>
                    <?php
                        em_options_input_text ( __( 'Partial payment email subject', 'em-paypal' ), 'dbem_bookings_email_partial_payment_subject', $email_subject_tip );                 
                        em_options_textarea ( __( 'Partial payment email', 'em-paypal' ), 'dbem_bookings_email_partial_payment_body', '' );
                    ?>
                    <tr class="em-subheader"><td colspan='2'>
                        <h5><?php _e('Payment reminder email','dbem') ?></h5> 
                        <em><?php echo __('This will be sent to the person 24 hours before their booking expires.','dbem') ?></em>
                    </td></tr>
                    <?php
                        em_options_input_text ( __( 'Payment reminder email subject', 'em-paypal' ), 'dbem_bookings_email_payment_reminder_subject', $email_subject_tip );                 
                        em_options_textarea ( __( 'Payment reminder email', 'em-paypal' ), 'dbem_bookings_email_payment_reminder_body', '' );
                    ?>
                    <tr class="em-subheader"><td colspan='2'>
                        <h5><?php _e('No payment email','dbem') ?></h5> 
                        <em><?php echo __('This will be sent to the person when their booking payment time expires.','dbem') ?></em>
                    </td></tr>
                    <?php
                        em_options_input_text ( __( 'No payment email subject', 'em-paypal' ), 'dbem_bookings_email_no_payment_subject', $email_subject_tip );                 
                        em_options_textarea ( __( 'No payment email', 'em-paypal' ), 'dbem_bookings_email_no_payment_body', '' );
                    ?>
                    <tr class="em-subheader"><td colspan='2'>
                        <h5><?php _e('Partially paid email','dbem') ?></h5> 
                        <em><?php echo __('This will be sent to the person when their partially paid booking expires.','dbem') ?></em>
                    </td></tr>
                    <?php
                        em_options_input_text ( __( 'Partially paid email subject', 'em-paypal' ), 'dbem_bookings_email_partially_paid_subject', $email_subject_tip );                 
                        em_options_textarea ( __( 'Partially paid email', 'em-paypal' ), 'dbem_bookings_email_partially_paid_body', '' );
                    ?>
                    <tr class="em-subheader"><td colspan='2'>
                        <h5><?php _e('Waiting list email','dbem') ?></h5>
                        <em><?php echo __('This will be sent to the person when their booking is moved to Waiting List.','dbem') ?></em>
                    </td></tr>
                    <?php
                        em_options_input_text ( __( 'Waiting list email subject', 'em-paypal' ), 'dbem_bookings_email_waiting_list_subject', $email_subject_tip );
                        em_options_textarea ( __( 'Waiting list email', 'em-paypal' ), 'dbem_bookings_email_waiting_list_body', '' );
                    ?>
                <?php echo $save_button; ?>
            </table>
        </div> <!-- . inside -->
        </div> <!-- .postbox -->
        <?php
    }

    public static function payment_options() {
        global $save_button;
        ?>
        <div  class="postbox " id="em-opt-payment-options" >
        <div class="handlediv" title="Click to toggle"><br /></div><h3>Limmud Payment Options</h3>
        <div class="inside">
            <table class='form-table'>
                <?php
                    em_options_select ( __( 'Payment Provider', 'em-limmud' ), 'dbem_payment_provider', array ('paypal' => 'PayPal', 'paid' => 'Paid'), '' );
                    em_options_select ( __( 'Payment Mode', 'em-limmud' ), 'dbem_payment_mode', array ('live' => 'Live Site', 'test' => 'Test Mode (Sandbox)'), '' );
                    em_options_select ( __( 'Automatic Payment', 'em-limmud' ), 'dbem_automatic_payment', array ('disable' => 'Disable', 'enable' => 'Enable'), '' );
                    em_options_select ( __( 'Days For Payment', 'em-limmud' ), 'dbem_days_for_payment', array ('0' => 'Unlimited', '1' => '1 Day', '2' => '2 Days', '3' => '3 Days', '4' => '4 Days'), '' );
                ?>
                <tr><th>
                <?php
				echo sprintf(__( '%s page', 'events-manager'),__('Booking Summary','events-manager'))
				?></th><td><?php
					wp_dropdown_pages(array('name'=>'dbem_booking_summary_page', 'selected'=>get_option('dbem_booking_summary_page'), 'show_option_none'=>'['.__('None', 'events-manager').']' ));
                ?></td></tr>
            </table>
        </div> <!-- . inside -->
        </div> <!-- .postbox -->
        <div  class="postbox " id="em-opt-paypal-options" >
        <div class="handlediv" title="Click to toggle"><br /></div><h3>Limmud PayPal Options</h3>
        <div class="inside">
            <table class='form-table'>
                <?php
                    em_options_input_text ( __( 'PayPal Live Client ID', 'em-limmud' ), 'dbem_paypal_live_client_id', '' );
                    em_options_input_text ( __( 'PayPal Live Secret', 'em-limmud' ), 'dbem_paypal_live_secret', '' );
                    em_options_input_text ( __( 'PayPal Sandbox Client ID', 'em-limmud' ), 'dbem_paypal_sandbox_client_id', '' );
                    em_options_input_text ( __( 'PayPal Sandbox Secret', 'em-limmud' ), 'dbem_paypal_sandbox_secret', '' );
                ?>
                <tr><th>
                <?php
				echo sprintf(__( '%s page', 'events-manager'),__('Booking Success','events-manager'))
				?></th><td><?php
					wp_dropdown_pages(array('name'=>'dbem_booking_success_page', 'selected'=>get_option('dbem_booking_success_page'), 'show_option_none'=>'['.__('None', 'events-manager').']' ));
                ?></td></tr>
                <tr><th>
                <?php
				echo sprintf(__( '%s page', 'events-manager'),__('Partial Payment Success','events-manager'))
				?></th><td><?php
					wp_dropdown_pages(array('name'=>'dbem_partial_payment_success_page', 'selected'=>get_option('dbem_partial_payment_success_page'), 'show_option_none'=>'['.__('None', 'events-manager').']' ));
                ?></td></tr>
            </table>
        </div> <!-- . inside -->
        </div> <!-- .postbox -->
        <div  class="postbox " id="em-opt-paid-options" >
        <div class="handlediv" title="Click to toggle"><br /></div><h3>Limmud Paid Options</h3>
        <div class="inside">
            <table class='form-table'>
                <?php
                    em_options_input_text ( __( 'Paid Live API Key', 'em-limmud' ), 'dbem_paid_live_api_key', '' );
                    em_options_input_text ( __( 'Paid Sandbox API Key', 'em-limmud' ), 'dbem_paid_sandbox_api_key', '' );
                    em_options_select ( __( 'Paid 3D Secure', 'em-limmud' ), 'dbem_paid_3d_secure', array ('disable' => 'Disable', 'enable' => 'Enable'), '' );
                    em_options_select ( __( 'Paid Installments', 'em-limmud' ), 'dbem_paid_installments', array ('1' => 'One', '103' => 'Up To Three', '106' => 'Up To Six'), '' );
                ?>
                <tr><th>
                <?php
				echo sprintf(__( '%s page', 'events-manager'),__('Payment Redirect','events-manager'))
				?></th><td><?php
					wp_dropdown_pages(array('name'=>'dbem_payment_redirect_page', 'selected'=>get_option('dbem_payment_redirect_page'), 'show_option_none'=>'['.__('None', 'events-manager').']' ));
                ?></td></tr>
            </table>
        </div> <!-- . inside -->
        </div> <!-- .postbox -->
        <?php
    }

    public static function misc_options() {
        global $save_button;
        ?>
        <div  class="postbox " id="em-opt-misc-options" >
        <div class="handlediv" title="Click to toggle"><br /></div><h3>Limmud Miscellaneous Options</h3>
        <div class="inside">
            <table class='form-table'>
                <?php
                    em_options_select ( __( 'Show Event Details', 'em-limmud' ), 'dbem_show_event_details', array ('show' => 'Show', 'hide' => 'Hide'), '' );                                      
                    em_options_select ( __( 'Admin Actions', 'em-limmud' ), 'dbem_admin_actions', array ('all' => 'Show All', 'edit' => 'Edit/View Only'), '' );                                      
                ?>
            </table>
        </div> <!-- . inside -->
        </div> <!-- .postbox -->
        <?php
    }
}

EM_Limmud_Options::init();