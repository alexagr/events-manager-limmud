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
        var HOTEL_SHLOMO;
        var HOTEL_CLUB;
        var HOTEL_ASTORIA;
        var HOTEL_INVALID;
        var ROOM_DOUBLE;
        var ROOM_FAMILY;
        var ROOM_TRIPLE;
        var ROOM_SINGLE;
        var ROOM_INVALID;
        var BUS_NO;
        var BUS_TEL_AVIV;
        var BUS_JERUSALEM;
        var BUS_HAIFA;
        var BUS_INVALID;
        var TICKET_3_DAYS;
        var TICKET_1_DAY;
        var TICKET_INVALID;

        function initGlobals() {
            if (!FIRST_RUN) {
                return;
            }
            FIRST_RUN = false;
            var hotelName = document.getElementsByName("hotel_name");
            if (hotelName.length > 0) {
                HOTEL_SHLOMO = hotelName[0].options[0].text;
                HOTEL_CLUB = hotelName[0].options[1].text;
                HOTEL_ASTORIA = hotelName[0].options[2].text;
                HOTEL_INVALID = hotelName[0].options[3].text;
            }

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
                BUS_TEL_AVIV = busNeeded[0].options[1].text;
                BUS_JERUSALEM = busNeeded[0].options[2].text;
                BUS_HAIFA = busNeeded[0].options[3].text;
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

        function displaySubmit(status) {
            displayElement("em-booking-submit", status);

            var els = document.getElementsByClassName("em-booking-submit");
            if (els.length > 0) {
                if (status) {
                    els[0].setAttribute("type", "submit");
                } else {
                    els[0].setAttribute("type", "");
                }
            }
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
            var toddlers = 0;
            var registration = "";
            var participation_type = ""; 

            var d1 = new Date("2022-12-03");
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
                        } else {
                            toddlers++;
                        }
                    }
                }

                var participation_types = els[i].querySelectorAll(".input-field-participation_type");
                for (var j = 0; j < participation_types.length; j++) {
                    if (participation_types[j].querySelector(".participation_type").selectedIndex == 0) {
                        participation_types[j].nextElementSibling.style.display = 'none';
                    } else {
                        participation_types[j].nextElementSibling.style.display = 'block';
                    }
                }
            }
            
            var els = document.getElementsByName("em_tickets[250][spaces]");
            if (els.length > 0) {
                registration = "regular";
                participation_type = "hotel";
            } else {
                els = document.getElementsByName("em_tickets[257][spaces]");
                if (els.length > 0) {
                    registration = "no-accomodation";
                    participation_type = "no-accomodation";
                } else {
                    els = document.getElementsByName("em_tickets[260][spaces]");
                    if (els.length > 0) {
                        registration = "volunteers";
                    }
                }
            }

			if (!registration) {
				return;
			}

            els = document.getElementsByName("summary_label");
            if (els.length > 0) {
                var summary_text = "";
                if (document.getElementsByTagName("html")[0].getAttribute("lang").includes("ru"))
                {
                    if ((adults == 0) && (kids == 0)) {
                        summary_text += "нет ни одного участника старше 3 лет";
                    } else {
                        if (adults == 1) {
                            summary_text += "один взрослый участник (старше 12 лет)";
                        }
                        else if (adults > 1) {
                            summary_text += adults.toString() + " взрослых участника (старше 12 лет)";
                        }
                        if ((kids > 0) && (summary_text != "")) {
                            if (toddlers > 0) {
                                summary_text += ", ";
                            } else {
                                summary_text += " и ";
                            }
                        }
                        if (kids == 1) {
                            summary_text += "один ребенок (от 3 до 11 лет)";
                        }
                        else if (kids > 1) {
                            summary_text += kids.toString() + " ребенка (от 3 до 11 лет)";
                        }
                        if (toddlers > 0) {
                            summary_text += " и ";
                            if (toddlers == 1) {
                                summary_text += "один малыш (младше 3 лет)";
                            } else {
                                summary_text += toddlers.toString() + " малыша (младше 3 лет)";
                            }
                        }
                    }
                    els[0].innerText = "В вашей регистрации " + summary_text + ".";
                }
                else if (document.getElementsByTagName("html")[0].getAttribute("lang").includes("he"))
                {
                    if ((adults == 0) && (kids == 0)) {
                        summary_text += "אין אף משתתף מעל גיל 3";
                    } else {
                        if (adults == 1) {
                            summary_text += "משתתף מבוגר אחד (מעל גיל 12)";
                        }
                        else if (adults > 1) {
                            summary_text += adults.toString() + " משתתפים מבוגרים (מעל גיל 12)";
                        }
                        if ((kids > 0) && (summary_text != "")) {
                            if (toddlers > 0) {
                                summary_text += ", ";
                            } else {
                                summary_text += " ו";
                            }
                        }
                        if (kids == 1) {
                            summary_text += "ילד אחד (מגיל 3 עד 11)";
                        }
                        else if (kids > 1) {
                            summary_text += kids.toString() + " ילדים (מגיל 3 עד 11)";
                        }
                        if (toddlers > 0) {
                            summary_text += " ו";
                            if (toddlers == 1) {
                                summary_text += "תינוק אחד (עד גיל 3)";
                            } else {
                                summary_text += toddlers.toString() + " תינוקות (עד גיל 3)";
                            }
                        }
                    }
                    els[0].innerText = "בהרשמתכם " + summary_text + ".";
                }
                else
                {
                    els[0].innerText = "";
                }
            }

            if ((adults == 0) || (adults > 3)) {
                displayElementByName("too_little_adults_label", (adults == 0));
                displayElementByName("room_label", false);
                displayElementByName("too_many_adults_label", (adults > 3));
                displayElementByName("children_label", false);
                displayElementByName("hotel_label", false);
                displayElementByName("astoria_label", false);
                displaySubmit(false);
                displayField("hotel_name", false);
                displayField("room_type", false);
                displayField("bus_needed", false);
                return;
            }

            displayElementByName("too_little_adults_label", false);
            displayElementByName("too_many_adults_label", false);
            
            els = document.getElementsByName("participation_type");
            if (els.length > 0) {
                if (els[0].selectedIndex == 0) {
                    participation_type = "hotel";
                } else {
                    participation_type = "no-accomodation";
                }
            }
            
            initGlobals();

            var display_room_label = false;
            var display_hotel_label = false;
            var display_children_label = false;
            var display_astoria_label = false;
            if (participation_type == "hotel") {
                displayField("hotel_name", true);

                if (document.getElementById('hotels-solomon')) {
                    updateComboBox("hotel_name", [HOTEL_SHLOMO]);
                } else if (document.getElementById('hotels-club')) {
                    updateComboBox("hotel_name", [HOTEL_CLUB]);
                } else if (document.getElementById('hotels-astoria')) {
                    updateComboBox("hotel_name", [HOTEL_ASTORIA]);
                } else if (document.getElementById('hotels-solomon-club')) {
                    updateComboBox("hotel_name", [HOTEL_SHLOMO, HOTEL_CLUB]);
                } else if (document.getElementById('hotels-solomon-astoria')) {
                    updateComboBox("hotel_name", [HOTEL_SHLOMO, HOTEL_ASTORIA]);
                } else if (document.getElementById('hotels-club-astoria')) {
                    updateComboBox("hotel_name", [HOTEL_CLUB, HOTEL_ASTORIA]);
                } else {
                    updateComboBox("hotel_name", [HOTEL_SHLOMO, HOTEL_CLUB, HOTEL_ASTORIA]);
                }
                
                displayField("room_type", true);
                var hotelName = document.getElementsByName("hotel_name")[0];
                if (kids == 0) {
                    if (adults == 1) {
                        updateComboBox("room_type", [ROOM_DOUBLE, ROOM_TRIPLE, ROOM_SINGLE]);
                    } else {
                        updateComboBox("room_type", [ROOM_DOUBLE, ROOM_TRIPLE]);
                    }

                    var roomType = document.getElementsByName("room_type")[0];
                    if ((roomType.selectedIndex == 0) && (adults != 2)) {
                        display_room_label = true;
                    }
                    if ((roomType.selectedIndex == 1) && (adults != 3)) {
                        display_room_label = true;
                    }
                } else {
                    updateComboBox("room_type", [ROOM_FAMILY]);
                    if (kids + adults > 5) {
                        display_room_label = true;
                    }

                    if (hotelName.options[hotelName.selectedIndex].text.startsWith("King Solomon")) {
                        if (kids + adults == 5) {
                            display_hotel_label = true;
                        } else {
                            display_children_label = true;
                        }
                    }
                }

                if (hotelName.options[hotelName.selectedIndex].text.startsWith("Astoria") && (kids + adults > 2)) {
                    display_astoria_label = true;
                }

                displayField("bus_needed", true);
                updateComboBox("bus_needed", [BUS_NO, BUS_TEL_AVIV, BUS_JERUSALEM, BUS_HAIFA]);
                displayField("ticket_days", false);
                updateComboBox("ticket_days", [TICKET_INVALID]);
            } else {
                displayField("hotel_name", false);
                updateComboBox("hotel_name", [HOTEL_INVALID]);
                displayField("room_type", false);
                updateComboBox("room_type", [ROOM_INVALID]);
                displayField("bus_needed", false);
                updateComboBox("bus_needed", [BUS_INVALID]);
                displayField("ticket_days", true);
                updateComboBox("ticket_days", [TICKET_3_DAYS, TICKET_1_DAY]);
            }
                     
            displayElementByName("room_label", display_room_label);
            displayElementByName("hotel_label", display_hotel_label);
            displayElementByName("children_label", display_children_label);
            displayElementByName("astoria_label", display_astoria_label);

            var display_submit = !display_room_label && !display_hotel_label && !display_astoria_label;
            displaySubmit(display_submit);
        }
        
        $("select").change(updateForm);
        $(".em-tickets tbody").on("change", ".input-date-select", updateForm);
        $(".em-tickets tbody").on("change", ".input-field-participation_type", updateForm);
        updateForm();

    <?php
	}     
}

EM_Limmud_Frontend::init();
