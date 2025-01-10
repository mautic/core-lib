/** This section is only needed once per page if manually copying **/
if (typeof MauticPrefCenterLoaded === 'undefined') {
    var MauticPrefCenterLoaded = true;

    function togglePreferredChannel(channel) {
        var status = document.getElementById(channel).checked;
        if (status) {
            document.getElementById('lead_contact_frequency_rules_frequency_number_' + channel).disabled = false;
            document.getElementById('lead_contact_frequency_rules_frequency_time_' + channel).disabled = false;
            document.getElementById('lead_contact_frequency_rules_contact_pause_start_date_' + channel).disabled = false;
            document.getElementById('lead_contact_frequency_rules_contact_pause_end_date_' + channel).disabled = false;
        } else {
            document.getElementById('lead_contact_frequency_rules_frequency_number_' + channel).disabled = true;
            document.getElementById('lead_contact_frequency_rules_frequency_time_' + channel).disabled = true;
            document.getElementById('lead_contact_frequency_rules_contact_pause_start_date_' + channel).disabled = true;
            document.getElementById('lead_contact_frequency_rules_contact_pause_end_date_' + channel).disabled = true;
        }
    }

    function saveUnsubscribePreferences(formId) {
        var forms = document.getElementsByName(formId);
        for (var i = 0; i < forms.length; i++) {
            if (forms[i].tagName === 'FORM') {
                forms[i].submit();
            }
        }
    }
}
