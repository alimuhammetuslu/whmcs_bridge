<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Whmcs_bridge extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        // Load the library manually if CI loader fails with Namespaces
        require_once(module_dir_path(WHMCS_BRIDGE_MODULE_NAME, 'libraries/Whmcs_api.php'));
        $this->whmcs_api = new \WhmcsBridge\Libraries\WhmcsApi();
    }

    public function index()
    {
        // Dashboard view (placeholder for stats)
        $data['title'] = 'WHMCS Bridge Dashboard';
        $this->load->view('dashboard', $data);
    }

    public function settings()
    {
        if (!has_permission('whmcs_bridge', '', 'view')) {
            access_denied('WHMCS Bridge Settings');
        }

        if ($this->input->post()) {
            if (!has_permission('whmcs_bridge', '', 'edit')) {
                access_denied('WHMCS Bridge Settings');
            }
            
            $data = $this->input->post();
            
            update_option('whmcs_bridge_url', rtrim($data['settings']['whmcs_bridge_url'], '/'));
            update_option('whmcs_bridge_identifier', $data['settings']['whmcs_bridge_identifier']);
            
            // Encrypt Secret
            $secret = $data['settings']['whmcs_bridge_secret'];
            if (!empty($secret)) {
                $this->load->library('encryption');
                update_option('whmcs_bridge_secret', $this->encryption->encrypt($secret));
            }

            update_option('whmcs_bridge_debug_mode', isset($data['settings']['whmcs_bridge_debug_mode']) ? '1' : '0');
            update_option('whmcs_bridge_default_gateway', $data['settings']['whmcs_bridge_default_gateway']);

            set_alert('success', _l('settings_updated'));
            redirect(admin_url('whmcs_bridge/settings'));
        }

        $data['title'] = 'WHMCS Bridge Settings';
        $this->load->view('settings', $data);
    }

    public function test_connection()
    {
        if (!has_permission('whmcs_bridge', '', 'view')) {
            ajax_access_denied();
        }

        try {
            // Try to get a simple command, e.g. GetCurrencies
            $result = $this->whmcs_api->call('GetCurrencies');
            echo json_encode([
                'success' => true, 
                'message' => 'Connection Successful! Found ' . count($result['currencies']['currency']) . ' currencies.'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false, 
                'message' => $e->getMessage()
            ]);
        }
    }

    public function sync_products()
    {
        if (!has_permission('whmcs_bridge', '', 'create')) {
            access_denied('Sync Products');
        }

        // Fetch groups for the dropdown
        $data['groups'] = $this->db->get(db_prefix() . 'items_groups')->result_array();
        
        // Fetch Perfex base currency
        $this->db->where('isdefault', 1);
        $base_currency = $this->db->get(db_prefix() . 'currencies')->row();
        $base_currency_code = $base_currency ? $base_currency->name : 'TRY'; // Default to TRY if not found

        try {
            // Always fetch products to list them
            $apiResponse = $this->whmcs_api->getProducts();
            $data['whmcs_products'] = $apiResponse['products']['product'] ?? [];
        } catch (Exception $e) {
            $data['api_error'] = $e->getMessage();
            $data['whmcs_products'] = [];
        }

        if ($this->input->post()) {
            $selected_products = $this->input->post('products'); // Array of IDs
            $target_group_id = $this->input->post('group_id');

            if (empty($selected_products)) {
                set_alert('warning', 'No products selected.');
                redirect(admin_url('whmcs_bridge/sync_products'));
            }

            // Logic to create group if not selected
            if (empty($target_group_id)) {
                $this->db->where('name', 'Hosting');
                $group = $this->db->get(db_prefix() . 'items_groups')->row();
                
                if (!$group) {
                    $this->db->insert(db_prefix() . 'items_groups', ['name' => 'Hosting']);
                    $target_group_id = $this->db->insert_id();
                } else {
                    $target_group_id = $group->id;
                }
            }

            $count = 0;
            
            // Map products by ID for easy lookup
            $productMap = [];
            foreach ($data['whmcs_products'] as $p) {
                $productMap[$p['pid']] = $p;
            }

            foreach ($selected_products as $pid) {
                if (!isset($productMap[$pid])) continue;
                
                $product = $productMap[$pid];

                // Check if item exists by code/name
                $this->db->where('description', $product['name']); 
                $exists = $this->db->get(db_prefix() . 'items')->row();

                // Rate Logic: Try to find pricing matching Perfex Base Currency
                $rate = 0;
                $pricing = [];
                
                if (isset($product['pricing'][$base_currency_code])) {
                    $pricing = $product['pricing'][$base_currency_code];
                } elseif (isset($product['pricing']['TRY'])) {
                     foreach ($product['pricing'] as $currency => $prices) {
                         if ($currency === $base_currency_code) {
                             $pricing = $prices;
                             break;
                         }
                     }
                }
                
                if (empty($pricing)) {
                     $pricing = array_values($product['pricing'])[0] ?? [];
                }

                if (isset($pricing['annually']) && floatval(str_replace(',', '', $pricing['annually'])) > 0) {
                    $rate = floatval(str_replace(',', '', $pricing['annually']));
                    $unit = 'Year';
                } elseif (isset($pricing['monthly']) && floatval(str_replace(',', '', $pricing['monthly'])) > 0) {
                    $rate = floatval(str_replace(',', '', $pricing['monthly']));
                    $unit = 'Month';
                } else {
                    $rate = 0;
                    $unit = 'Unit';
                }
                
                $tax = 0;
                if(isset($product['tax']) && $product['tax'] == 1) {
                    $this->db->where('taxrate', 20);
                    $tax_query = $this->db->get(db_prefix() . 'taxes')->row();
                    if($tax_query) {
                        $tax = $tax_query->id;
                    }
                }

                $itemData = [
                    'description' => $product['name'],
                    'long_description' => strip_tags($product['description']),
                    'rate' => $rate,
                    'unit' => $unit,
                    'group_id' => $target_group_id,
                    'tax' => $tax
                ];

                if (!$exists) {
                    $this->db->insert(db_prefix() . 'items', $itemData);
                    $count++;
                } else {
                    $this->db->where('id', $exists->id);
                    $this->db->update(db_prefix() . 'items', ['rate' => $rate]);
                }
            }

            set_alert('success', "Synced $count new products from WHMCS.");
            redirect(admin_url('whmcs_bridge/sync_products'));
        }

        $data['base_currency'] = $base_currency_code;
        $data['title'] = 'Sync WHMCS Products';
        $this->load->view('sync_products', $data);
    }

    /**
     * AJAX: Get Client Services for Tab
     */
    public function client_services($client_id)
    {
        if (!has_permission('whmcs_bridge', '', 'view')) {
            ajax_access_denied();
        }

        // Check if mapped
        $map = $this->db->where('perfex_client_id', $client_id)->get(db_prefix() . 'whmcs_client_map')->row();
        
        $data['whmcs_client_id'] = $map ? $map->whmcs_client_id : null;
        $data['products'] = [];
        $data['domains'] = [];
        $data['error'] = '';

        if ($data['whmcs_client_id']) {
            try {
                $products = $this->whmcs_api->getClientProducts((int)$data['whmcs_client_id']);
                $data['products'] = $products['products']['product'] ?? [];

                $domains = $this->whmcs_api->getClientDomains((int)$data['whmcs_client_id']);
                $data['domains'] = $domains['domains']['domain'] ?? [];

                $orders = $this->whmcs_api->getClientOrders((int)$data['whmcs_client_id']);
                $data['orders'] = $orders['orders']['order'] ?? [];

            } catch (Exception $e) {
                $data['error'] = $e->getMessage();
            }
        }

        $this->load->view('client_services_html', $data);
    }

    /**
     * AJAX: Service Actions (Suspend/Unsuspend/Create/Cancel)
     */
    public function service_action()
    {
        if (!has_permission('whmcs_bridge', '', 'edit')) {
            echo json_encode(['success' => false, 'message' => _l('access_denied')]);
            return;
        }

        $service_id = (int)$this->input->post('service_id');
        $command = $this->input->post('command'); 

        if (!$service_id || !$command) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            return;
        }

        try {
            $result = [];
            if ($command === 'cancel_service') {
                // Cancel Service = Set Status to Cancelled
                $result = $this->whmcs_api->updateClientProduct($service_id, ['status' => 'Cancelled']);
            } elseif ($command === 'create') {
                // Activate/Accept = ModuleCreate
                $result = $this->whmcs_api->moduleCommand($service_id, 'create');
            } else {
                // Suspend/Unsuspend
                $allowed_commands = ['suspend', 'unsuspend'];
                if (!in_array($command, $allowed_commands)) {
                    throw new Exception("Invalid command");
                }
                $result = $this->whmcs_api->moduleCommand($service_id, $command);
            }

            if (isset($result['result']) && $result['result'] == 'success') {
                echo json_encode(['success' => true, 'message' => 'Command sent successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Unknown Error']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Order Actions (Cancel/Delete)
     */
    public function order_action()
    {
        if (!has_permission('whmcs_bridge', '', 'delete')) { // Orders might require higher perm
            echo json_encode(['success' => false, 'message' => _l('access_denied')]);
            return;
        }

        $order_id = (int)$this->input->post('order_id');
        $whmcs_invoice_id = (int)$this->input->post('whmcs_invoice_id');
        $command = $this->input->post('command'); 

        if (!$order_id || !$command) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            return;
        }

        try {
            $result = [];
            if ($command === 'cancel_order') {
                $result = $this->whmcs_api->cancelOrder($order_id);
                
                // If Cancel Order Successful -> Cancel linked Perfex Invoice
                if (isset($result['result']) && $result['result'] == 'success' && $whmcs_invoice_id > 0) {
                    $map = $this->db->where('whmcs_invoice_id', $whmcs_invoice_id)->get(db_prefix() . 'whmcs_invoice_map')->row();
                    
                    if ($map) {
                        $this->load->model('invoices_model');
                        $invoice = $this->invoices_model->get($map->perfex_invoice_id);
                        
                        // Safety Check: Only cancel if NOT Paid (2) and NOT Partially Paid (3)
                        // Statuses: 1=Unpaid, 2=Paid, 3=Partial, 4=Overdue, 5=Cancelled, 6=Draft
                        if ($invoice && $invoice->status != 2 && $invoice->status != 3) {
                            $this->invoices_model->mark_as_cancelled($map->perfex_invoice_id);
                            $this->db->where('id', $map->id)->update(db_prefix() . 'whmcs_invoice_map', ['status' => 'cancelled']);
                        } else {
                            // Optional: Log that cancellation was skipped due to payment status
                            log_activity("WHMCS Bridge: Skipped cancelling Invoice #{$map->perfex_invoice_id} because it is Paid or Partially Paid.");
                        }
                    }
                }

            } elseif ($command === 'delete_order') {
                $result = $this->whmcs_api->deleteOrder($order_id);
            } else {
                throw new Exception("Invalid order command");
            }

            if (isset($result['result']) && $result['result'] == 'success') {
                echo json_encode(['success' => true, 'message' => 'Order updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Unknown Error']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
