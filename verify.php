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
 * Verify Payment Callback from Paystack
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require("../../config.php");
require_once("$CFG->dirroot/enrol/paystack/lib.php");

$custom = explode('-', required_param('custom', array(), PARAM_RAW));
$userid           = (int)$custom[0];
$courseid         = (int)$custom[1];
$instanceid       = (int)$custom[2];

if (!$course = $DB->get_record("course", array("id" => $courseid))) {
    redirect($CFG->wwwroot);
}

$context = context_course::instance($course->id, MUST_EXIST);

$PAGE->set_context($context);

require_login();

if (!empty($SESSION->wantsurl)) {
    $destination = $SESSION->wantsurl;
    unset($SESSION->wantsurl);
} else {
    $destination = "$CFG->wwwroot/course/view.php?id=$course->id";
}

if (empty(required_param('paystack-trxref', PARAM_RAW))) {
    notice(get_string('paystack_sorry', 'enrol_paystack'), $destination);
}

$ref = required_param('paystack-trxref', PARAM_RAW);
$fullname = format_string($course->fullname, true, array('context' => $context));

if (is_enrolled($context, NULL, '', true)) { 
    // use real paystack check
    $plugin = enrol_get_plugin('paystack');
    $plugin_instance = $DB->get_record("enrol", array("id" => $instanceid, "enrol" => "paystack", "status" => 0), "*", MUST_EXIST);
    $paystack = new \enrol_paystack\Paystack('moodle-enrol', $plugin->get_publickey(), $plugin->secretkey());
    $res = $paystack->verify_transaction($ref);
    if ($res['data']['status'] != "success") {
        $plugin->unenrol_user($plugin_instance, $userid);
        message_paystack_error_to_admin(
            "Status not successful. User unenrolled from course",
            $res
        );
        redirect($CFG->wwwroot);
    }
    redirect($destination, get_string('paymentthanks', '', $fullname));
} else {   
    // Somehow they aren't enrolled yet!  :-(
    $PAGE->set_url($destination);
    echo $OUTPUT->header();
    $a = new stdClass();
    $a->teacher = get_string('defaultcourseteacher');
    $a->fullname = $fullname;
    redirect($destination, get_string('paymentsorry', '', $a), 5);
}
