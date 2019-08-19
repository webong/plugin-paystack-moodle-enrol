<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Listens for Instant Payment Notification from Paystack
 *
 * This script waits for Payment notification from Paystack,
 * then double checks that data by sending it back to Paystack.
 * If Paystack verifies this then it sets up the enrolment for that
 * user.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Disable moodle specific debug messages and any errors in output,
// comment out when debugging or better look into error log!
define('NO_DEBUG_DISPLAY', true);

require('../../config.php');
require_once('lib.php');
if ($CFG->version < 2018101900) {
    require_once($CFG->libdir . '/eventslib.php');
}
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/filelib.php');


require_login();
// Paystack does not like when we return error messages here,
// the custom handler just logs exceptions and stops.
set_exception_handler('enrol_paystack_charge_exception_handler');

// Keep out casual intruders.
if (empty($_POST) or !empty($_GET)) {
    http_response_code(400);
    throw new moodle_exception('invalidrequest', 'core_error');
}

$data = new stdClass();

foreach ($_POST as $key => $value) {
    if ($key !== clean_param($key, PARAM_ALPHANUMEXT)) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null, $key);
    }
    if (is_array($value)) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Unexpected array param: ' . $key);
    }
    $req .= "&$key=" . urlencode($value);
    $data->$key = fix_utf8($value);
}

if (empty($data->custom)) {
    throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Missing request param: custom');
}

$custom = explode('-', $data->custom);
unset($data->custom);

if (empty($custom) || count($custom) < 3) {
    throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Invalid value of the request param: custom');
}

$data->userid           = (int) $custom[0];
$data->courseid         = (int) $custom[1];
$data->instanceid       = (int) $custom[2];
$data->payment_gross    = $data->amount;
$data->payment_currency = $data->currency_code;
$data->timeupdated      = time();

// Get the user and course records.
if (!$user = $DB->get_record("user", array("id" => $data->userid))) {
    message_paystack_error_to_admin("Not a valid user id", $data);
    redirect($CFG->wwwroot);
}

if (!$course = $DB->get_record("course", array("id" => $data->courseid))) {
    message_paystack_error_to_admin("Not a valid course id", $data);
    redirect($CFG->wwwroot);
}

if (!$context = context_course::instance($course->id, IGNORE_MISSING)) {
    message_paystack_error_to_admin("Not a valid context id", $data);
    redirect($CFG->wwwroot);
}

$PAGE->set_context($context);

if (!$plugininstance = $DB->get_record("enrol", array("id" => $data->instanceid, "status" => 0))) {
    message_paystack_error_to_admin("Not a valid instance id", $data);
    redirect($CFG->wwwroot);
}

// If currency is incorrectly set then someone maybe trying to cheat the system.

if ($data->courseid != $plugininstance->courseid) {
    message_paystack_error_to_admin("Course Id does not match to the course settings, received: " . $data->courseid, $data);
    redirect($CFG->wwwroot);
}

$plugin = enrol_get_plugin('paystack');

// Check that amount paid is the correct amount.
if ((float) $plugininstance->cost <= 0) {
    $cost = (float) $plugin->get_config('cost');
} else {
    $cost = (float) $plugininstance->cost;
}

// Use the same rounding of floats as on the enrol form.
$cost = format_float($cost, 2, false);

try {
    $paystackUrl = "https://api.paystack.co";
    $c = new curl();
    $options = array(
        'returntransfer' => true,
        'httpheader' => array('application/x-www-form-urlencoded', "Host: $paypaladdr"),
        'timeout' => 30,
        'CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1,
    );
    $location = "https://$paystackUrl/transaction/verify/$data->";
    $result = $c->post($location, $req, $options);

    Paystack::setApiKey($plugin->get_config('secretkey'));
    $charge1 = Paystack_Customer::create(array(
        "email" => required_param('paystackEmail', PARAM_EMAIL),
        "description" => get_string('charge_description1', 'enrol_paystack')
    ));
    $charge = Paystack_Charge::create(array(
        "amount" => $cost * 100,
        "currency" => $plugininstance->currency,
        "card" => required_param('paystackToken', PARAM_RAW),
        "description" => get_string('charge_description2', 'enrol_paystack'),
        "receipt_email" => required_param('paystackEmail', PARAM_EMAIL)
    ));
    // Send the file, this line will be reached if no error was thrown above.
    $data->txn_id = $charge->balance_transaction;
    $data->tax = $charge->amount / 100;
    $data->memo = $charge->id;
    $data->payment_status = $charge->status;
    $data->pending_reason = $charge->failure_message;
    $data->reason_code = $charge->failure_code;

    // ALL CLEAR !

    $DB->insert_record("enrol_paystack", $data);

    if ($plugininstance->enrolperiod) {
        $timestart = time();
        $timeend   = $timestart + $plugininstance->enrolperiod;
    } else {
        $timestart = 0;
        $timeend   = 0;
    }

    // Enrol user.
    $plugin->enrol_user($plugininstance, $user->id, $plugininstance->roleid, $timestart, $timeend);

    // Pass $view=true to filter hidden caps if the user cannot see them.
    if ($users = get_users_by_capability(
        $context,
        'moodle/course:update',
        'u.*',
        'u.id ASC',
        '',
        '',
        '',
        '',
        false,
        true
    )) {
        $users = sort_by_roleassignment_authority($users, $context);
        $teacher = array_shift($users);
    } else {
        $teacher = false;
    }

    $mailstudents = $plugin->get_config('mailstudents');
    $mailteachers = $plugin->get_config('mailteachers');
    $mailadmins   = $plugin->get_config('mailadmins');
    $shortname = format_string($course->shortname, true, array('context' => $context));


    if (!empty($mailstudents)) {
        $a = new stdClass();
        $a->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";

        $eventdata = new \core\message\message();
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_paystack';
        $eventdata->name              = 'paystack_enrolment';
        $eventdata->userfrom          = empty($teacher) ? core_user::get_support_user() : $teacher;
        $eventdata->userto            = $user;
        $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
        $eventdata->fullmessage       = get_string('welcometocoursetext', '', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }

    if (!empty($mailteachers) && !empty($teacher)) {
        $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->user = fullname($user);

        $eventdata = new \core\message\message();
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_paystack';
        $eventdata->name              = 'paystack_enrolment';
        $eventdata->userfrom          = $user;
        $eventdata->userto            = $teacher;
        $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
        $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }

    if (!empty($mailadmins)) {
        $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->user = fullname($user);
        $admins = get_admins();
        foreach ($admins as $admin) {
            $eventdata = new \core\message\message();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_paystack';
            $eventdata->name              = 'paystack_enrolment';
            $eventdata->userfrom          = $user;
            $eventdata->userto            = $admin;
            $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
            $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);
        }
    }

    $destination = "$CFG->wwwroot/course/view.php?id=$course->id";

    $fullname = format_string($course->fullname, true, array('context' => $context));

    if (is_enrolled($context, null, '', true)) { // TODO: use real paystack check.
        redirect($destination, get_string('paymentthanks', '', $fullname));
    } else {   // Somehow they aren't enrolled yet!
        $PAGE->set_url($destination);
        echo $OUTPUT->header();
        $a = new stdClass();
        $a->teacher = get_string('defaultcourseteacher');
        $a->fullname = $fullname;
        notice(get_string('paymentsorry', '', $a), $destination);
    }
} catch (Paystack_CardError $e) {
    // Catch the errors in any way you like.
    echo 'Error';
}

// Catch the errors in any way you like.

catch (Paystack_InvalidRequestError $e) {
    // Invalid parameters were supplied to Paystack's API.
    echo 'Invalid parameters were supplied to Paystack\'s API';
} catch (Paystack_AuthenticationError $e) {
    // Authentication with Paystack's API failed
    // (maybe you changed API keys recently).
    echo 'Authentication with Paystack\'s API failed';
} catch (Paystack_ApiConnectionError $e) {
    // Network communication with Paystack failed.
    echo 'Network communication with Paystack failed';
} catch (Paystack_Error $e) {

    // Display a very generic error to the user, and maybe send
    // yourself an email.
    echo 'Paystack Error';
} catch (Exception $e) {

    // Something else happened, completely unrelated to Paystack.
    echo 'Something else happened, completely unrelated to Paystack';
}


// --- HELPER FUNCTIONS --------------------------------------------------------------------------------------!

/**
 * Send payment error message to the admin.
 *
 * @param string $subject
 * @param stdClass $data
 */
function message_paystack_error_to_admin($subject, $data)
{
    $admin = get_admin();
    $site = get_site();

    $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";

    foreach ($data as $key => $value) {
        $message .= s($key) . " => " . s($value) . "\n";
    }

    $eventdata = new stdClass();
    $eventdata->modulename        = 'moodle';
    $eventdata->component         = 'enrol_paystack';
    $eventdata->name              = 'paystack_enrolment';
    $eventdata->userfrom          = $admin;
    $eventdata->userto            = $admin;
    $eventdata->subject           = "STRIPE PAYMENT ERROR: " . $subject;
    $eventdata->fullmessage       = $message;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';
    $eventdata->smallmessage      = '';
    message_send($eventdata);
}
