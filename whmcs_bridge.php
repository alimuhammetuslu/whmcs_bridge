<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: WHMCS Bridge
Description: Synchronize Clients, Invoices and Products between Perfex CRM and WHMCS.
Version: 1.0.1
Requires at least: 3.0.*
Author: Muhammet Ali Uslu
Author URI: https://muhammetaliuslu.com
License: GPLv3
*/

/**
 * @package   Perfex CRM to WHMCS Bridge
 * @author    Muhammet Ali USLU <iletisim@muhammetaliuslu.com>
 * @copyright 2026 Muhammet Ali USLU
 * @license   https://opensource.org/licenses/GPL-3.0 GNU General Public License version 3 (GPLv3)
 * @link      https://github.com/alimuhammetuslu/whmcs_bridge
 */

define('WHMCS_BRIDGE_MODULE_NAME', 'whmcs_bridge');

hooks()->add_action('admin_init', 'whmcs_bridge_module_init_menu_items');
hooks()->add_action('admin_init', 'whmcs_bridge_permissions');
hooks()->add_action('app_admin_head', 'whmcs_bridge_add_head_components');

// Register Payment Gateway
register_payment_gateway('whmcs_gateway', 'whmcs_bridge');

// Hook for Invoice Creation (The Trigger)
hooks()->add_action('after_invoice_added', 'whmcs_bridge_invoice_created_hook');

// Hook for Client Tabs
hooks()->add_filter('customer_profile_tabs', 'whmcs_bridge_client_tab');

/**
* Register activation module hook
*/
register_activation_hook(WHMCS_BRIDGE_MODULE_NAME, 'whmcs_bridge_module_activation_hook');

function whmcs_bridge_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
* Register language files
*/
register_language_files(WHMCS_BRIDGE_MODULE_NAME, [WHMCS_BRIDGE_MODULE_NAME]);

/**
 * Add WHMCS Tab to Client Profile
 */
function whmcs_bridge_client_tab($tabs)
{
    if (has_permission('whmcs_bridge', '', 'view')) {
        $tabs['whmcs_services'] = [
            'slug'     => 'whmcs_services',
            'name'     => 'WHMCS Services',
            'icon'     => 'fa fa-server',
            'view'     => 'whmcs_bridge/client_tab',
            'position' => 50,
            // Removing empty badge array to prevent PHP deprecation warnings in some Perfex versions
        ];
    }
    return $tabs;
}

/**
 * Init module menu items in setup in admin_init hook
 */
function whmcs_bridge_module_init_menu_items()
{
    $CI = &get_instance();

    if (has_permission('whmcs_bridge', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('whmcs-bridge', [
            'name'     => 'WHMCS Bridge',
            'icon'     => 'fa fa-server',
            'position' => 30,
        ]);

        $CI->app_menu->add_sidebar_children_item('whmcs-bridge', [
            'slug'     => 'whmcs-bridge-dashboard',
            'name'     => 'Dashboard',
            'href'     => admin_url('whmcs_bridge'),
            'position' => 5,
        ]);

        $CI->app_menu->add_sidebar_children_item('whmcs-bridge', [
            'slug'     => 'whmcs-bridge-settings',
            'name'     => 'Settings',
            'href'     => admin_url('whmcs_bridge/settings'),
            'position' => 10,
        ]);
        
        $CI->app_menu->add_sidebar_children_item('whmcs-bridge', [
            'slug'     => 'whmcs-bridge-sync',
            'name'     => 'Sync Products',
            'href'     => admin_url('whmcs_bridge/sync_products'),
            'position' => 15,
        ]);
    }
}

function whmcs_bridge_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
        'create' => _l('permission_create'),
        'edit'   => _l('permission_edit'),
        'delete' => _l('permission_delete'),
    ];

    register_staff_capabilities('whmcs_bridge', $capabilities, 'WHMCS Bridge');
}

function whmcs_bridge_add_head_components() {
    // Add custom CSS or JS if needed
}

/**
 * Hook Logic: Create Order in WHMCS when Invoice is created in Perfex
 */
function whmcs_bridge_invoice_created_hook($invoice_id)
{
    $CI = &get_instance();
    
    log_activity("WHMCS Bridge: Hook triggered for Invoice ID " . $invoice_id);

    $CI->load->model('invoices_model');
    $CI->load->model('clients_model');
    require_once(module_dir_path(WHMCS_BRIDGE_MODULE_NAME, 'libraries/Whmcs_api.php'));
    
    $invoice = $CI->invoices_model->get($invoice_id);
    if (!$invoice) {
        log_activity("WHMCS Bridge Error: Invoice not found.");
        return;
    }

    $items = $invoice->items;
    $client = $CI->clients_model->get($invoice->clientid);
    
    // Primary Contact Email Logic
    $primary_contact_id = get_primary_contact_user_id($invoice->clientid);
    $contact = $CI->clients_model->get_contact($primary_contact_id);
    $client_email = $contact ? $contact->email : $client->email; 

    $has_hosting = false;
    $whmcs_product_id = 0; 

    foreach ($items as $item) {
        $rel_id = $item['item_related_id'] ?? 0;
        $item_description = $item['description'];

        $sql = "SELECT i.description, g.name as group_name 
                FROM " . db_prefix() . "items i 
                LEFT JOIN " . db_prefix() . "items_groups g ON i.group_id = g.id 
                WHERE i.id = " . $CI->db->escape($rel_id) . " 
                OR i.description = " . $CI->db->escape($item_description) . " LIMIT 1";
        
        $item_det = $CI->db->query($sql)->row();
        
        if ($item_det) {
            log_activity("WHMCS Bridge Debug: Item '{$item_det->description}' is in Group: '" . ($item_det->group_name ?? 'None') . "'");
            
            if (isset($item_det->group_name) && preg_match('/(hosting|sunucu|server|ssl|domain|alan ad)/i', $item_det->group_name)) {
                
                log_activity("WHMCS Bridge: Item matches Hosting/Server keywords. Proceeding to sync.");
                
                try {
                    $whmcs_api = new \WhmcsBridge\Libraries\WhmcsApi();
                    $all_products = $whmcs_api->getProducts();
                    
                    if (isset($all_products['products']['product'])) {
                        foreach ($all_products['products']['product'] as $p) {
                            if (strcasecmp($p['name'], $item_det->description) === 0) {
                                $whmcs_product_id = $p['pid'];
                                $has_hosting = true;
                                log_activity("WHMCS Bridge: Matched Perfex Item to WHMCS PID: " . $whmcs_product_id);
                                break 2; 
                            }
                        }
                    }
                } catch (Exception $e) {
                    log_activity("WHMCS Bridge Product Lookup Error: " . $e->getMessage());
                }
            } else {
                log_activity("WHMCS Bridge Debug: Group name '{$item_det->group_name}' does not match hosting keywords.");
            }
        }
    }

    if (!$has_hosting) {
        log_activity("WHMCS Bridge: No hosting items found (checked by group name).");
        return; 
    }

    // Get Domain from Custom Fields
    $custom_fields = get_custom_fields('invoice');
    $domain = '';
    
    foreach ($custom_fields as $field) {
        if ($field['slug'] === 'invoice_domain') {
            $value = get_custom_field_value($invoice_id, $field['id'], 'invoice');
            if (!empty($value)) {
                $domain = trim($value);
            }
            break;
        }
    }
    
    log_activity("WHMCS Bridge: Domain detected: " . ($domain ?: 'None'));

    try {
        $api = new \WhmcsBridge\Libraries\WhmcsApi();

        // 2. Sync Client
        $whmcs_client_id = 0;
        $map = $CI->db->where('perfex_client_id', $client->userid)->get(db_prefix() . 'whmcs_client_map')->row();
        
        if ($map) {
            $whmcs_client_id = $map->whmcs_client_id;
            log_activity("WHMCS Bridge: Found mapped client in DB. WHMCS ID: " . $whmcs_client_id);
        } else {
            // Check by email
            log_activity("WHMCS Bridge: Checking WHMCS for email: " . $client_email);
            $check = $api->getClients($client_email);
            
            if (isset($check['totalresults']) && $check['totalresults'] > 0) {
                $whmcs_client_id = $check['clients']['client'][0]['id'];
                log_activity("WHMCS Bridge: Client found in WHMCS. ID: " . $whmcs_client_id);
            } else {
                log_activity("WHMCS Bridge: Client not found. Creating new client...");
                $pwd = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 12);
                $clientData = [
                    'firstname' => $contact->firstname ?? $client->company,
                    'lastname' => $contact->lastname ?? 'User',
                    'email' => $client_email,
                    'companyname' => $client->company,
                    'address1' => $client->address,
                    'city' => $client->city,
                    'state' => $client->state,
                    'postcode' => $client->zip,
                    'country' => 'TR', 
                    'phonenumber' => $client->phonenumber,
                    'password' => $pwd,
                ];
                
                $new_client = $api->addClient($clientData);
                $whmcs_client_id = $new_client;
                log_activity("WHMCS Bridge: Client created successfully. New ID: " . $whmcs_client_id);
            }
            
            if ($whmcs_client_id > 0) {
                $CI->db->insert(db_prefix() . 'whmcs_client_map', [
                    'perfex_client_id' => $client->userid,
                    'whmcs_client_id' => $whmcs_client_id,
                    'email' => $client_email,
                    'last_synced' => date('Y-m-d H:i:s')
                ]);
            } else {
                throw new Exception("Failed to get a valid Client ID from WHMCS.");
            }
        }

        // 3. Create Order (AddOrder)
        if ($whmcs_product_id > 0) {
            $default_gateway = get_option('whmcs_bridge_default_gateway');
            
            log_activity("WHMCS Bridge: Preparing AddOrder for Client ID: {$whmcs_client_id}, PID: {$whmcs_product_id}");
            
            $orderParams = [
                'clientid' => $whmcs_client_id,
                'pid' => [$whmcs_product_id], 
                'paymentmethod' => !empty($default_gateway) ? $default_gateway : 'mailin', 
                'billingcycle' => 'annually', 
                'domain' => [$domain], 
                'noemail' => true,
                'skipvalidation' => true,
            ];

            $orderResult = $api->addOrder($orderParams);
            
            log_activity("WHMCS Bridge AddOrder Result: " . json_encode($orderResult));
            
            if (isset($orderResult['result']) && $orderResult['result'] == 'success') {
                $whmcs_invoice_id = $orderResult['invoiceid'];
                
                $CI->db->insert(db_prefix() . 'whmcs_invoice_map', [
                    'perfex_invoice_id' => $invoice_id,
                    'whmcs_invoice_id' => $whmcs_invoice_id,
                    'status' => 'synced',
                    'sync_status' => 'success'
                ]);
                
                log_activity("WHMCS Bridge: Order created successfully. WHMCS Invoice ID: {$whmcs_invoice_id}");
            } else {
                throw new Exception("AddOrder Failed: " . ($orderResult['message'] ?? 'Unknown Error'));
            }
        } else {
             log_activity("WHMCS Bridge: Skipped order creation. No matching hosting product found.");
        }

    } catch (Exception $e) {
        log_activity("WHMCS Bridge Error: " . $e->getMessage());
        log_activity("WHMCS Bridge Error Trace: " . $e->getTraceAsString());
    }
}
