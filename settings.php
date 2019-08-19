<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     enrol_paystack
 * @category    admin
 * @copyright   2019 Paystack
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'enrol_paystack_enrolname_short',
        '',
        get_string('pluginname_desc', 'enrol_paystack')
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_paystack/secretkey',
        get_string('secretkey', 'enrol_paystack'),
        get_string('secretkey_desc', 'enrol_paystack'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_paystack/publickey',
        get_string('publickey', 'enrol_paystack'),
        get_string('publickey_desc', 'enrol_paystack'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_paystack/mailstudents',
        get_string('mailstudents', 'enrol_paystack'),
        '',
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_paystack/mailteachers',
        get_string('mailteachers', 'enrol_paystack'),
        '',
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_paystack/mailadmins',
        get_string('mailadmins', 'enrol_paystack'),
        '',
        0
    ));

    // Note: let's reuse the ext sync constants and strings here, internally it is very similar,
    // it describes what should happen when users are not supposed to be enrolled any more.
    $options = array(
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    );
    $settings->add(new admin_setting_configselect('enrol_paystack/expiredaction', get_string('expiredaction', 'enrol_paystack'), get_string('expiredaction_help', 'enrol_paystack'), ENROL_EXT_REMOVED_SUSPENDNOROLES, $options));


    // --- enrol instance defaults ----------------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'enrol_paystack_defaults',
        get_string('enrolinstancedefaults', 'admin'),
        get_string('enrolinstancedefaults_desc', 'admin')
    ));

    $options = array(
        ENROL_INSTANCE_ENABLED  => get_string('yes'),
        ENROL_INSTANCE_DISABLED => get_string('no')
    );
    $settings->add(new admin_setting_configselect(
        'enrol_paystack/status',
        get_string('status', 'enrol_paystack'),
        get_string('status_desc', 'enrol_paystack'),
        ENROL_INSTANCE_DISABLED,
        $options
    ));

    $currencies = enrol_get_plugin('paystack')->get_currencies();
    $settings->add(new admin_setting_configselect(
        'enrol_paystack/currency',
        get_string('set_currency', 'enrol_paystack'),
        '',
        'NGN',
        $currencies
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_paystack/maxenrolled',
        get_string('maxenrolled', 'enrol_paystack'),
        get_string('maxenrolled_help', 'enrol_paystack'),
        0,
        PARAM_INT
    ));
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect(
            'enrol_paystack/roleid',
            get_string('defaultrole', 'enrol_paystack'),
            get_string('defaultrole_desc', 'enrol_paystack'),
            $student->id,
            $options
        ));
    }
    $settings->add(new admin_setting_configduration(
        'enrol_paystack/enrolperiod',
        get_string('enrolperiod', 'enrol_paystack'),
        get_string('enrolperiod_desc', 'enrol_paystack'),
        0
    ));
}
