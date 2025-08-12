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

if (!defined('_PS_VERSION_')) {
    exit;
}

class Syncrosevi extends Module
{
    private $productCache = array();
    private $debug = true;  // ACTIVAR PARA DEBUG
	
    public function __construct()
    {
        $this->name = 'syncrosevi';
        $this->tab = 'administration';
        $this->version = '1.0.3';
        $this->author = 'SyncroSevi Team';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array(
            'min' => '1.7.0.0',
            'max' => '8.99.99'
        );
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('SyncroSevi - Sincronización de Pedidos');
        $this->description = $this->l('Módulo para sincronizar pedidos entre tienda madre e hijas mediante WebService');
        $this->confirmUninstall = $this->l('¿Estás seguro de que quieres desinstalar?');
    }

    private function log($message) 
    {
        if ($this->debug) {
            $logFile = dirname(__FILE__) . '/logs/syncrosevi_debug.log';
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
            error_log('SyncroSevi: ' . $message);
        }
    }

    public function install()
    {
        if (!parent::install() ||
            !$this->createTables() ||
            !$this->installTab() ||
            !$this->createInitialConfig() ||
            !$this->createFreeShippingCarrier() ||
            !$this->registerHook('actionObjectOrderHistoryAddAfter')) {
            return false;
        }
        
        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall() || 
            !$this->uninstallTab() ||
            !$this->deleteFreeShippingCarrier()) {
            return false;
        }
        
        $this->dropTables();
        $this->removeConfig();
        return true;
    }

    /**
     * ======================================================================
     * NUEVOS MÉTODOS PARA TRANSPORTISTA GRATUITO
     * ======================================================================
     */

    /**
     * Crear transportista gratuito de SyncroSevi
     */
    private function createFreeShippingCarrier()
    {
        try {
            $carrier = new Carrier();
            $carrier->name = 'SyncroSevi - Envío Gratuito';
            $carrier->active = 1;
            $carrier->is_free = 1;  // ¡ESTO ES LO CLAVE!
            $carrier->shipping_handling = 0;
            $carrier->range_behavior = 0;
            $carrier->shipping_method = Carrier::SHIPPING_METHOD_WEIGHT;
            $carrier->max_width = 0;
            $carrier->max_height = 0;
            $carrier->max_depth = 0;
            $carrier->max_weight = 0;
            $carrier->grade = 1;
            
            // Configurar delays para todos los idiomas
            $languages = Language::getLanguages(true);
            $delay = array();
            foreach ($languages as $language) {
                $delay[$language['id_lang']] = 'Envío gratuito para pedidos consolidados';
            }
            $carrier->delay = $delay;
            
            if ($carrier->add()) {
                // Guardar ID del transportista para uso posterior
                Configuration::updateValue('SYNCROSEVI_FREE_CARRIER_ID', $carrier->id);
                
                // Configurar rangos y zonas
                if ($this->setupCarrierRanges($carrier)) {
                    $this->log('✓ Transportista gratuito SyncroSevi creado correctamente (ID: ' . $carrier->id . ')');
                    return true;
                } else {
                    $carrier->delete();
                    return false;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log('✗ Error creando transportista gratuito: ' . $e->getMessage());
            return false;
        }
    }

    private function setupCarrierRanges($carrier)
{
    try {
        // PRIMERO: Cambiar el shipping_method a BY_WEIGHT para que funcione con rangos
        $carrier->shipping_method = Carrier::SHIPPING_METHOD_WEIGHT;
        $carrier->update();
        
        // Crear rango de peso (obligatorio para que funcione)
        $range_weight = new RangeWeight();
        $range_weight->id_carrier = $carrier->id;
        $range_weight->delimiter1 = 0;
        $range_weight->delimiter2 = 999999;
        
        if (!$range_weight->add()) {
            $this->log('✗ Error creando rango de peso');
            return false;
        }
        
        // Crear rango de precio (opcional pero recomendado)
        $range_price = new RangePrice();
        $range_price->id_carrier = $carrier->id;
        $range_price->delimiter1 = 0;
        $range_price->delimiter2 = 999999;
        
        if (!$range_price->add()) {
            $this->log('✗ Error creando rango de precio');
            return false;
        }
            // Asignar transportista a todas las zonas con coste 0
            $zones = Zone::getZones();
            foreach ($zones as $zone) {
                // Añadir zona al transportista
                $carrier->addZone($zone['id_zone']);
                
                // Configurar precio 0 para esta zona en rango de peso
                Db::getInstance()->insert('delivery', array(
                    'id_carrier' => $carrier->id,
                    'id_range_weight' => $range_weight->id,
                    'id_zone' => $zone['id_zone'],
                    'price' => 0
                ));
                
                // Configurar precio 0 para esta zona en rango de precio
                Db::getInstance()->insert('delivery', array(
                    'id_carrier' => $carrier->id,
                    'id_range_price' => $range_price->id,
                    'id_zone' => $zone['id_zone'],
                    'price' => 0
                ));
            }
            
            // Asignar a todos los grupos de clientes
            $groups = Group::getGroups(Configuration::get('PS_LANG_DEFAULT'));
            foreach ($groups as $group) {
                Db::getInstance()->insert('carrier_group', array(
                    'id_carrier' => $carrier->id,
                    'id_group' => $group['id_group']
                ));
            }
            
            $this->log('✓ Rangos y zonas configurados para transportista gratuito');
            return true;
            
        } catch (Exception $e) {
            $this->log('✗ Error configurando rangos del transportista: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar transportista gratuito al desinstalar
     */
    private function deleteFreeShippingCarrier()
    {
        try {
            $carrierId = Configuration::get('SYNCROSEVI_FREE_CARRIER_ID');
            
            if ($carrierId) {
                $carrier = new Carrier($carrierId);
                if (Validate::isLoadedObject($carrier)) {
                    // Eliminar rangos y relaciones
                    Db::getInstance()->delete('delivery', 'id_carrier = ' . (int)$carrierId);
                    Db::getInstance()->delete('carrier_zone', 'id_carrier = ' . (int)$carrierId);
                    Db::getInstance()->delete('carrier_group', 'id_carrier = ' . (int)$carrierId);
                    Db::getInstance()->delete('range_weight', 'id_carrier = ' . (int)$carrierId);
                    Db::getInstance()->delete('range_price', 'id_carrier = ' . (int)$carrierId);
                    
                    // Eliminar transportista
                    $carrier->delete();
                    $this->log('✓ Transportista gratuito SyncroSevi eliminado correctamente');
                }
                
                Configuration::deleteByName('SYNCROSEVI_FREE_CARRIER_ID');
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->log('✗ Error eliminando transportista gratuito: ' . $e->getMessage());
            return true; // No fallar la desinstalación por esto
        }
    }

    /**
     * ======================================================================
     * MÉTODOS DE CONFIGURACIÓN (MEJORADOS)
     * ======================================================================
     */

    private function createInitialConfig()
    {
        // Generar token de webhook
        $webhook_token = md5('syncrosevi_' . Configuration::get('PS_SHOP_NAME') . '_' . Configuration::get('PS_SHOP_EMAIL') . '_' . date('Y-m'));
        Configuration::updateValue('SYNCROSEVI_WEBHOOK_TOKEN', $webhook_token);
        
        // Crear directorio de logs
        $logDir = dirname(__FILE__) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Crear archivo .htaccess para proteger logs
        $htaccessContent = "Order Deny,Allow\nDeny from all\n";
        file_put_contents($logDir . '/.htaccess', $htaccessContent);
        
        return true;
    }

    private function removeConfig()
    {
        Configuration::deleteByName('SYNCROSEVI_WEBHOOK_TOKEN');
        Configuration::deleteByName('SYNCROSEVI_FREE_CARRIER_ID');
        return true;
    }

    private function dropTables()
    {
        $sql = array();
        
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'syncrosevi_order_lines`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'syncrosevi_order_tracking`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'syncrosevi_child_shops`';
        
        foreach ($sql as $query) {
            Db::getInstance()->execute($query);
        }
    }

    private function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminSyncrosevi';
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'SyncroSevi';
        }
        $tab->id_parent = (int)Tab::getIdFromClassName('AdminParentOrders');
        $tab->module = $this->name;
        return $tab->add();
    }

    private function uninstallTab()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminSyncrosevi');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        return false;
    }

	private function createTables()
    {
        $sql = array();

        // Tabla de configuración de tiendas hijas
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'syncrosevi_child_shops` (
    `id_child_shop` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `url` varchar(500) NOT NULL,
    `api_key` varchar(255) NOT NULL,
    `id_customer` int(11) NOT NULL,
    `id_group` int(11) NOT NULL,
    `id_address` int(11) NOT NULL,
    `id_carrier` int(11) NULL DEFAULT NULL,
    `id_order_state` int(11) NOT NULL,
    `start_order_id` int(11) NOT NULL DEFAULT 1,
    `import_states` varchar(255) NOT NULL DEFAULT "2,3,4,5",
    `active` tinyint(1) NOT NULL DEFAULT 1,
    `realtime_enabled` tinyint(1) NOT NULL DEFAULT 1,
    `webhook_secret` varchar(64) NOT NULL DEFAULT "",
    `last_webhook` datetime NULL DEFAULT NULL,
    `date_add` datetime NOT NULL,
    `date_upd` datetime NOT NULL,
    PRIMARY KEY (`id_child_shop`),
    KEY `idx_active` (`active`),
    KEY `idx_realtime` (`realtime_enabled`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        // Tabla de tracking de pedidos
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'syncrosevi_order_tracking` (
    `id_tracking` int(11) NOT NULL AUTO_INCREMENT,
    `id_original_order` int(11) NOT NULL,
    `id_child_shop` int(11) NOT NULL,
    `id_mother_order` int(11) NULL DEFAULT NULL,
    `processed` tinyint(1) NOT NULL DEFAULT 0,
    `realtime_sync` tinyint(1) NOT NULL DEFAULT 0,
    `date_sync` datetime NOT NULL,
    `date_realtime` datetime NULL DEFAULT NULL,
    `date_processed` datetime NULL DEFAULT NULL,
    PRIMARY KEY (`id_tracking`),
    UNIQUE KEY `unique_order_shop` (`id_original_order`, `id_child_shop`),
    KEY `idx_processed` (`processed`),
    KEY `idx_realtime` (`realtime_sync`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        // Tabla temporal de líneas de pedido
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'syncrosevi_order_lines` (
            `id_line` int(11) NOT NULL AUTO_INCREMENT,
            `id_child_shop` int(11) NOT NULL,
            `id_original_order` int(11) NOT NULL,
            `id_product` int(11) NOT NULL,
            `id_product_attribute` int(11) NOT NULL DEFAULT 0,
            `quantity` int(11) NOT NULL,
            `product_reference` varchar(255) NOT NULL,
            `product_name` varchar(255) NOT NULL,
            `processed` tinyint(1) NOT NULL DEFAULT 0,
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_line`),
            KEY `idx_child_shop_processed` (`id_child_shop`, `processed`),
            KEY `idx_order_processed` (`id_original_order`, `processed`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        $this->updateExistingTables();
        return true;
    }
    
   private function updateExistingTables()
{
    $columns = Db::getInstance()->executeS("SHOW COLUMNS FROM `" . _DB_PREFIX_ . "syncrosevi_child_shops`");
    $hasStartOrderId = false;
    $hasImportStates = false;
    $hasRealtimeEnabled = false;
    $hasWebhookSecret = false;
    $hasLastWebhook = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] == 'start_order_id') $hasStartOrderId = true;
        if ($column['Field'] == 'import_states') $hasImportStates = true;
        if ($column['Field'] == 'realtime_enabled') $hasRealtimeEnabled = true;
        if ($column['Field'] == 'webhook_secret') $hasWebhookSecret = true;
        if ($column['Field'] == 'last_webhook') $hasLastWebhook = true;
    }
    
    if (!$hasStartOrderId) {
        Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'syncrosevi_child_shops` ADD COLUMN `start_order_id` int(11) NOT NULL DEFAULT 1 AFTER `id_order_state`');
    }
    
    if (!$hasImportStates) {
        Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'syncrosevi_child_shops` ADD COLUMN `import_states` varchar(255) NOT NULL DEFAULT "2,3,4,5" AFTER `start_order_id`');
    }
    
    if (!$hasRealtimeEnabled) {
        Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'syncrosevi_child_shops` ADD COLUMN `realtime_enabled` tinyint(1) NOT NULL DEFAULT 1 AFTER `active`');
    }
    
    if (!$hasWebhookSecret) {
        Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'syncrosevi_child_shops` ADD COLUMN `webhook_secret` varchar(64) NOT NULL DEFAULT "" AFTER `realtime_enabled`');
        
        // Generar claves secretas para tiendas existentes
        $shops = Db::getInstance()->executeS('SELECT id_child_shop FROM `' . _DB_PREFIX_ . 'syncrosevi_child_shops` WHERE webhook_secret = ""');
        foreach ($shops as $shop) {
            $secret = bin2hex(random_bytes(32));
            Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'syncrosevi_child_shops` 
                 SET webhook_secret = "' . pSQL($secret) . '" 
                 WHERE id_child_shop = ' . (int)$shop['id_child_shop']
            );
        }
    }
    
    if (!$hasLastWebhook) {
        Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'syncrosevi_child_shops` ADD COLUMN `last_webhook` datetime NULL DEFAULT NULL AFTER `webhook_secret`');
    }
    
    // Actualizar tabla de tracking para campos realtime
    $trackingColumns = Db::getInstance()->executeS("SHOW COLUMNS FROM `" . _DB_PREFIX_ . "syncrosevi_order_tracking`");
    $hasRealtimeSync = false;
    $hasDateRealtime = false;
    
    foreach ($trackingColumns as $column) {
        if ($column['Field'] == 'realtime_sync') $hasRealtimeSync = true;
        if ($column['Field'] == 'date_realtime') $hasDateRealtime = true;
    }
    
    if (!$hasRealtimeSync) {
        Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'syncrosevi_order_tracking` ADD COLUMN `realtime_sync` tinyint(1) NOT NULL DEFAULT 0 AFTER `processed`');
    }
    
    if (!$hasDateRealtime) {
        Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'syncrosevi_order_tracking` ADD COLUMN `date_realtime` datetime NULL DEFAULT NULL AFTER `date_sync`');
    }
}

    /**
     * ======================================================================
     * HOOKS Y SINCRONIZACIÓN
     * ======================================================================
     */

    /**
     * Hook para cambios de estado de pedido
     */
    public function hookActionObjectOrderHistoryAddAfter($params)
    {
        try {
            if (!isset($params['object']) || !($params['object'] instanceof OrderHistory)) {
                return;
            }
            
            $orderHistory = $params['object'];
            $orderId = $orderHistory->id_order;
            $newStateId = $orderHistory->id_order_state;
            
            $childShops = Db::getInstance()->executeS(
                'SELECT * FROM `' . _DB_PREFIX_ . 'syncrosevi_child_shops` WHERE `active` = 1'
            );
            
            if (empty($childShops)) {
                return;
            }
            
            $this->syncSingleOrderToTemp($orderId, $newStateId, $childShops);
            
        } catch (Exception $e) {
            $this->log('Hook Error: ' . $e->getMessage());
        }
    }

    /**
     * Sincronizar un pedido específico a tabla temporal
     */
    private function syncSingleOrderToTemp($orderId, $stateId, $childShops)
    {
        if (!class_exists('SyncroSeviWebservice')) {
            require_once(dirname(__FILE__).'/classes/SyncroSeviWebservice.php');
        }

        foreach ($childShops as $shop) {
            $importStates = explode(',', $shop['import_states']);
            if (!in_array($stateId, $importStates)) {
                continue;
            }
            
            try {
                $webservice = new SyncroSeviWebservice($shop['url'], $shop['api_key'], false);
                $orders = $webservice->getNewOrders($orderId, $shop['import_states']);
                
                foreach ($orders as $order) {
                    if ($order['id'] == $orderId) {
                        $this->syncOrderToTemp($order, $shop);
                        break;
                    }
                }
                
            } catch (Exception $e) {
                $this->log('Error sincronizando pedido #' . $orderId . ' de ' . $shop['name'] . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Sincronizar un pedido a tabla temporal
     */
    private function syncOrderToTemp($order, $shop)
    {
        // Verificar si ya está sincronizado
        $existing = Db::getInstance()->getRow(
            'SELECT id_tracking FROM `' . _DB_PREFIX_ . 'syncrosevi_order_tracking` 
             WHERE id_original_order = ' . (int)$order['id'] . ' 
             AND id_child_shop = ' . (int)$shop['id_child_shop']
        );

        if ($existing) {
            return false;
        }

        // Insertar tracking
        Db::getInstance()->insert('syncrosevi_order_tracking', array(
            'id_original_order' => (int)$order['id'],
            'id_child_shop' => (int)$shop['id_child_shop'],
            'processed' => 0,
            'date_sync' => date('Y-m-d H:i:s')
        ));

        // Insertar líneas de pedido
        foreach ($order['order_rows'] as $line) {
            Db::getInstance()->insert('syncrosevi_order_lines', array(
                'id_child_shop' => (int)$shop['id_child_shop'],
                'id_original_order' => (int)$order['id'],
                'id_product' => (int)$line['product_id'],
                'id_product_attribute' => (int)$line['product_attribute_id'],
                'quantity' => (int)$line['product_quantity'],
                'product_reference' => pSQL($line['product_reference']),
                'product_name' => pSQL($line['product_name']),
                'processed' => 0,
                'date_add' => date('Y-m-d H:i:s')
            ));
        }

        return true;
    }

	/**
     * Sincronizar pedidos desde tiendas hijas
     */
    public function syncOrders()
    {
        if (!class_exists('SyncroSeviWebservice')) {
            require_once(dirname(__FILE__).'/classes/SyncroSeviWebservice.php');
        }

        $childShops = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'syncrosevi_child_shops` WHERE `active` = 1'
        );

        $syncResults = array();
        
        foreach ($childShops as $shop) {
            try {
                $webservice = new SyncroSeviWebservice($shop['url'], $shop['api_key'], true);
                $orders = $webservice->getNewOrders($shop['start_order_id'], $shop['import_states']);
                
                $syncCount = 0;
                foreach ($orders as $order) {
                    if ($this->syncOrderToTemp($order, $shop)) {
                        $syncCount++;
                    }
                }
                
                $syncResults[] = array(
                    'shop' => $shop['name'],
                    'count' => $syncCount,
                    'status' => 'success'
                );
                
            } catch (Exception $e) {
                $syncResults[] = array(
                    'shop' => $shop['name'],
                    'count' => 0,
                    'status' => 'error',
                    'message' => $e->getMessage()
                );
            }
        }
        
        return $syncResults;
    }

	/**
     * Procesar pedidos pendientes - CREAR UN SOLO PEDIDO CONSOLIDADO
     */
    public function processOrders()
    {
        $childShops = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'syncrosevi_child_shops` WHERE `active` = 1'
        );

        $processResults = array();

        foreach ($childShops as $shop) {
            try {
                // Obtener TODAS las líneas pendientes para esta tienda
                $pendingLines = Db::getInstance()->executeS(
                    'SELECT * FROM `' . _DB_PREFIX_ . 'syncrosevi_order_lines` 
                     WHERE id_child_shop = ' . (int)$shop['id_child_shop'] . ' 
                     AND processed = 0'
                );

                if (empty($pendingLines)) {
                    continue;
                }

                // Agrupar productos y sumar cantidades
                $products = array();
                foreach ($pendingLines as $line) {
                    $key = $line['id_product'] . '_' . $line['id_product_attribute'];
                    if (isset($products[$key])) {
                        $products[$key]['quantity'] += $line['quantity'];
                    } else {
                        $products[$key] = $line;
                    }
                }

                $this->log('DEBUG - Tienda ' . $shop['name'] . ': ' . count($products) . ' productos únicos agrupados');

                try {
                    Db::getInstance()->execute('START TRANSACTION');
                    
                    $orderId = null;
                    $validProducts = 0;
                    
                    try {
                        $customer = $this->validateCustomer($shop['id_customer']);
                        $address = $this->validateAddress($shop['id_address'], $customer->id);
                        $orderId = $this->createMotherOrder($products, $shop, $customer, $address);
                        $validProducts = count($products); // Aproximado
                        
                    } catch (Exception $e) {
                        $this->log('Error creando pedido: ' . $e->getMessage());
                    }
                    
                    // SIEMPRE marcar como procesado
                    Db::getInstance()->execute(
                        'UPDATE `' . _DB_PREFIX_ . 'syncrosevi_order_lines` 
                         SET processed = 1 
                         WHERE id_child_shop = ' . (int)$shop['id_child_shop'] . ' 
                         AND processed = 0'
                    );

                    Db::getInstance()->execute(
                        'UPDATE `' . _DB_PREFIX_ . 'syncrosevi_order_tracking` 
                         SET id_mother_order = ' . ($orderId ? (int)$orderId : 0) . ', 
                             processed = 1, 
                             date_processed = "' . date('Y-m-d H:i:s') . '" 
                         WHERE id_child_shop = ' . (int)$shop['id_child_shop'] . ' 
                         AND processed = 0'
                    );
                    
                    Db::getInstance()->execute('COMMIT');
                    
                    if ($orderId) {
                        $processResults[] = array(
                            'shop' => $shop['name'],
                            'order_id' => $orderId,
                            'products_count' => $validProducts,
                            'status' => 'success'
                        );
                    } else {
                        $processResults[] = array(
                            'shop' => $shop['name'],
                            'status' => 'success',
                            'message' => 'Procesado sin crear pedido (sin productos válidos)'
                        );
                    }
                } catch (Exception $e) {
                    Db::getInstance()->execute('ROLLBACK');
                    $processResults[] = array(
                        'shop' => $shop['name'],
                        'status' => 'error',
                        'message' => $e->getMessage()
                    );
                }
            } catch (Exception $e) {
                $processResults[] = array(
                    'shop' => $shop['name'],
                    'status' => 'error',
                    'message' => 'Error general: ' . $e->getMessage()
                );
            }
        }

        return $processResults;
    }
/**
     * Verificar firma de webhook de seguridad
     */
    public function verifyWebhookSignature($shopId, $orderId, $newState, $timestamp, $receivedSignature)
    {
        // Obtener clave secreta de la tienda
        $shop = Db::getInstance()->getRow(
            'SELECT webhook_secret FROM `' . _DB_PREFIX_ . 'syncrosevi_child_shops` 
             WHERE id_child_shop = ' . (int)$shopId
        );
        
        if (!$shop || empty($shop['webhook_secret'])) {
            $this->log('Webhook signature verification failed: No secret key for shop ' . $shopId);
            return false;
        }
        
        // Calcular firma esperada
        $payload = $shopId . $orderId . $newState . $timestamp;
        $expectedSignature = hash_hmac('sha256', $payload, $shop['webhook_secret']);
        
        // Comparación segura
        if (!hash_equals($expectedSignature, $receivedSignature)) {
            $this->log('Webhook signature verification failed for shop ' . $shopId . ' order ' . $orderId);
            return false;
        }
        
        return true;
    }
    
    /**
     * Sincronizar un pedido específico en tiempo real
     */
    public function syncSingleOrderRealtime($shopId, $orderId, $newState)
    {
        if (!class_exists('SyncroSeviWebservice')) {
            require_once(dirname(__FILE__).'/classes/SyncroSeviWebservice.php');
        }
        
        try {
            // Obtener configuración de la tienda
            $shop = Db::getInstance()->getRow(
                'SELECT * FROM `' . _DB_PREFIX_ . 'syncrosevi_child_shops` 
                 WHERE id_child_shop = ' . (int)$shopId . ' AND active = 1'
            );
            
            if (!$shop) {
                throw new Exception('Shop not found or inactive: ' . $shopId);
            }
            
            // Verificar que el estado está en los permitidos para importar
            $importStates = explode(',', $shop['import_states']);
            if (!in_array($newState, $importStates)) {
                $this->log('Order state ' . $newState . ' not in import list for shop ' . $shopId);
                return false;
            }
            
            // Verificar si ya existe este pedido sincronizado
            $existing = Db::getInstance()->getRow(
                'SELECT id_tracking FROM `' . _DB_PREFIX_ . 'syncrosevi_order_tracking` 
                 WHERE id_original_order = ' . (int)$orderId . ' 
                 AND id_child_shop = ' . (int)$shopId
            );
            
            if ($existing) {
                $this->log('Order ' . $orderId . ' from shop ' . $shopId . ' already synchronized');
                return false;
            }
            
            // Conectar con la tienda hija y obtener el pedido específico
            $webservice = new SyncroSeviWebservice($shop['url'], $shop['api_key'], $this->debug);
            
            // Obtener solo este pedido específico
            $orders = $webservice->getNewOrders($orderId, $shop['import_states']);
            
            // Buscar nuestro pedido en los resultados
            $targetOrder = null;
            foreach ($orders as $order) {
                if ($order['id'] == $orderId) {
                    $targetOrder = $order;
                    break;
                }
            }
            
            if (!$targetOrder) {
                throw new Exception('Order ' . $orderId . ' not found in shop ' . $shopId);
            }
            
            // Sincronizar a tabla temporal
            $synced = $this->syncOrderToTemp($targetOrder, $shop);
            
            if ($synced) {
                $this->log('✓ REALTIME: Order #' . $orderId . ' from ' . $shop['name'] . ' synchronized in real-time');
                
                // Marcar como sincronizado en tiempo real
                Db::getInstance()->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'syncrosevi_order_tracking` 
                     SET realtime_sync = 1, date_realtime = "' . date('Y-m-d H:i:s') . '"
                     WHERE id_original_order = ' . (int)$orderId . ' 
                     AND id_child_shop = ' . (int)$shopId
                );
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log('✗ REALTIME ERROR: Shop ' . $shopId . ' Order ' . $orderId . ': ' . $e->getMessage());
            return false;
        }
    }
    /**
     * ======================================================================
     * MÉTODOS DE CREACIÓN DE PEDIDOS (SIMPLIFICADOS)
     * ======================================================================
     */

    /**
     * Buscar producto por referencia/código con cache - VERSIÓN OPTIMIZADA
     */
    private function findProductByReference($reference)
    {
        if (empty($reference)) {
            return null;
        }
        
        $reference = trim($reference);
        
        // Verificar cache
        if (isset($this->productCache[$reference])) {
            return $this->productCache[$reference];
        }
        
        $result = null;
        
        // 1. COMBINACIONES: Buscar por reference
        $sql = 'SELECT pa.id_product_attribute, pa.id_product FROM ' . _DB_PREFIX_ . 'product_attribute pa ' .
               'JOIN ' . _DB_PREFIX_ . 'product p ON pa.id_product = p.id_product ' .
               'WHERE pa.reference = "' . pSQL($reference) . '" AND p.active = 1';
        
        $this->log('SQL Combinación por reference: ' . $sql);
        $combinations = Db::getInstance()->executeS($sql);
        
        if (!empty($combinations)) {
            $combination_data = $combinations[0];
            $result = array(
                'id_product' => (int)$combination_data['id_product'],
                'id_product_attribute' => (int)$combination_data['id_product_attribute'],
                'type' => 'combination',
                'found_by' => 'combination_reference'
            );
            $this->log('✓ Encontrado (combinación por reference): "' . $reference . '" -> ID: ' . $result['id_product'] . ', Combinación: ' . $result['id_product_attribute']);
        } else {
            // 2. COMBINACIONES: Buscar por código (ean13, upc, isbn)
            $sql2 = 'SELECT pa.id_product_attribute, pa.id_product FROM ' . _DB_PREFIX_ . 'product_attribute pa ' .
                    'JOIN ' . _DB_PREFIX_ . 'product p ON pa.id_product = p.id_product ' .
                    'WHERE (pa.ean13 = "' . pSQL($reference) . '" OR pa.upc = "' . pSQL($reference) . '" OR pa.isbn = "' . pSQL($reference) . '") ' .
                    'AND p.active = 1 AND (pa.ean13 != "" OR pa.upc != "" OR pa.isbn != "")';
            
            $this->log('SQL Combinación por código: ' . $sql2);
            $combinations2 = Db::getInstance()->executeS($sql2);
            
            if (!empty($combinations2)) {
                $combination_data = $combinations2[0];
                $result = array(
                    'id_product' => (int)$combination_data['id_product'],
                    'id_product_attribute' => (int)$combination_data['id_product_attribute'],
                    'type' => 'combination',
                    'found_by' => 'combination_code'
                );
                $this->log('✓ Encontrado (combinación por código): "' . $reference . '" -> ID: ' . $result['id_product'] . ', Combinación: ' . $result['id_product_attribute']);
            } else {
                // 3. PRODUCTO SIMPLE: Buscar por reference
                $sql3 = 'SELECT id_product FROM ' . _DB_PREFIX_ . 'product ' .
                        'WHERE reference = "' . pSQL($reference) . '" AND active = 1';
                
                $this->log('SQL Producto simple por reference: ' . $sql3);
                $products = Db::getInstance()->executeS($sql3);
                
                if (!empty($products)) {
                    $result = array(
                        'id_product' => (int)$products[0]['id_product'],
                        'id_product_attribute' => 0,
                        'type' => 'simple',
                        'found_by' => 'product_reference'
                    );
                    $this->log('✓ Encontrado (producto simple por reference): "' . $reference . '" -> ID: ' . $result['id_product']);
                } else {
                    // 4. PRODUCTO SIMPLE: Buscar por código (ean13, upc, isbn)
                    $sql4 = 'SELECT id_product FROM ' . _DB_PREFIX_ . 'product ' .
                            'WHERE (ean13 = "' . pSQL($reference) . '" OR upc = "' . pSQL($reference) . '" OR isbn = "' . pSQL($reference) . '") ' .
                            'AND active = 1 AND (ean13 != "" OR upc != "" OR isbn != "")';
                    
                    $this->log('SQL Producto simple por código: ' . $sql4);
                    $products2 = Db::getInstance()->executeS($sql4);
                    
                    if (!empty($products2)) {
                        $result = array(
                            'id_product' => (int)$products2[0]['id_product'],
                            'id_product_attribute' => 0,
                            'type' => 'simple',
                            'found_by' => 'product_code'
                        );
                        $this->log('✓ Encontrado (producto simple por código): "' . $reference . '" -> ID: ' . $result['id_product']);
                    } else {
                        $this->log('✗ Producto NO encontrado en ninguna búsqueda: "' . $reference . '"');
                    }
                }
            }
        }
        
        // Guardar en cache
        $this->productCache[$reference] = $result;
        
        return $result;
    }

    private function validateCustomer($customerId)
    {
        $customer = new Customer($customerId);
        if (!Validate::isLoadedObject($customer)) {
            throw new Exception('Cliente ID ' . $customerId . ' no encontrado');
        }
        return $customer;
    }

    private function validateAddress($addressId, $customerId)
    {
        $address = new Address($addressId);
        if (!Validate::isLoadedObject($address)) {
            throw new Exception('Dirección ID ' . $addressId . ' no encontrada');
        }
        
        if ($address->id_customer != $customerId) {
            throw new Exception('La dirección no pertenece al cliente especificado');
        }
        
        return $address;
    }

    /**
     * Crear pedido madre consolidado - MÉTODO SIMPLIFICADO
     */
    private function createMotherOrder($products, $shop, $customer = null, $address = null)
    {
        if (!$customer) {
            $customer = $this->validateCustomer($shop['id_customer']);
        }
        if (!$address) {
            $address = $this->validateAddress($shop['id_address'], $customer->id);
        }
        
        // PASO 1: Crear carrito
        $cart = $this->createSimpleCart($customer, $address, $shop);
        
        // PASO 2: Añadir productos al carrito
        $validProducts = $this->addProductsToCart($cart, $products, $shop);
        
        // PASO 3: Configurar transportista (SIMPLIFICADO)
        $this->configureCartShipping($cart, $shop);
        
        // PASO 4: Crear pedido usando el módulo Payment nativo
        $orderId = $this->createOrderFromCart($cart, $shop);
        
        $this->log('Pedido madre creado: #' . $orderId . ' con ' . $validProducts . ' productos');
        return $orderId;
    }

    /**
     * Crear carrito simple (SIN complejidad innecesaria)
     */
    private function createSimpleCart($customer, $address, $shop)
    {
        $cart = new Cart();
        $cart->id_customer = $customer->id;
		// ASEGURAR QUE EL CARRITO CONOCE EL GRUPO DEL CLIENTE
    $customer->id_default_group = $shop['id_group'];
    $customer->update();
        $cart->id_address_delivery = $address->id;
        $cart->id_address_invoice = $address->id;
        $cart->id_lang = Configuration::get('PS_LANG_DEFAULT');
        $cart->id_currency = Configuration::get('PS_CURRENCY_DEFAULT');
        $cart->id_shop = Configuration::get('PS_SHOP_DEFAULT');
        $cart->id_shop_group = Shop::getGroupFromShop($cart->id_shop);
        $cart->secure_key = $customer->secure_key;
        
        if (!$cart->add()) {
            throw new Exception('Error al crear carrito');
        }
        
        $this->log('Carrito creado con ID: ' . $cart->id);
        return $cart;
    }

    /**
     * Añadir productos al carrito - VERSIÓN SIMPLIFICADA
     */
    private function addProductsToCart($cart, $products, $shop)
{
    $addedCount = 0;
    $notFoundCount = 0;
    
    // FORZAR EL GRUPO DE LA TIENDA PARA PRECIOS CORRECTOS
    $customer = new Customer($cart->id_customer);
    $originalGroupId = $customer->id_default_group;
    $customer->id_default_group = $shop['id_group'];
    $customer->update();
    
    // ESTABLECER CONTEXTO CON EL GRUPO CORRECTO
    $context = Context::getContext();
    $context->customer = $customer;
    $context->cart = $cart;
    
    foreach ($products as $product) {
            $reference = trim($product['product_reference']);
            
            if (empty($reference)) {
                continue;
            }
            
            $productFound = $this->findProductByReference($reference);
            if (!$productFound) {
                $notFoundCount++;
                $this->log('Producto NO encontrado: "' . $reference . '" - CONTINUANDO');
                continue;
            }
            
            // Asegurar stock
            $this->ensureProductStock($productFound['id_product'], $productFound['id_product_attribute'], $product['quantity']);
            
            // CONFIGURAR CONTEXTO ANTES DE AÑADIR AL CARRITO
$context = Context::getContext();
$originalCustomer = $context->customer;
$context->customer = new Customer($cart->id_customer);

// Añadir al carrito con contexto correcto
$result = $cart->updateQty(
    $product['quantity'],
    $productFound['id_product'],
    $productFound['id_product_attribute'],
    false,  // $id_customization
    'up',   // $operator
    $cart->id_address_delivery,
    new Shop($cart->id_shop),
    false   // $auto_add_cart_rule
);

// Restaurar contexto original
$context->customer = $originalCustomer;
            
            if ($result) {
                $addedCount++;
                $this->log('✓ "' . $reference . '" añadido (Cantidad: ' . $product['quantity'] . ')');
            } else {
                $this->log('✗ Error añadiendo "' . $reference . '"');
            }
        }
        
        $this->log('RESUMEN: ' . $addedCount . ' productos añadidos, ' . $notFoundCount . ' no encontrados');
    
    // RESTAURAR GRUPO ORIGINAL DEL CLIENTE
    $customer->id_default_group = $originalGroupId;
    $customer->update();
    
    if ($addedCount === 0) {
        throw new Exception('No se pudo añadir NINGÚN producto al carrito');
    }
    
    return $addedCount;
}

    /**
     * Configurar transportista del carrito - VERSIÓN SIMPLIFICADA
     */
    private function configureCartShipping($cart, $shop)
    {
        $carrierId = null;
        
        if (!empty($shop['id_carrier'])) {
            // Usar transportista específico configurado
            $carrier = new Carrier($shop['id_carrier']);
            if (Validate::isLoadedObject($carrier) && $carrier->active) {
                $carrierId = $shop['id_carrier'];
                $this->log('Usando transportista específico configurado: ' . $carrierId);
            } else {
                $this->log('Transportista configurado no válido, usando gratuito');
            }
        }
        
        if (!$carrierId) {
            // Usar nuestro transportista gratuito de SyncroSevi
            $carrierId = Configuration::get('SYNCROSEVI_FREE_CARRIER_ID');
            if (!$carrierId) {
                throw new Exception('Transportista gratuito de SyncroSevi no encontrado');
            }
            $this->log('Usando transportista gratuito de SyncroSevi: ' . $carrierId);
        }
        
        $cart->id_carrier = $carrierId;
$cart->update();

// FORZAR RECÁLCULO DE GASTOS DE ENVÍO
$cart->getPackageList(true); // Limpiar cache de paquetes
$cart->getDeliveryOptionList(null, true); // Recalcular opciones de entrega

// VERIFICAR que ahora calcula bien el envío
$shippingCost = $cart->getOrderTotal(false, Cart::ONLY_SHIPPING);
$this->log('✓ Transportista configurado para carrito ID ' . $cart->id . ': ' . $carrierId);
$this->log('✓ Gastos de envío recalculados: ' . $shippingCost . '€');

// Si sigue siendo 0, FORZAR el coste de envío en el carrito
if ($shippingCost == 0) {
    $manualShippingCost = $cart->getPackageShippingCost($carrierId);
    $this->log('DEBUG: Coste manual del transportista ' . $carrierId . ': ' . $manualShippingCost . '€');
    
    if ($manualShippingCost > 0) {
        $this->log('FORZANDO gastos de envío en el carrito...');
        
        // FORZAR la delivery option en el carrito
        $delivery_option = array($cart->id_address_delivery => $carrierId . ',');
        $cart->setDeliveryOption($delivery_option);
        $cart->update();
        
        // VERIFICAR de nuevo
        $newShippingCost = $cart->getOrderTotal(false, Cart::ONLY_SHIPPING);
        $this->log('Nuevo coste de envío tras forzar: ' . $newShippingCost . '€');
        
        // Si TODAVÍA sigue a 0, usar método directo
        if ($newShippingCost == 0) {
            $this->forceShippingCostInCart($cart, $manualShippingCost);
        }
    }
}
}
/**
 * Forzar coste de envío directamente en el carrito
 */
private function forceShippingCostInCart($cart, $shippingCost)
{
    $this->log('MÉTODO DIRECTO: Forzando ' . $shippingCost . '€ de envío en carrito ' . $cart->id);
    
    try {
        // Crear delivery option personalizada
        $delivery_option = array(
            $cart->id_address_delivery => $cart->id_carrier . ','
        );
        
        // Guardar en base de datos directamente
        Db::getInstance()->delete('cart_rule', 'id_cart = ' . (int)$cart->id);
        
        $cart->setDeliveryOption($delivery_option);
        $cart->update();
        
        // Forzar recálculo total
        $cart->getPackageList(true);
        $cart->getDeliveryOptionList(null, true);
        
        $finalShippingCost = $cart->getOrderTotal(false, Cart::ONLY_SHIPPING);
        $this->log('RESULTADO FINAL: Envío forzado = ' . $finalShippingCost . '€');
        
    } catch (Exception $e) {
        $this->log('ERROR forzando envío: ' . $e->getMessage());
    }
}
    /**
     * Asegurar que hay stock suficiente
     */
    private function ensureProductStock($productId, $attributeId, $requiredQty)
    {
        $currentStock = (int)StockAvailable::getQuantityAvailableByProduct($productId, $attributeId);
        
        if ($currentStock < $requiredQty) {
            $neededStock = $requiredQty - $currentStock + 100; // +100 de buffer
            
            StockAvailable::updateQuantity(
                $productId,
                $attributeId,
                $neededStock,
                Context::getContext()->shop->id
            );
            
            $this->log('Stock actualizado para producto ID ' . $productId . ' (attr: ' . $attributeId . '): +' . $neededStock . ' unidades');
        }
    }

    /**
     * Crear pedido desde carrito usando Payment module
     */
    private function createOrderFromCart($cart, $shop)
    {
       // FORZAR RECÁLCULO DEL CARRITO CON ENVÍO
// DIAGNÓSTICO: Ver qué pasa con el carrito
$productsTotal = $cart->getOrderTotal(false, Cart::ONLY_PRODUCTS);
$shippingTotal = $cart->getOrderTotal(false, Cart::ONLY_SHIPPING);  
$cartTotal = $cart->getOrderTotal(true);

$this->log('DIAGNÓSTICO CARRITO:');
$this->log('  - Productos: ' . $productsTotal . '€');
$this->log('  - Envío: ' . $shippingTotal . '€');
$this->log('  - Total: ' . $cartTotal . '€');
$this->log('  - Estado configurado: ' . $shop['id_order_state']);

if ($cartTotal <= 0) {
    throw new Exception('El carrito tiene un total de 0€, no se puede crear el pedido');
}

$this->log('Creando pedido desde carrito ID: ' . $cart->id . ' Total: ' . $cartTotal . '€');
        
        if (!$cart->id || $cart->OrderExists()) {
            throw new Exception('El carrito no es válido o ya tiene un pedido asociado');
        }
        
        // Usar PaymentModule para crear el pedido
        require_once(dirname(__FILE__) . '/classes/SyncroSeviPayment.php');
        $paymentModule = new SyncroSeviPayment();

        $paymentModule->context = Context::getContext();
        $paymentModule->context->cart = $cart;
        $paymentModule->context->customer = new Customer($cart->id_customer);
// DEBUG: Verificar transportista antes de crear pedido
$currentCarrierId = $cart->id_carrier;
$this->log('DEBUG ANTES validateOrder - Carrito ID: ' . $cart->id . ', Transportista: ' . $currentCarrierId);

// FORZAR que el carrito mantenga el transportista
$cart->id_carrier = $currentCarrierId;
$cart->update();

// Verificar de nuevo
$cart = new Cart($cart->id); // Recargar carrito
$this->log('DEBUG DESPUÉS de forzar - Carrito reloadado, Transportista: ' . $cart->id_carrier);
$result = $paymentModule->validateOrder(
    $cart->id,
    (int)$shop['id_order_state'],
    $cartTotal,
    'SyncroSevi - ' . $shop['name'],
    'Pedido consolidado de ' . $shop['name'] . ' - Fecha: ' . date('Y-m-d H:i:s'),
    array(),
    (int)$cart->id_currency,
    false,
    $cart->secure_key
);
        
        if ($result && $paymentModule->currentOrder) {
    $orderId = $paymentModule->currentOrder;
    
    // DEBUG: Verificar qué transportista tiene el pedido creado
    $order = new Order($orderId);
    $this->log('DEBUG PEDIDO CREADO - Pedido #' . $orderId . ', Transportista final: ' . $order->id_carrier);
    
    $this->log('✓ Pedido creado correctamente: #' . $orderId);
    return $orderId;
        } else {
            throw new Exception('Error al crear pedido desde carrito - ValidateOrder falló');
        }
    }

    /**
     * ======================================================================
     * MÉTODO getContent() SIMPLIFICADO
     * ======================================================================
     */
    public function getContent()
    {
        // Si es primera vez (sin tiendas), mostrar configuración inicial
        if ($this->isFirstTime()) {
            return $this->displayInitialSetup();
        }
        
        // Redirigir al controlador especializado AdminSyncroSeviController
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminSyncrosevi'));
    }

    /**
     * Verificar si es primera vez que se accede
     */
    private function isFirstTime()
    {
        $shopsCount = (int)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'syncrosevi_child_shops`'
        );
        return $shopsCount === 0;
    }

    /**
     * Mostrar configuración inicial solo la primera vez
     */
    private function displayInitialSetup()
    {
        // Cargar assets
        $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
        $this->context->controller->addCSS($this->_path . 'views/css/modal.css');
        
        // URLs para webhook
        $webhook_token = Configuration::get('SYNCROSEVI_WEBHOOK_TOKEN');
        $base_url = (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__;
        
        $this->context->smarty->assign(array(
            'admin_url' => $this->context->link->getAdminLink('AdminSyncrosevi'),
            'ps_version' => _PS_VERSION_,
            'php_version' => phpversion(),
            'webhook_test_url' => $base_url . 'modules/syncrosevi/webhook.php?action=test&security_token=' . $webhook_token,
            'cron_sync_path' => _PS_ROOT_DIR_ . '/modules/syncrosevi/cron/syncrosevi_cron.php sync',
            'cron_process_path' => _PS_ROOT_DIR_ . '/modules/syncrosevi/cron/syncrosevi_cron.php process',
            'cron_stats_path' => _PS_ROOT_DIR_ . '/modules/syncrosevi/cron/syncrosevi_cron.php stats',
            'cron_health_path' => _PS_ROOT_DIR_ . '/modules/syncrosevi/cron/syncrosevi_cron.php health'
        ));
        
        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }
	
}
?>