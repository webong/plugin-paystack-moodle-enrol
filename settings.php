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
        get_string('public_desc', 'enrol_paystack'),
        '',
        PARAM_TEXT
    ));

    $currencies = enrol_get_plugin('paystack')->get_currencies();
    $settings->add(new admin_setting_configselect(
        'enrol_paystack/currency',
        get_string('set_currency', 'enrol_paystack'),
        '',
        'NGN',
        $currencies
    ));
}
