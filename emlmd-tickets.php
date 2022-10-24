<?php

class EM_Limmud_Tickets {
    public static function init() {
        if (current_user_can('manage_others_bookings')) {
            add_action('em_bookings_event_footer', array(__CLASS__, 'output'), 11, 1);
            add_action('em_bookings_dashboard', array(__CLASS__, 'em_bookings_dashboard'), 11);
        }
    }

    public static function output($EM_Event) {
        $EM_Tickets = $EM_Event->get_tickets();
        if (count($EM_Tickets->tickets) > 1) {
        ?>
        <div class="wrap">
            <h2><?php esc_html_e('Tickets','events-limmud'); ?></h2>
            <div class="table-wrap">
            <table class="widefat">
                <thead>
                    <tr valign="top">
                        <th><?php esc_html_e('Ticket Name','events-limmud'); ?></th>
                        <th><?php esc_html_e('Price','events-limmud'); ?></th>
                        <th><?php esc_html_e('Booked Spaces','events-limmud'); ?></th>
                        <th><?php esc_html_e('Pending Spaces','events-limmud'); ?></th>
                    </tr>
                </thead>    
                <?php
                    $col_count = 0;
                    foreach ($EM_Tickets->tickets as $EM_Ticket) {
                        ?>
                        <tbody id="em-ticket-<?php echo $col_count ?>" >
                            <tr class="em-tickets-row">
                                <td class="ticket-name">
                                    <span class="ticket_name"><?php if($EM_Ticket->ticket_members) echo '* ';?><?php echo wp_kses_data(apply_filters('translate_text', $EM_Ticket->ticket_name, 'ru')); ?></span>
                                </td>
                                <td class="ticket-price">
                                    <span class="ticket_price"><?php echo ($EM_Ticket->ticket_price) ? esc_html($EM_Ticket->get_price_precise()) : esc_html__('Free','events-manager'); ?></span>
                                </td>
                                <td class="ticket-booked-spaces">
                                    <span class="ticket_booked_spaces"><?php echo $EM_Ticket->get_booked_spaces(); ?></span>
                                </td>
                                <td class="ticket-pending-spaces">
                                    <span class="ticket_pending_spaces"><?php echo $EM_Ticket->get_pending_spaces(); ?></span>
                                </td>
                            </tr>
                        </tbody>
                        <?php
                        $col_count++;
                    }
                ?>
            </table>
            </div>
        </div>
        <?php
        }

        $hotel_bookings = array();
        $EM_Bookings = $EM_Event->get_bookings();
        foreach ($EM_Bookings->bookings as $EM_Booking) {
            if (!array_key_exists('hotel_name', $EM_Booking->booking_meta['booking']))
                continue;
            
            $hotel_name = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['hotel_name'], 'ru');
            if (!array_key_exists($hotel_name, $hotel_bookings))
                $hotel_bookings[$hotel_name] = array('pending'=>0, 'partial'=>0, 'approved'=>0, 'not_fully_paid'=>0, 'waiting_list'=>0);

            switch ($EM_Booking->booking_status) {
                case 0:
                case 5:
                    if (EM_Limmud_Paypal::get_total_paid($EM_Booking) > 0) {
                        $hotel_bookings[$hotel_name]['partial'] += 1;
                    } else {
                        $hotel_bookings[$hotel_name]['pending'] += 1;
                    }
                    break;
                case 1:
                    $hotel_bookings[$hotel_name]['approved'] += 1;
                    break;
                case 7:
                    $hotel_bookings[$hotel_name]['not_fully_paid'] += 1;
                    break;
                case 8:
                    $hotel_bookings[$hotel_name]['waiting_list'] += 1;
                    break;
            }
        }

        if (count($hotel_bookings) > 0) {
        ?>
        <div class="wrap">
            <h2><?php esc_html_e('Hotels','events-limmud'); ?></h2>
            <div class="table-wrap">
            <table class="widefat">
                <thead>
                    <tr valign="top">
                        <th><?php esc_html_e('Hotel Name','events-limmud'); ?></th>
                        <th><?php esc_html_e('Booked Rooms','events-limmud'); ?></th>
                        <th><?php esc_html_e('Partially Paid','events-limmud'); ?></th>
                        <th><?php esc_html_e('Awaiting Payment','events-limmud'); ?></th>
                        <th><?php esc_html_e('Not Fully Paid / Expired','events-limmud'); ?></th>
                        <th><?php esc_html_e('Waiting List','events-limmud'); ?></th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>    
                <?php
                    $col_count = 0;
                    foreach ($hotel_bookings as $key => $value) {
                        ?>
                        <tbody id="em-hotel-<?php echo $col_count ?>" >
                            <tr class="em-hotel-row">
                                <td class="hotel-name">
                                    <span class="hotel_name"><?php echo $key; ?></span>
                                </td>
                                <td class="hotel-booked-rooms">
                                    <span class="hotel_booked_rooms"><?php echo $value['approved']; ?></span>
                                </td>
                                <td class="hotel-pending-rooms">
                                    <span class="hotel_pending_rooms"><?php echo $value['partial']; ?></span>
                                </td>
                                <td class="hotel-pending-rooms">
                                    <span class="hotel_pending_rooms"><?php echo $value['pending']; ?></span>
                                </td>
                                <td class="hotel-not-fully-paid">
                                    <span class="hotel_not_fully_paid"><?php echo $value['not_fully_paid']; ?></span>
                                </td>
                                <td class="hotel-waiting-list">
                                    <span class="hotel_waiting_list"><?php echo $value['waiting_list']; ?></span>
                                </td>
                            </tr>
                        </tbody>
                        <?php
                        $col_count++;
                    }
                ?>
            </table>
            </div>
        </div>
        <?php
        }
    }

    public static function em_bookings_dashboard() {
        $action_scope = ( !empty($_REQUEST['em_obj']) && $_REQUEST['em_obj'] == 'em_bookings_events_table' );
        $action = ( $action_scope && !empty($_GET ['action']) ) ? $_GET ['action']:'';
        $order = ( $action_scope && !empty($_GET ['order']) ) ? $_GET ['order']:'ASC';
        $limit = ( $action_scope && !empty($_GET['limit']) ) ? $_GET['limit'] : 20;//Default limit
        $page = ( $action_scope && !empty($_GET['pno']) ) ? $_GET['pno']:1;
        $offset = ( $action_scope && $page > 1 ) ? ($page-1)*$limit : 0;
        $scope = ( $action_scope && !empty($_GET ['scope']) && array_key_exists($_GET ['scope'], $scope_names) ) ? $_GET ['scope']:'future';

        $owner = !current_user_can('manage_others_bookings') ? get_current_user_id() : false;
        $events = EM_Events::get( array('scope'=>$scope, 'limit'=>$limit, 'offset' => $offset, 'order'=>$order, 'orderby'=>'event_start', 'bookings'=>true, 'owner' => $owner, 'pagination' => 1 ) );
        $events_count = EM_Events::$num_rows_found;

        $hotel_bookings = array();
        if (!empty($events)) {
            foreach ($events as $EM_Event) {
                $EM_Bookings = $EM_Event->get_bookings();
                foreach ($EM_Bookings->bookings as $EM_Booking) {
                    if (!array_key_exists('hotel_name', $EM_Booking->booking_meta['booking']))
                        continue;
                    
                    $hotel_name = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['hotel_name'], 'ru');
                    if (!array_key_exists($hotel_name, $hotel_bookings))
                        $hotel_bookings[$hotel_name] = array('pending'=>0, 'partial'=>0, 'approved'=>0, 'not_fully_paid'=>0, 'waiting_list'=>0);
        
                    switch ($EM_Booking->booking_status) {
                        case 0:
                        case 5:
                            if (EM_Limmud_Paypal::get_total_paid($EM_Booking) > 0) {
                                $hotel_bookings[$hotel_name]['partial'] += 1;
                            } else {
                                $hotel_bookings[$hotel_name]['pending'] += 1;
                            }
                            break;
                        case 1:
                            $hotel_bookings[$hotel_name]['approved'] += 1;
                            break;
                        case 7:
                            $hotel_bookings[$hotel_name]['not_fully_paid'] += 1;
                            break;
                        case 8:
                            $hotel_bookings[$hotel_name]['waiting_list'] += 1;
                            break;
                    }
                }
            }
        }

        if (count($hotel_bookings) > 0) {
        ?>
        <div class="wrap">            
            <h2><?php esc_html_e('Hotels','events-limmud'); ?></h2>
            <div class="table-wrap">            
            <table class="widefat">
                <thead>
                    <tr valign="top">
                        <th><?php esc_html_e('Hotel Name','events-limmud'); ?></th>
                        <th><?php esc_html_e('Booked Rooms','events-limmud'); ?></th>
                        <th><?php esc_html_e('Partially Paid','events-limmud'); ?></th>
                        <th><?php esc_html_e('Awaiting Payment','events-limmud'); ?></th>
                        <th><?php esc_html_e('Not Fully Paid / Expired','events-limmud'); ?></th>
                        <th><?php esc_html_e('Waiting List','events-limmud'); ?></th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>    
                <?php
                    $col_count = 0;
                    foreach ($hotel_bookings as $key => $value) {
                        ?>
                        <tbody id="em-hotel-<?php echo $col_count ?>" >
                            <tr class="em-hotel-row">
                                <td class="hotel-name">
                                    <span class="hotel_name"><?php echo $key; ?></span>
                                </td>
                                <td class="hotel-booked-rooms">
                                    <span class="hotel_booked_rooms"><?php echo $value['approved']; ?></span>
                                </td>
                                <td class="hotel-pending-rooms">
                                    <span class="hotel_pending_rooms"><?php echo $value['partial']; ?></span>
                                </td>
                                <td class="hotel-pending-rooms">
                                    <span class="hotel_pending_rooms"><?php echo $value['pending']; ?></span>
                                </td>
                                <td class="hotel-not-fully-paid">
                                    <span class="hotel_not_fully_paid"><?php echo $value['not_fully_paid']; ?></span>
                                </td>
                                <td class="hotel-waiting-list">
                                    <span class="hotel_waiting_list"><?php echo $value['waiting_list']; ?></span>
                                </td>
                            </tr>
                        </tbody>
                        <?php
                        $col_count++;
                    }
                ?>
            </table>
            </div>
        </div>
        <?php
        }
    }

}

EM_Limmud_Tickets::init();