<?php

class EM_Limmud_Tickets {
    public static function init() {
        if (current_user_can('manage_others_bookings')) {
            add_action('em_bookings_event_footer', array(__CLASS__, 'output'), 11, 1);
            add_action('em_bookings_dashboard', array(__CLASS__, 'em_bookings_dashboard'), 11);
        }
    }

    public static function summarize(&$summary, $EM_Event) {
        $waiting_list_limits = array();
        $waiting_list = get_post_meta($EM_Event->post_id, '_waiting_list', true);
        if (!empty($waiting_list) && (strlen($waiting_list) > 3)) {
            $waiting_list_array = explode(",", $waiting_list);
            foreach ($waiting_list_array as $waiting_list_str) {
                $waiting_list_data = explode("=", $waiting_list_str);
                if ((count($waiting_list_data) != 2) || !is_numeric($waiting_list_data[1]))
                    continue;
    
                $key = $waiting_list_data[0];
                $value = intval($waiting_list_data[1]);
    
                $waiting_list_limits[$key] = $value;
            }
        }

        $EM_Bookings = $EM_Event->get_bookings();
        foreach ($EM_Bookings->bookings as $EM_Booking) {
            $keys = array();
            if (array_key_exists('hotel_name', $EM_Booking->booking_meta['booking'])) {
                $hotel_name = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['hotel_name'], 'ru');
                array_push($keys, $hotel_name);
            }
            if (array_key_exists('room_type', $EM_Booking->booking_meta['booking'])) {
                if (array_key_exists('participation_type', $EM_Booking->booking_meta['booking'])) {
                    $participation_type = apply_filters('translate_text', $EM_Booking->booking_meta['booking']['participation_type'], 'ru');
                    if ($participation_type == 'с проживанием')
                        array_push($keys, 'rooms');
                    else
                        array_push($keys, 'no-accomodation');
                    // add 'bookings' only if there is waiting list limit defined for it
                    foreach ($waiting_list_limits as $key => $value) {
                        if ($key == 'bookings') {
                            array_push($keys, 'bookings');
                            break;
                        }
                    }
                } else {
                    array_push($keys, 'bookings');
                }
            }
            array_push($keys, 'spaces');
            
            foreach ($keys as $key) {
                if (!array_key_exists($key, $summary))
                    $summary[$key] = array('limits'=>0, 'pending'=>0, 'partial'=>0, 'approved'=>0, 'not_fully_paid'=>0, 'waiting_list'=>0);

                if (($key == 'spaces') || ($key == 'no-accomodation')) {
                    $count = $EM_Booking->get_spaces();
                } else {
                    $count = 1;
                }

                switch ($EM_Booking->booking_status) {
                    case 0:
                    case 5:
                        if (EM_Limmud_Paypal::get_total_paid($EM_Booking) > 0) {
                            $summary[$key]['partial'] += $count;
                        } else {
                            $summary[$key]['pending'] += $count;
                        }
                        break;
                    case 1:
                        $summary[$key]['approved'] += $count;
                        break;
                    case 7:
                        $summary[$key]['not_fully_paid'] += $count;
                        break;
                    case 8:
                        $summary[$key]['waiting_list'] += $count;
                        break;
                }
            }
        }

        foreach ($waiting_list_limits as $waiting_list_key => $waiting_list_value) {
            $found = false;
            foreach ($summary as $key => $value) {
                if (str_contains($key, $waiting_list_key)) {
                    $summary[$key]['limits'] += $waiting_list_value;
                    $found = true;
                }
            }

            if (!$found) 
                $summary[$waiting_list_key] = array('limits'=>$waiting_list_value, 'pending'=>0, 'partial'=>0, 'approved'=>0, 'not_fully_paid'=>0, 'waiting_list'=>0);
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

        $summary = array();
        EM_Limmud_Tickets::summarize($summary, $EM_Event);

        if (count($summary) > 0) {
        ?>
        <div class="wrap">
            <h2><?php esc_html_e('Summary','events-limmud'); ?></h2>
            <div class="table-wrap">
            <table class="widefat">
                <thead>
                    <tr valign="top">
                        <th><?php esc_html_e('Item','events-limmud'); ?></th>
                        <th><?php esc_html_e('Approved','events-limmud'); ?></th>
                        <th><?php esc_html_e('Partially Paid','events-limmud'); ?></th>
                        <th><?php esc_html_e('Awaiting Payment','events-limmud'); ?></th>
                        <th><?php esc_html_e('Not Fully Paid / Expired','events-limmud'); ?></th>
                        <th><?php esc_html_e('Waiting List','events-limmud'); ?></th>
                        <th><?php esc_html_e('Limits','events-limmud'); ?></th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>    
                <?php
                    $col_count = 0;
                    foreach ($summary as $key => $value) {
                        ?>
                        <tbody id="em-summary-<?php echo $col_count ?>" >
                            <tr class="em-summary-row">
                                <td class="summary-item">
                                    <?php echo $key; ?>
                                </td>
                                <td class="summary-approved">
                                    <?php echo $value['approved']; ?>
                                </td>
                                <td class="summary-partial">
                                    <?php echo $value['partial']; ?>
                                </td>
                                <td class="summary-pending">
                                    <?php echo $value['pending']; ?>
                                </td>
                                <td class="summary-not-fully-paid">
                                    <?php echo $value['not_fully_paid']; ?>
                                </td>
                                <td class="summary-waiting-list">
                                    <?php echo $value['waiting_list']; ?>
                                </td>
                                <td class="summary-limits">
                                    <?php if ($value['limits'] > 0) echo $value['limits']; ?>
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

        if (!empty($events)) {
            $events_summary = array();
            foreach ($events as $EM_Event) {
                $summary = array();
                EM_Limmud_Tickets::summarize($summary, $EM_Event);
                $events_summary[$EM_Event->event_id] = $summary;
            }
        ?>
        <div class="wrap">            
            <h2><?php esc_html_e('Summary','events-limmud'); ?></h2>
            <div class="table-wrap">            
            <table class="widefat">
                <thead>
                    <tr valign="top">
                        <th><?php esc_html_e('Event','events-limmud'); ?></th>
                        <th><?php esc_html_e('Item','events-limmud'); ?></th>
                        <th><?php esc_html_e('Approved','events-limmud'); ?></th>
                        <th><?php esc_html_e('Partially Paid','events-limmud'); ?></th>
                        <th><?php esc_html_e('Awaiting Payment','events-limmud'); ?></th>
                        <th><?php esc_html_e('Not Fully Paid / Expired','events-limmud'); ?></th>
                        <th><?php esc_html_e('Waiting List','events-limmud'); ?></th>
                        <th><?php esc_html_e('Limits','events-limmud'); ?></th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>    
                <?php
            $col_count = 0;
            foreach ($events as $EM_Event) {
                $event_id = $EM_Event->event_id;
                $summary = $events_summary[$event_id];
                $summary_keys = array_keys($summary);
                sort($summary_keys);
                foreach ($summary_keys as $key) {
                    $value = $summary[$key];
                        ?>
                        <tbody id="em-summary-<?php echo $col_count ?>" >
                            <tr class="em-summary-row">
                                <td class="summary-event">
                                    <?php echo apply_filters('translate_text', $EM_Event->output('#_BOOKINGSLINK'), 'ru'); ?>
                                </td>
                                <td class="summary-item">
                                    <?php echo $key; ?>
                                </td>
                                <td class="summary-approved">
                                    <?php echo $value['approved']; ?>
                                </td>
                                <td class="summary-partial">
                                    <?php echo $value['partial']; ?>
                                </td>
                                <td class="summary-pending">
                                    <?php echo $value['pending']; ?>
                                </td>
                                <td class="summary-not-fully-paid">
                                    <?php echo $value['not_fully_paid']; ?>
                                </td>
                                <td class="summary-waiting-list">
                                    <?php echo $value['waiting_list']; ?>
                                </td>
                                <td class="summary-limits">
                                    <?php if ($value['limits'] > 0) echo $value['limits']; ?>
                                </td>
                            </tr>
                        </tbody>
                        <?php
                        $col_count++;
                }
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