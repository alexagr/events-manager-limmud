<?php

/**
 * Limmud-specific adjustments and custom coding
 */

class EM_Limmud_Frontend {
    public static function init() {
        add_action('em_booking_js', array(__CLASS__, 'em_booking_js'));
    }

	public static function em_booking_js() {
    ?>

        var FIRST_RUN = true;
        var ROOM_DOUBLE;
        var ROOM_FAMILY;
        var ROOM_TRIPLE;
        var ROOM_SINGLE;
        var ROOM_INVALID;
        var BUS_NO;
        var BUS_TEL_AVIV1;
        var BUS_TEL_AVIV2;
        var BUS_NETANIYA;
        var BUS_INVALID;
        var TICKET_3_DAYS;
        var TICKET_1_DAY;
        var TICKET_INVALID;

        function initGlobals() {
            if (!FIRST_RUN) {
                return;
            }
            FIRST_RUN = false;
            var roomType = document.getElementsByName("room_type");
            if (roomType.length > 0) {
                ROOM_DOUBLE = roomType[0].options[0].text;
                ROOM_FAMILY = roomType[0].options[1].text;
                ROOM_TRIPLE = roomType[0].options[2].text;
                ROOM_SINGLE = roomType[0].options[3].text;
                ROOM_INVALID = roomType[0].options[4].text;
            }

            var busNeeded = document.getElementsByName("bus_needed");
            if (busNeeded.length > 0) {
                BUS_NO = busNeeded[0].options[0].text;
                BUS_TEL_AVIV1 = busNeeded[0].options[1].text;
                BUS_TEL_AVIV2 = busNeeded[0].options[2].text;
                BUS_NETANIYA = busNeeded[0].options[3].text;
                BUS_INVALID = busNeeded[0].options[4].text;
            }

            var ticketDays = document.getElementsByName("ticket_days");
            if (ticketDays.length > 0) {
                TICKET_3_DAYS = ticketDays[0].options[0].text;
                TICKET_1_DAY = ticketDays[0].options[1].text;
                TICKET_INVALID = ticketDays[0].options[2].text;
            }
        }
        
        function updateComboBox(name, types) {
            var els = document.getElementsByName(name);
            if (els.length > 0 ) {
                var curType = els[0];
                var i;
    
                if (types.length == curType.options.length) {
                    var updateNeeded = false; 
                    for (i = curType.options.length - 1 ; i >= 0 ; i--) {
                        if (curType.options[i].text != types[i]) {
                            updateNeeded = true;
                        }
                    }
                    if (!updateNeeded)
                        return;
                }
                    
                for (i = curType.options.length - 1 ; i >= 0 ; i--) {
                    curType.remove(i);
                } 
    
                for (i = 0 ; i < types.length ; i++) {
                    curType.options[curType.options.length] = new Option(types[i]);
                }
                curType.value = types[0];
                curType.selectedIndex = 0;
            }
        }

        function displayElement(name, status) {
            var display = "none";
            if (status) {
                display = "block";
            } 
            var els = document.getElementsByClassName(name);
            if (els.length > 0) {
                els[0].style.display = display;
            }
        }

        function displayField(name, status) {
            displayElement("input-field-" + name, status);
        }

        function displayElementByName(name, status) {
            var display = "none";
            if (status) {
                display = "block";
            } 
            var els = document.getElementsByName(name);
            if (els.length > 0) {
                els[0].style.display = display;
            }
        }

        function updateForm() {
            var adults = 0; 
            var kids = 0;
            var registration = "";
            var participation_type = ""; 

            var d1 = new Date("2021-12-09");
            var els = document.getElementsByClassName("em-attendee-fieldset");
            for (var i = 0; i < els.length; i++) {
                var inputs = els[i].querySelectorAll(".input-date-string");
                for (var j = 0; j < inputs.length; j++) {
                    var d2 = new Date(inputs[j].value);
                    var d3 = new Date(d1 - d2);
                    var age = Math.abs(d3.getUTCFullYear() - 1970);
                    if (age >= 12) {
                        adults++;
                    } else {
                        if (age >= 3) {
                            kids++;
                        }
                    }
                }
            }
            
            var els = document.getElementsByName("em_tickets[215][spaces]");
            if (els.length > 0) {
                registration = "regular";
                participation_type = "hotel";
            } else {
                els = document.getElementsByName("em_tickets[222][spaces]");
                if (els.length > 0) {
                    registration = "no-accomodation";
                    participation_type = "no-accomodation";
                } else {
                    els = document.getElementsByName("em_tickets[226][spaces]");
                    if (els.length > 0) {
                        registration = "volunteers";
                    }
                }
            }

			if (!registration) {
				return;
			}

            if (adults == 0) {
                displayElementByName("too_little_adults_label", true);
                displayElementByName("room_label", false);
                displayElementByName("too_many_adults_label", false);
                displayElement("em-booking-submit", false);
                displayField("room_type", false);
                displayField("bus_needed", false);
                return;
            }

			displayElementByName("too_little_adults_label", false);

            if (adults > 3) {
                displayElementByName("room_label", false);
                displayElementByName("too_many_adults_label", true);
                displayElement("em-booking-submit", false);
                displayField("room_type", false);
                displayField("bus_needed", false);
                return;
            } else {
                displayElementByName("too_many_adults_label", false);
            }
            
            els = document.getElementsByName("participation_type");
            if (els.length > 0) {
                if (els[0].selectedIndex == 0) {
                    participation_type = "hotel";
                } else {
                    participation_type = "no-accomodation";
                }
            }
            
            els = document.getElementsByName("bus_needed");
            if (els.length > 0) {
                if (els[0].selectedIndex > 0) {
                    busNeeded = true;
                }
            }

            initGlobals();

            if (participation_type == "hotel") {
                displayField("room_type", true);
                displayField("bus_needed", true);
                updateComboBox("bus_needed", [BUS_NO, BUS_TEL_AVIV1, BUS_TEL_AVIV2, BUS_NETANIYA]);
                displayField("ticket_days", false);
                updateComboBox("ticket_days", [TICKET_INVALID]);
            } else {
                displayField("room_type", false);
                displayField("bus_needed", false);
                updateComboBox("bus_needed", [BUS_INVALID]);
                displayField("ticket_days", true);
                updateComboBox("ticket_days", [TICKET_3_DAYS, TICKET_1_DAY]);
            }
                     
            var display_room_label = false;
            if (participation_type == "hotel") {
                if (kids == 0) {
                    if (adults == 1) {
                        updateComboBox("room_type", [ROOM_DOUBLE, ROOM_TRIPLE, ROOM_SINGLE]);
                    } else {
                        updateComboBox("room_type", [ROOM_DOUBLE, ROOM_TRIPLE]);
                    }

                    roomType = document.getElementsByName("room_type")[0];
                    if ((roomType.selectedIndex == 0) && (adults != 2)) {
                        display_room_label = true;
                    }
                    if ((roomType.selectedIndex == 1) && (adults != 3)) {
                        display_room_label = true;
                    }
                } else {
                    updateComboBox("room_type", [ROOM_FAMILY]);
                }
            } else {
                updateComboBox("room_type", [ROOM_INVALID]);
            }
            
            displayElementByName("room_label", display_room_label);
            displayElement("em-booking-submit", !display_room_label);
        }
        
        $("select").change(updateForm);
        $(".em-tickets tbody").on("change", ".input-date-select", updateForm);
        updateForm();

    <?php
	}     
}

EM_Limmud_Frontend::init();
