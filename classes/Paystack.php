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
 * Paystack API Class
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paystack;

defined('MOODLE_INTERNAL') || die();

class Paystack {
    public $plugin_name;
    public $public_key;
    public $secret_key;

    public function __construct($plugin, $pk, $sk){
        //configure plugin name
        //configure public key
        $this->base_url = "https://api.paystack.co/";
        $this->plugin_name = $plugin;
        $this->public_key = $pk;
        $this->secret_key = $sk;
    }
    
    /**
     * Track Payment Transactions from this Plugin
     *
     * @param string $trx_ref
     * @return void
     */
    public function log_transaction_success($reference)
    {
        //send reference to logger along with plugin name and public key
        $url = "https://plugin-tracker.paystackintegrations.com/log/charge_success";
        $params = [
            'plugin_name'  => $this->plugin_name,
            'transaction_reference' => $reference,
            'public_key' => $this->public_key
        ];
        $params_string = http_build_query($params);
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $params_string);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true); 
        //execute post
        $result = curl_exec($ch);
        //  echo $result;
    }

    /**
     * Verify Payment Transaction
     *
     * @param string $reference
     * @return void
     */
    public function verify_transaction($reference)
    {
        $paystackUrl = $this->base_url . "transaction/verify/" . $reference;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $paystackUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "authorization: Bearer " . $this->secretkey,
                "content-type: application/json",
                "cache-control: no-cache"
            ],
        ]);

        $request = curl_exec($curl);
        $res = json_decode($request, true);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (curl_errno($curl)) {
            throw new moodle_exception(
                'errpaystackconnect',
                'enrol_paystack',
                '',
                array('url' => $paystackUrl, 'response' => $res),
                json_encode($data)
            );
        }

        curl_close($curl);

        return $res;
    }
}
