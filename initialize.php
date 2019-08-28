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
 * Intialize Payment Transaction on Paystack
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require("../../config.php");
require_once("$CFG->dirroot/enrol/paystack/lib.php");

$plugin = enrol_get_plugin('paystack');
$paystack = new \enrol_paystack\Paystack('moodle-enrol', $plugin->get_publickey(), $plugin->secretkey());

$data = [
    'amount'  => $_POST['amount'], 
    'email'  => $_POST['email'],
    'reference' => $plugin->getHashedToken(),
    'callback_url'  => $_POST['callback'],

];
$res = $paystack->initialize_transaction($data);

redirect($res['data']['authorization_url']);