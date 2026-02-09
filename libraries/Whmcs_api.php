<?php

namespace WhmcsBridge\Libraries;

defined('BASEPATH') or exit('No direct script access allowed');

use Exception;

/**
 * Modern PHP 8.3 Class for WHMCS API Interaction
 */
readonly class WhmcsApi
{
    private string $apiUrl;
    private string $identifier;
    private string $secret;
    private bool $debugMode;

    public function __construct()
    {
        $CI = &get_instance();
        $this->apiUrl = rtrim(get_option('whmcs_bridge_url'), '/') . '/includes/api.php';
        $this->identifier = get_option('whmcs_bridge_identifier');
        
        // Decrypt Secret
        $CI->load->library('encryption');
        $encrypted_secret = get_option('whmcs_bridge_secret');
        $this->secret = $CI->encryption->decrypt($encrypted_secret);
        
        $this->debugMode = (get_option('whmcs_bridge_debug_mode') === '1');
    }

    /**
     * Generic method to call WHMCS API
     * @throws Exception
     */
    public function call(string $action, array $params = []): array
    {
        $postFields = array_merge($params, [
            'username' => $this->identifier,
            'password' => $this->secret,
            'action' => $action,
            'responsetype' => 'json',
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->log("cURL Error: $error", 'ERROR');
            throw new Exception("WHMCS API Connection Error: $error");
        }

        curl_close($ch);

        $data = json_decode($response, true);

        if (!$data) {
            $this->log("Invalid JSON response: $response", 'ERROR');
            throw new Exception("WHMCS API returned invalid JSON.");
        }

        if (isset($data['result']) && $data['result'] === 'error') {
            $this->log("API Error ({$action}): " . $data['message'], 'ERROR');
            throw new Exception("WHMCS API Error: " . $data['message']);
        }

        $this->log("API Success ({$action})", 'INFO');
        
        return $data;
    }

    public function getClients(string $email): array
    {
        return $this->call('GetClients', ['search' => $email]);
    }

    public function addClient(array $clientData): int
    {
        $response = $this->call('AddClient', $clientData);
        return (int) $response['clientid'];
    }

    public function getProducts(): array
    {
        return $this->call('GetProducts', []); // Fetch ALL products (no filter)
    }

    public function addOrder(array $params): array
    {
        return $this->call('AddOrder', $params);
    }

    public function getClientProducts(int $clientId): array
    {
        return $this->call('GetClientsProducts', ['clientid' => $clientId]);
    }

    public function getClientDomains(int $clientId): array
    {
        return $this->call('GetClientsDomains', ['clientid' => $clientId]);
    }

    public function getClientOrders(int $clientId): array
    {
        return $this->call('GetOrders', ['userid' => $clientId, 'limitnum' => 50]);
    }

    public function moduleCommand(int $serviceId, string $command): array
    {
        // Commands: suspend, unsuspend, terminate, create
        return $this->call('ModuleCommand', [
            'serviceid' => $serviceId,
            'command' => $command
        ]);
    }

    public function updateClientProduct(int $serviceId, array $data): array
    {
        return $this->call('UpdateClientProduct', array_merge(['serviceid' => $serviceId], $data));
    }

    public function acceptOrder(int $orderId): array
    {
        return $this->call('AcceptOrder', ['orderid' => $orderId]);
    }

    public function cancelOrder(int $orderId): array
    {
        return $this->call('CancelOrder', ['orderid' => $orderId]);
    }

    public function deleteOrder(int $orderId): array
    {
        return $this->call('DeleteOrder', ['orderid' => $orderId]);
    }

    public function createInvoice(int $userId, array $items): int
    {
        // Wrapper for CreateInvoice
        // Logic to build item lines
        return 0; // Placeholder
    }

    private function log(string $message, string $level = 'INFO'): void
    {
        if ($this->debugMode || $level === 'ERROR') {
            log_activity("[WHMCS Bridge] [$level] $message");
        }
    }
}
