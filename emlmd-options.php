<?php

class EM_Limmud_Options {
    public static function init() {
        add_action('em_options_page_footer_emails', array(__CLASS__, 'email_options'));
        add_action('em_options_page_footer', array(__CLASS__, 'paypal_options'));
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
                $email_hashtag_tip = ' This accepts all regular placeholders and #_PAYPAL for PayPal link.'; 
                ?>
                <tr class="em-header"><td colspan='2'><h4><?php _e('Event Admin/Owner Emails', 'dbem'); ?></h4></td></tr>
                    <tbody class="em-subsection">
                    <tr class="em-subheader"><td colspan='2'>
                        <h5><?php _e('Awaiting payment email','dbem') ?></h5> 
                        <em><?php echo __('This is sent when a person\'s booking is awaiting payment.'.$email_hashtag_tip,'dbem') ?></em>
                    </td></tr>
                    <?php
                        em_options_input_text ( __( 'Awaiting payment email subject', 'em-paypal' ), 'dbem_bookings_contact_email_awaiting_payment_subject', $email_subject_tip );                 
                        em_options_textarea ( __( 'Awaiting payment email', 'em-paypal' ), 'dbem_bookings_contact_email_awaiting_payment_body', '' );
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
                        <em><?php echo __('This will be sent to the person when their booking is awaiting payment.'.$email_hashtag_tip,'dbem') ?></em>
                    </td></tr>
                    <?php
                        em_options_input_text ( __( 'Awaiting payment email subject', 'em-paypal' ), 'dbem_bookings_email_awaiting_payment_subject', $email_subject_tip );                 
                        em_options_textarea ( __( 'Awaiting payment email', 'em-paypal' ), 'dbem_bookings_email_awaiting_payment_body', '' );
                    ?>
                    <tr class="em-subheader"><td colspan='2'>
                        <h5><?php _e('Payment reminder email','dbem') ?></h5> 
                        <em><?php echo __('This will be sent to the person 24 hours before their booking is moved to Waiting List.'.$email_hashtag_tip,'dbem') ?></em>
                    </td></tr>
                    <?php
                        em_options_input_text ( __( 'Payment reminder email subject', 'em-paypal' ), 'dbem_bookings_email_payment_reminder_subject', $email_subject_tip );                 
                        em_options_textarea ( __( 'Payment reminder email', 'em-paypal' ), 'dbem_bookings_email_payment_reminder_body', '' );
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

    public static function paypal_options() {
        global $save_button;
        ?>
        <div  class="postbox " id="em-opt-paypal-options" >
        <div class="handlediv" title="Click to toggle"><br /></div><h3>Limmud Paypal Options</h3>
        <div class="inside">
            <table class='form-table'>
                <?php
                    em_options_input_text ( __( 'Live Client ID', 'em-limmud' ), 'dbem_paypal_live_client_id', '' );
                    em_options_input_text ( __( 'Live Secret', 'em-limmud' ), 'dbem_paypal_live_secret', '' );
                    em_options_input_text ( __( 'Sandbox Client ID', 'em-limmud' ), 'dbem_paypal_sandbox_client_id', '' );
                    em_options_input_text ( __( 'Sandbox Secret', 'em-limmud' ), 'dbem_paypal_sandbox_secret', '' );
                    em_options_select ( __( 'PayPal Mode', 'em-limmud' ), 'dbem_paypal_status', array ('live' => 'Live Site', 'test' => 'Test Mode (Sandbox)'), '' );                                      
                ?>
                <tr><th>
                <?php
				echo sprintf(__( '%s page', 'events-manager'),__('Booking Summary','events-manager'))
				?></th><td><?php
					wp_dropdown_pages(array('name'=>'dbem_booking_summary_page', 'selected'=>get_option('dbem_booking_summary_page'), 'show_option_none'=>'['.__('None', 'events-manager').']' ));
                ?></td></tr>
                <?php
                    em_options_select ( __( 'Automatic Payment', 'em-limmud' ), 'dbem_automatic_payment', array ('disable' => 'Disable', 'enable' => 'Enable'), '' );
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
                    em_options_select ( __( 'Days For Payment', 'em-limmud' ), 'dbem_days_for_payment', array ('0' => 'Unlimited', '1' => '1 Day', '2' => '2 Days', '3' => '3 Days', '4' => '4 Days'), '' );                                      
                ?>
            </table>
        </div> <!-- . inside -->
        </div> <!-- .postbox -->
        <?php
    }
}

EM_Limmud_Options::init();