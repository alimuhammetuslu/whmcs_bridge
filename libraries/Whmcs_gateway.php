<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Whmcs_gateway extends App_gateway
{
    public function __construct()
    {
        /**
         * Call App_gateway __construct function
         */
        parent::__construct();

        /**
         * Gateway unique id
         * The The id must be alphanumeric
         */
        $this->setId('whmcs_bridge');

        /**
         * Gateway name
         */
        $this->setName('WHMCS Bridge Payment');

        /**
         * Add gateway settings
         */
        $this->setSettings([
            [
                'name'      => 'description',
                'label'     => 'settings_paymentmethod_description',
                'type'      => 'textarea',
                'default_value' => 'Pay securely via our Client Portal (WHMCS).',
            ],
            [
                'name'          => 'currencies',
                'label'         => 'settings_paymentmethod_currencies',
                'default_value' => 'TRY,USD,EUR',
            ],
        ]);
    }

    /**
     * Process the payment
     *
     * @param  array $data
     * @return mixed
     */
    public function process_payment($data)
    {
        $CI = &get_instance();
        
        // 1. Get Perfex Invoice ID
        $invoice_id = $data['invoiceid'];

        // 2. Look up WHMCS Invoice ID in our map table
        $map = $CI->db->where('perfex_invoice_id', $invoice_id)->get(db_prefix() . 'whmcs_invoice_map')->row();

        if (!$map) {
            // Error: No WHMCS invoice exists for this Perfex invoice yet.
            // This happens if the sync hook failed or hasn't run.
            set_alert('danger', 'WHMCS Invoice not found for this transaction. Please contact support.');
            redirect(site_url('invoice/' . $invoice_id . '/' . $data['invoice']->hash));
            return false;
        }

        $whmcs_invoice_id = $map->whmcs_invoice_id;
        $whmcs_url = rtrim(get_option('whmcs_bridge_url'), '/');

        // 3. Redirect user to WHMCS Invoice View
        // ideally, we would use AutoAuth here to log them in automatically
        redirect($whmcs_url . '/viewinvoice.php?id=' . $whmcs_invoice_id);
    }
}
