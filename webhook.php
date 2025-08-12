<?php
/**
 * 2024 SyncroSevi
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * 
 * @author    SyncroSevi Team
 * @copyright 2024 SyncroSevi
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

// Determinar la ruta de PrestaShop
$prestashop_root = realpath(dirname(__FILE__) . '/../../');
if (!$prestashop_root || !file_exists($prestashop_root . '/config/config.inc.php')) {
    header('HTTP/1.1 500 Internal Server Error');
    die(json_encode(array('error' => 'PrestaShop not found')));
}

// Incluir archivos de PrestaShop
require_once($prestashop_root . '/config/config.inc.php');
require_once($prestashop_root . '/init.php');

// Verificar que el módulo esté instalado y activo
if (!Module::isInstalled('syncrosevi')) {
    header('HTTP/1.1 404 Not Found');
    die(json_encode(array('error' => 'SyncroSevi module not installed')));
}

// Usar Tools::getValue() método PrestaShop
$action = Tools::getValue('action', '');
$security_token = Tools::getValue('security_token', '');

// Verificar token de seguridad mejorado (fijo por mes en lugar de diario)
// CORREGIDO: Verificar token fijo desde configuración
$expected_token = Configuration::get('SYNCROSEVI_WEBHOOK_TOKEN');

if (!$expected_token) {
    header('HTTP/1.1 500 Internal Server Error');
    die(json_encode(array('error' => 'Webhook token not configured. Please reinstall the module.')));
}

if ($security_token !== $expected_token) {
    header('HTTP/1.1 403 Forbidden');
    die(json_encode(array('error' => 'Invalid security token')));
}

// Configurar respuesta JSON
header('Content-Type: application/json');

try {
    // Cargar módulo
    $module = Module::getInstanceByName('syncrosevi');
    if (!$module || !$module->active) {
        throw new Exception('Cannot load SyncroSevi module or module is inactive');
    }
    
    // Verificar que los métodos existen
    if (!method_exists($module, 'syncOrders') || !method_exists($module, 'processOrders')) {
        throw new Exception('Required module methods not found');
    }
    
    switch ($action) {
    case 'sync':
        $results = $module->syncOrders();
        echo json_encode(array(
            'success' => true, 
            'action' => 'sync',
            'results' => $results,
            'count' => is_array($results) ? count($results) : 0,
            'timestamp' => date('Y-m-d H:i:s')
        ));
        break;
        
    case 'process':
        $results = $module->processOrders();
        echo json_encode(array(
            'success' => true, 
            'action' => 'process',
            'results' => $results,
            'count' => is_array($results) ? count($results) : 0,
            'timestamp' => date('Y-m-d H:i:s')
        ));
        break;
        
    case 'realtime_sync':
    error_log('WEBHOOK RECIBIDO: ' . json_encode($_POST));
    
    $shopId = (int)Tools::getValue('shop_id');
    $orderId = (int)Tools::getValue('order_id');
    $newState = (int)Tools::getValue('new_state');
    $timestamp = (int)Tools::getValue('timestamp');
    $signature = Tools::getValue('signature');
    
    error_log('WEBHOOK PARSED: shopId=' . $shopId . ', orderId=' . $orderId . ', newState=' . $newState . ', timestamp=' . $timestamp);
    
    // Verificar que no sea muy antiguo (máximo 5 minutos)
    if (abs(time() - $timestamp) > 300) {
        error_log('WEBHOOK ERROR: Timestamp too old');
        throw new Exception('Webhook timestamp too old');
    }
    
    // Verificar firma de seguridad
    if (!$module->verifyWebhookSignature($shopId, $orderId, $newState, $timestamp, $signature)) {
        error_log('WEBHOOK ERROR: Invalid signature');
        throw new Exception('Invalid webhook signature');
    }
    
    error_log('WEBHOOK: Firma válida, procediendo a sincronizar...');
    
    // Sincronizar este pedido específico
    $result = $module->syncSingleOrderRealtime($shopId, $orderId, $newState);
    
    error_log('WEBHOOK RESULT: ' . ($result ? 'SUCCESS' : 'FAILED'));
        
        echo json_encode(array(
            'success' => true,
            'action' => 'realtime_sync',
            'shop_id' => $shopId,
            'order_id' => $orderId,
            'new_state' => $newState,
            'synced' => $result,
            'timestamp' => date('Y-m-d H:i:s')
        ));
        break;
        
    case 'test':
        echo json_encode(array(
            'success' => true,
            'action' => 'test',
            'message' => 'Webhook funcionando correctamente',
            'prestashop_version' => _PS_VERSION_,
            'module_version' => $module->version,
            'timestamp' => date('Y-m-d H:i:s')
        ));
        break;
        
    default:
        throw new Exception('Invalid action: ' . $action . '. Valid actions: sync, process, realtime_sync, test');
}
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage(),
        'action' => $action,
        'timestamp' => date('Y-m-d H:i:s')
    ));
}
?>