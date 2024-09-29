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
        var ROOM_TRIPLE;
        var ROOM_SINGLE;
        var ROOM_FAMILY_2_1;
        var ROOM_FAMILY_2_2;
        var ROOM_FAMILY_2_3;
        var ROOM_FAMILY_1_1;
        var ROOM_FAMILY_1_2;
        var ROOM_FAMILY_1_3;
        var ROOM_INVALID;
        var BUS_NO;
        var BUS_TEL_AVIV;
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
                ROOM_TRIPLE = roomType[0].options[1].text;
                ROOM_SINGLE = roomType[0].options[2].text;
                ROOM_FAMILY_2_1 = roomType[0].options[3].text;
                ROOM_FAMILY_2_2 = roomType[0].options[4].text;
                ROOM_FAMILY_2_3 = roomType[0].options[5].text;
                ROOM_FAMILY_1_1 = roomType[0].options[6].text;
                ROOM_FAMILY_1_2 = roomType[0].options[7].text;
                ROOM_FAMILY_1_3 = roomType[0].options[8].text;
                ROOM_INVALID = roomType[0].options[9].text;
            }

            var busNeeded = document.getElementsByName("bus_needed");
            if (busNeeded.length > 0) {
                BUS_NO = busNeeded[0].options[0].text;
                BUS_TEL_AVIV = busNeeded[0].options[1].text;
                BUS_INVALID = busNeeded[0].options[2].text;
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
            var child_program = false;
            var toddlers = 0;
            var registration = "";
            var participation_type = ""; 

            var d1 = new Date("2023-12-21");
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
                        if (age >= 2) {
                            kids++;
                        } else {
                            toddlers++;
                        }
                    }
                    if ((age >= 3) && (age <= 17)) {
                        child_program = true;
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
            
            var els = document.getElementsByName("em_tickets[284][spaces]");
            if (els.length > 0) {
                registration = "regular";
                participation_type = "hotel";
            } else {
                els = document.getElementsByName("em_tickets[295][spaces]");
                if (els.length > 0) {
                    registration = "no-accomodation";
                    participation_type = "no-accomodation";
                } else {
                    els = document.getElementsByName("em_tickets[298][spaces]");
                    if (els.length > 0) {
                        registration = "volunteers";
                    } else {
                        els = document.getElementsByName("em_tickets[322][spaces]");
                        if (els.length > 0) {
                            registration = "friends";
                        }
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
                        summary_text += "нет ни одного участника старше 2 лет";
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
                            summary_text += "один ребенок (от 2 до 11 лет)";
                        }
                        else if (kids > 1) {
                            summary_text += kids.toString() + " ребенка (от 2 до 11 лет)";
                        }
                        if (toddlers > 0) {
                            summary_text += " и ";
                            if (toddlers == 1) {
                                summary_text += "один малыш (младше 2 лет)";
                            } else {
                                summary_text += toddlers.toString() + " малыша (младше 2 лет)";
                            }
                        }
                    }
                    els[0].innerText = "В вашей регистрации " + summary_text + ".";
                    els[0].style.color = "dodgerblue";
                }
                else if (document.getElementsByTagName("html")[0].getAttribute("lang").includes("he"))
                {
                    if ((adults == 0) && (kids == 0)) {
                        summary_text += "אין אף משתתף/ת מעל גיל 2";
                    } else {
                        if (adults == 1) {
                            summary_text += "משתתף/ת מבוגר/ת אחד/ת (מעל גיל 12)";
                        }
                        else if (adults > 1) {
                            summary_text += adults.toString() + " משתתפים/ות מבוגרים/ות (מעל גיל 12)";
                        }
                        if ((kids > 0) && (summary_text != "")) {
                            if (toddlers > 0) {
                                summary_text += ", ";
                            } else {
                                summary_text += " ו";
                            }
                        }
                        if (kids == 1) {
                            summary_text += "ילד/ה אחד/ת (מגיל 2 עד 11)";
                        }
                        else if (kids > 1) {
                            summary_text += kids.toString() + " ילדים/ות (מגיל 2 עד 11)";
                        }
                        if (toddlers > 0) {
                            summary_text += " ו";
                            if (toddlers == 1) {
                                summary_text += "תינוק אחד (עד גיל 2)";
                            } else {
                                summary_text += toddlers.toString() + " תינוקות (עד גיל 2)";
                            }
                        }
                    }
                    els[0].innerText = "בהרשמתכם/ן " + summary_text + ".";
                    els[0].style.color = "dodgerblue";
                }
                else
                {
                    els[0].innerText = "";
                }
            }

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
            var display_too_many_adults_label = false;
            var display_too_little_adults_label = false;
            if (participation_type == "hotel") {
                displayField("room_type", true);
                if (adults == 0) {
                    display_too_little_adults_label = true;
                    updateComboBox("room_type", [ROOM_INVALID]);
                }
                else if (adults > 3) {
                    display_too_many_adults_label = true;
                    updateComboBox("room_type", [ROOM_INVALID]);
                }
                else if (kids == 0) {
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
                    if ((adults == 2) && (kids == 1)) {
                        updateComboBox("room_type", [ROOM_FAMILY_2_1]);
                    } else if ((adults == 2) && (kids == 2)) {
                        updateComboBox("room_type", [ROOM_FAMILY_2_2]);
                    } else if ((adults == 2) && (kids == 3)) {
                        updateComboBox("room_type", [ROOM_FAMILY_2_3]);
                    } else if ((adults == 1) && (kids == 1)) {
                        updateComboBox("room_type", [ROOM_FAMILY_1_1]);
                    } else if ((adults == 1) && (kids == 2)) {
                        updateComboBox("room_type", [ROOM_FAMILY_1_2]);
                    } else if ((adults == 1) && (kids == 3)) {
                        updateComboBox("room_type", [ROOM_FAMILY_1_3]);
                    } else if (adults >= 3) {
                        updateComboBox("room_type", [ROOM_INVALID]);
                        display_too_many_adults_label = true;
                    } else {
                        updateComboBox("room_type", [ROOM_INVALID]);
                        display_room_label = true;
                    }
                }

                displayField("bus_needed", true);
                updateComboBox("bus_needed", [BUS_NO, BUS_TEL_AVIV]);
                displayField("ticket_days", false);
                updateComboBox("ticket_days", [TICKET_INVALID]);
            } else {
                displayField("room_type", false);
                updateComboBox("room_type", [ROOM_INVALID]);
                displayField("bus_needed", false);
                updateComboBox("bus_needed", [BUS_INVALID]);
                displayField("ticket_days", true);
                updateComboBox("ticket_days", [TICKET_3_DAYS, TICKET_1_DAY]);
            }
            
            displayElementByName("room_label", display_room_label);
            displayElementByName("too_many_adults_label", display_too_many_adults_label);
            displayElementByName("too_little_adults_label", display_too_little_adults_label);
            
            var child_program_label_div = document.getElementById("child_program_label_div");
            if (child_program_label_div) {
                if (child_program) {
                    child_program_label_div.style.display = "block";
                } else {
                    child_program_label_div.style.display = "none";
                }
            }

            var display_submit = !display_room_label && !display_too_many_adults_label && !display_too_little_adults_label;

            var personal_data = document.getElementsByName("personal_data");
            if ((personal_data.length > 0) && !personal_data[0].checked) {
                display_submit = false;
            }

            var photo_use = document.getElementsByName("photo_use");
            if ((photo_use.length > 0) && !photo_use[0].checked) {
                display_submit = false;
            }

            displaySubmit(display_submit);
        }
        
        $("select").change(updateForm);
        $(".em-tickets tbody").on("change", ".input-date-select", updateForm);
        $(".em-tickets tbody").on("change", ".input-field-participation_type", updateForm);
        $("#personal_data").change(updateForm);
        $("#photo_use").change(updateForm);
        updateForm();

    <?php
	}     
}

EM_Limmud_Frontend::init();
