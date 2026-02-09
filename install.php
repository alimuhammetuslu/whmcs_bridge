<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

// Table to store WHMCS Client Mapping
if (!$CI->db->table_exists(db_prefix() . 'whmcs_client_map')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "whmcs_client_map` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `perfex_client_id` int(11) NOT NULL,
      `whmcs_client_id` int(11) NOT NULL,
      `email` varchar(191) NOT NULL,
      `last_synced` datetime DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `perfex_client_id` (`perfex_client_id`),
      KEY `whmcs_client_id` (`whmcs_client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// Table to store WHMCS Invoice Mapping
if (!$CI->db->table_exists(db_prefix() . 'whmcs_invoice_map')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "whmcs_invoice_map` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `perfex_invoice_id` int(11) NOT NULL,
      `whmcs_invoice_id` int(11) NOT NULL,
      `status` varchar(50) NOT NULL,
      `sync_status` varchar(50) DEFAULT 'pending', 
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `perfex_invoice_id` (`perfex_invoice_id`),
      KEY `whmcs_invoice_id` (`whmcs_invoice_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// Add Domain Custom Field to Invoices if not exists
$CI->db->where('slug', 'invoice_domain');
$exists = $CI->db->get(db_prefix() . 'customfields')->row();

if (!$exists) {
    $data = [
        'fieldto' => 'invoice',
        'name' => 'Associated Domain',
        'slug' => 'invoice_domain',
        'required' => 0,
        'type' => 'input',
        'options' => '',
        'display_inline' => 0,
        'field_order' => 0,
        'active' => 1,
        'show_on_pdf' => 1,
        'show_on_ticket_form' => 0,
        'only_admin' => 0,
        'show_on_table' => 1,
        'show_on_client_portal' => 1,
        'bs_column' => 12,
        'default_value' => ''
    ];

    if ($CI->db->field_exists('disallow_client_to_edit', db_prefix() . 'customfields')) {
        $data['disallow_client_to_edit'] = 0;
    }

    $CI->db->insert(db_prefix() . 'customfields', $data);
}

// Add settings
if (get_option('whmcs_bridge_url') === null) {
    add_option('whmcs_bridge_url', '');
}
if (get_option('whmcs_bridge_identifier') === null) {
    add_option('whmcs_bridge_identifier', '');
}
if (get_option('whmcs_bridge_secret') === null) {
    add_option('whmcs_bridge_secret', '');
}
if (get_option('whmcs_bridge_debug_mode') === null) {
    add_option('whmcs_bridge_debug_mode', '0');
}
if (get_option('whmcs_bridge_default_gateway') === null) {
    add_option('whmcs_bridge_default_gateway', 'mailin');
}
