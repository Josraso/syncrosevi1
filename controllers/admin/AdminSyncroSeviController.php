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

class AdminSyncroseviController extends ModuleAdminController
{
public function __construct()
{
    $this->bootstrap = true;
    $this->context = Context::getContext();
    $this->lang = false;
    
    parent::__construct();
    
    $this->meta_title = $this->l('SyncroSevi - Gestión de Sincronización');
}

public function initContent()
{
    parent::initContent();
    
    $action = Tools::getValue('action');
    
    switch ($action) {
        case 'reset_shop':
            $this->processResetShop();
            break;
        case 'add_shop':
            $this->processAddShop();
            break;
        case 'edit_shop':
            $this->processEditShop();
            break;
        case 'edit':
            $this->displayEditShop();
            return;
        case 'update_shop':
            $this->processEditShop();
            break;
        case 'delete_shop':
            $this->processDeleteShop();
            break;
        case 'test_connection':
            $this->processTestConnection();
            break;
        case 'sync_orders':
            $this->processSyncOrders();
            break;
        case 'process_orders':
            $this->processOrders();
            break;
        case 'view_pending':
            $this->displayPendingOrders();
            return;
        case 'view_processed':
            $this->displayProcessedOrders();
            return;
        case 'get_processed_orders':
            $this->getProcessedOrdersAjax();
            return;
        case 'get_order_details':
            $this->getOrderDetailsAjax();
            return;
        case 'get_states':
            $this->getChildShopStatesAjax();
            return;
        case 'get_order_products':
            $this->getOrderProductsAjax();
            return;
        case 'delete_pending_order':
            $this->deletePendingOrderAjax();
            return;
        case 'regenerate_webhook_secret':
            $this->regenerateWebhookSecret();
            return;
        case 'get_webhook_info':
            $this->getWebhookInfoAjax();
            return;
        case 'export_processed':
            $this->exportProcessedOrders();
            return;
			case 'download_child_module':
    $this->downloadChildModule();
    return;
        default:
            $this->displayMainContent();
            break;
    }
}
/**
 * Restablecer datos de una tienda
 */
protected function processResetShop()
{
    $id_shop = (int)Tools::getValue('id_child_shop');
    
    if (!$id_shop) {
        $this->errors[] = $this->l('ID de tienda inválido');
        return;
    }
    
    try {
        Db::getInstance()->execute('START TRANSACTION');
        
        // Eliminar líneas de pedido
        $deleted_lines = Db::getInstance()->delete('syncrosevi_order_lines', 'id_child_shop = ' . $id_shop);
        
        // Eliminar tracking de pedidos
        $deleted_tracking = Db::getInstance()->delete('syncrosevi_order_tracking', 'id_child_shop = ' . $id_shop);
        
        Db::getInstance()->execute('COMMIT');
        
        $this->confirmations[] = $this->l('Tienda restablecida correctamente. Eliminadas: ') . $deleted_lines . $this->l(' líneas y ') . $deleted_tracking . $this->l(' registros de tracking.');
        
        // Redirigir de vuelta a la edición
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminSyncrosevi') . '&action=edit&id_child_shop=' . $id_shop);
        
    } catch (Exception $e) {
        Db::getInstance()->execute('ROLLBACK');
        $this->errors[] = $this->l('Error al restablecer la tienda: ') . $e->getMessage();
    }
}
    /**
     * Mostrar contenido principal - REFACTORIZADO
     */
    protected function displayMainContent()
    {
        // Cargar assets
        $this->loadAssets();
        
        // Obtener datos
        $stats = $this->getStats();
        $shops = $this->getConfiguredShops();
        $pendingOrders = $this->getPendingOrders();
        
        // URLs para webhook
        $webhook_token = $this->generateWebhookToken();
        $base_url = $this->getBaseUrl();
        
        // Asignar variables a Smarty
        $this->context->smarty->assign(array(
            'stats' => $stats,
            'shops' => $shops,
            'pending_orders' => $pendingOrders,
            'customers' => Customer::getCustomers(true),
            'groups' => Group::getGroups($this->context->language->id),
            'carriers' => Carrier::getCarriers($this->context->language->id, true),
            'order_states' => OrderState::getOrderStates($this->context->language->id),
            'addresses' => $this->getAddresses(),
            'admin_link' => $this->context->link->getAdminLink('AdminSyncrosevi'),
            'token' => Tools::getAdminTokenLite('AdminSyncrosevi'),
            'webhook_sync_url' => $base_url . 'modules/syncrosevi/webhook.php?action=sync&security_token=' . $webhook_token,
            'webhook_process_url' => $base_url . 'modules/syncrosevi/webhook.php?action=process&security_token=' . $webhook_token
        ));
        
        $this->setTemplate('main.tpl');
    }

    /**
     * Mostrar pedidos pendientes - REFACTORIZADO
     */
    protected function displayPendingOrders()
    {
        $this->loadAssets();
        
        $pendingOrders = $this->getAllPendingOrders();
        
        $this->context->smarty->assign(array(
            'pending_orders' => $pendingOrders,
            'admin_link' => $this->context->link->getAdminLink('AdminSyncrosevi'),
            'token' => Tools::getAdminTokenLite('AdminSyncrosevi')
        ));
        
        $this->setTemplate('pending-orders.tpl');
    }

    /**
     * Mostrar pedidos procesados - NUEVO
     */
    protected function displayProcessedOrders()
{
    $this->loadAssets();
    
    // Cargar algunos pedidos procesados como ejemplo
    $processedOrders = Db::getInstance()->executeS('
        SELECT 
            o.id_order as id_mother_order,
            o.reference,
            o.total_paid_tax_incl as total_paid,
            o.date_add,
            cs.name as shop_name,
            CONCAT(c.firstname, " ", c.lastname) as customer_name,
            c.email as customer_email
        FROM `' . _DB_PREFIX_ . 'syncrosevi_order_tracking` ot
        JOIN `' . _DB_PREFIX_ . 'syncrosevi_child_shops` cs ON ot.id_child_shop = cs.id_child_shop
        JOIN `' . _DB_PREFIX_ . 'orders` o ON ot.id_mother_order = o.id_order
        JOIN `' . _DB_PREFIX_ . 'customer` c ON o.id_customer = c.id_customer
        WHERE ot.processed = 1 AND ot.id_mother_order IS NOT NULL
        ORDER BY ot.date_processed DESC
        LIMIT 50
    ');
    
    $this->context->smarty->assign(array(
        'admin_link' => $this->context->link->getAdminLink('AdminSyncrosevi'),
        'token' => Tools::getAdminTokenLite('AdminSyncrosevi'),
        'processed_orders' => $processedOrders
    ));
    
    $this->setTemplate('processed-orders.tpl');
}
/**
 * Mostrar formulario de edición de tienda
 */
protected function displayEditShop()
{
    $id_shop = (int)Tools::getValue('id_child_shop');
    
    if (!$id_shop) {
        $this->errors[] = $this->l('ID de tienda inválido');
        $this->displayMainContent();
        return;
    }
    
    $shop = Db::getInstance()->getRow(
        'SELECT cs.*, c.firstname, c.lastname, c.email 
         FROM `' . _DB_PREFIX_ . 'syncrosevi_child_shops` cs
         LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON cs.id_customer = c.id_customer
         WHERE cs.id_child_shop = ' . $id_shop
    );
    
    if (!$shop) {
        $this->errors[] = $this->l('Tienda no encontrada');
        $this->displayMainContent();
        return;
    }
    
    $this->loadAssets();
    
// Obtener estadísticas de la tienda
$shopStats = array(
    'pending_orders' => (int)Db::getInstance()->getValue(
        'SELECT COUNT(DISTINCT id_original_order) FROM `' . _DB_PREFIX_ . 'syncrosevi_order_lines` 
         WHERE id_child_shop = ' . $id_shop . ' AND processed = 0'
    ),
    'processed_orders' => (int)Db::getInstance()->getValue(
        'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'syncrosevi_order_tracking` 
         WHERE id_child_shop = ' . $id_shop . ' AND processed = 1'
    ),
    'last_sync' => Db::getInstance()->getValue(
        'SELECT MAX(date_sync) FROM `' . _DB_PREFIX_ . 'syncrosevi_order_tracking` 
         WHERE id_child_shop = ' . $id_shop
    ) ?: 'Nunca'
);
// Cargar estados reales de la tienda hija
$childStates = array();
try {
    require_once(dirname(__FILE__).'/../../classes/SyncroSeviWebservice.php');
    $webservice = new SyncroSeviWebservice($shop['url'], $shop['api_key'], false);
    $xml = $webservice->get(array('resource' => 'order_states', 'display' => 'full'));
    
    if ($xml && isset($xml->order_states)) {
        $orderStates = $xml->order_states->order_state;
        if (!is_array($orderStates) && !($orderStates instanceof Traversable)) {
            $orderStates = array($orderStates);
        }
        
        foreach ($orderStates as $state) {
            $stateName = '';
            if (isset($state->name)) {
                if (is_array($state->name) || $state->name instanceof Traversable) {
                    $stateName = (string)$state->name[0];
                } else {
                    $stateName = (string)$state->name;
                }
            }
            
            $childStates[] = array(
                'id' => (int)$state->id,
                'name' => $stateName ?: 'Estado #' . (int)$state->id
            );
        }
    }
} catch (Exception $e) {
    // Si falla la conexión, usar estados locales
}
$this->context->smarty->assign(array(
    'shop' => $shop,
    'shop_stats' => $shopStats,
    'child_states' => $childStates,
    'customer' => array(
        'id_customer' => $shop['id_customer'],
        'firstname' => $shop['firstname'],
        'lastname' => $shop['lastname'],
        'email' => $shop['email']
    ),
    'customers' => Customer::getCustomers(true),
    'groups' => Group::getGroups($this->context->language->id),
    'carriers' => Carrier::getCarriers($this->context->language->id, true),
    'order_states' => OrderState::getOrderStates($this->context->language->id),
    'addresses' => $this->getAddresses(),
    'addresses_all' => $this->getAddresses(),
    'admin_link' => $this->context->link->getAdminLink('AdminSyncrosevi'),
    'token' => Tools::getAdminTokenLite('AdminSyncrosevi')
));
    
    $this->setTemplate('edit-shop.tpl');
}
    /**
     * Cargar assets CSS y JS - NUEVO
     */
    protected function loadAssets()
    {
        // CSS
        $this->addCSS(_MODULE_DIR_ . 'syncrosevi/views/css/admin.css');
        $this->addCSS(_MODULE_DIR_ . 'syncrosevi/views/css/modal.css');
        
        // JavaScript
        $this->addJS(_MODULE_DIR_ . 'syncrosevi/views/js/admin.js');
        $this->addJS(_MODULE_DIR_ . 'syncrosevi/views/js/processed-orders.js');
    }

    /**
     * Generar token para webhook
     */
    protected function generateWebhookToken()
    {
        return md5('syncrosevi_' . Configuration::get('PS_SHOP_NAME') . '_' . Configuration::get('PS_SHOP_EMAIL') . '_' . date('Y-m'));
    }

    /**
     * Obtener URL base
     */
    protected function getBaseUrl()
    {
        return (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__;
    }

    // AJAX HANDLERS

    /**
     * AJAX: Obtener pedidos procesados
     */
    protected function getProcessedOrdersAjax()
    {
        try {
            $orders = Db::getInstance()->executeS('
                SELECT 
                    o.id_order as id_mother_order,
                    o.reference,
                    o.total_paid_tax_incl as total_paid,
                    o.date_add,
                    cs.name as shop_name,
                    CONCAT(c.firstname, " ", c.lastname) as customer_name,
                    c.email as customer_email,
                    COUNT(DISTINCT ot.id_original_order) as original_orders,
                    SUM(od.product_quantity) as total_products,
                    ot.date_processed
                FROM `' . _DB_PREFIX_ . 'syncrosevi_order_tracking` ot
                JOIN `' . _DB_PREFIX_ . 'syncrosevi_child_shops` cs ON ot.id_child_shop = cs.id_child_shop
                JOIN `' . _DB_PREFIX_ . 'orders` o ON ot.id_mother_order = o.id_order
                JOIN `' . _DB_PREFIX_ . 'customer` c ON o.id_customer = c.id_customer
                LEFT JOIN `' . _DB_PREFIX_ . 'order_detail` od ON o.id_order = od.id_order
                WHERE ot.processed = 1 AND ot.id_mother_order IS NOT NULL
                GROUP BY o.id_order
                ORDER BY ot.date_processed DESC
                LIMIT 100
            ');

            // Añadir URL del pedido y formatear datos
            foreach ($orders as &$order) {
                $order['order_url'] = $this->context->link->getAdminLink('AdminOrders') . '&vieworder&id_order=' . $order['id_mother_order'];
                $order['total_paid'] = number_format($order['total_paid'], 2, ',', '.');
            }

            $this->ajaxResponse(true, 'success', array('orders' => $orders));
            
        } catch (Exception $e) {
            $this->ajaxResponse(false, $e->getMessage());
        }
    }

    /**
     * AJAX: Obtener detalles de pedido
     */
    protected function getOrderDetailsAjax()
    {
        try {
            $orderId = (int)Tools::getValue('id_order');
            
            if (!$orderId) {
                throw new Exception('ID de pedido inválido');
            }

            // Información del pedido
            $order = Db::getInstance()->getRow('
                SELECT 
                    o.*,
                    os.name as state_name,
                    CONCAT(c.firstname, " ", c.lastname) as customer_name,
                    c.email as customer_email,
                    CONCAT(a.address1, ", ", a.city, " ", a.postcode) as delivery_address,
                    ca.name as carrier_name
                FROM `' . _DB_PREFIX_ . 'orders` o
                JOIN `' . _DB_PREFIX_ . 'order_state_lang` os ON o.current_state = os.id_order_state AND os.id_lang = ' . (int)$this->context->language->id . '
                JOIN `' . _DB_PREFIX_ . 'customer` c ON o.id_customer = c.id_customer
                JOIN `' . _DB_PREFIX_ . 'address` a ON o.id_address_delivery = a.id_address
                LEFT JOIN `' . _DB_PREFIX_ . 'carrier` ca ON o.id_carrier = ca.id_carrier
                WHERE o.id_order = ' . $orderId
            );

            if (!$order) {
                throw new Exception('Pedido no encontrado');
            }

            // Productos del pedido
            $products = Db::getInstance()->executeS('
                SELECT 
                    product_name,
                    product_reference,
                    product_quantity,
                    unit_price_tax_incl as unit_price,
                    total_price_tax_incl as total_price
                FROM `' . _DB_PREFIX_ . 'order_detail`
                WHERE id_order = ' . $orderId
            );

            // Información de sincronización
            $syncInfo = Db::getInstance()->getRow('
                SELECT 
                    cs.name as shop_name,
                    COUNT(DISTINCT ot.id_original_order) as original_orders,
                    MIN(ot.date_sync) as date_sync,
                    MAX(ot.date_processed) as date_processed
                FROM `' . _DB_PREFIX_ . 'syncrosevi_order_tracking` ot
                JOIN `' . _DB_PREFIX_ . 'syncrosevi_child_shops` cs ON ot.id_child_shop = cs.id_child_shop
                WHERE ot.id_mother_order = ' . $orderId . '
                GROUP BY cs.id_child_shop
            ');

            $order['products'] = $products;
            $order['sync_info'] = $syncInfo;
            $order['total_paid'] = number_format($order['total_paid_tax_incl'], 2, ',', '.');

            $this->ajaxResponse(true, 'success', array('order' => $order));
            
        } catch (Exception $e) {
            $this->ajaxResponse(false, $e->getMessage());
        }
    }

    /**
     * AJAX: Obtener estados de tienda hija
     */
    protected function getChildShopStatesAjax()
    {
        $url = Tools::getValue('shop_url');
        $api_key = Tools::getValue('api_key');
        
        if (empty($url) || empty($api_key)) {
            $this->ajaxResponse(false, 'URL y API Key requeridos');
            return;
        }

        try {
            require_once(dirname(__FILE__).'/../../classes/SyncroSeviWebservice.php');
            
            $webservice = new SyncroSeviWebservice($url, $api_key, true);
            $xml = $webservice->get(array('resource' => 'order_states', 'display' => 'full'));
            
            $states = array();
            if ($xml && isset($xml->order_states)) {
                $orderStates = $xml->order_states->order_state;
                if (!is_array($orderStates) && !($orderStates instanceof Traversable)) {
                    $orderStates = array($orderStates);
                }
                
                foreach ($orderStates as $state) {
                    $stateName = '';
                    if (isset($state->name)) {
                        if (is_array($state->name) || $state->name instanceof Traversable) {
                            $stateName = (string)$state->name[0];
                        } else {
                            $stateName = (string)$state->name;
                        }
                    }
                    
                    $states[] = array(
    'id' => (int)$state->id,
    'name' => 'ID ' . (int)$state->id . ': ' . ($stateName ?: 'Estado sin nombre'),
    'display_name' => 'ID ' . (int)$state->id . ' - ' . ($stateName ?: 'Estado sin nombre')
);
                }
            }
            
            $this->ajaxResponse(true, 'success', array('states' => $states));
            
        } catch (Exception $e) {
            $this->ajaxResponse(false, $e->getMessage());
        }
    }

    /**
     * Exportar pedidos procesados
     */
    protected function exportProcessedOrders()
    {
        try {
            // Obtener filtros
            $shop = Tools::getValue('shop', '');
            $dateFrom = Tools::getValue('dateFrom', '');
            $dateTo = Tools::getValue('dateTo', '');
            $search = Tools::getValue('search', '');

            // Construir consulta con filtros
            $sql = 'SELECT 
                        o.id_order as id_mother_order,
                        o.reference,
                        o.total_paid_tax_incl as total_paid,
                        o.date_add,
                        cs.name as shop_name,
                        CONCAT(c.firstname, " ", c.lastname) as customer_name,
                        c.email as customer_email,
                        COUNT(DISTINCT ot.id_original_order) as original_orders,
                        SUM(od.product_quantity) as total_products,
                        ot.date_processed
                    FROM `' . _DB_PREFIX_ . 'syncrosevi_order_tracking` ot
                    JOIN `' . _DB_PREFIX_ . 'syncrosevi_child_shops` cs ON ot.id_child_shop = cs.id_child_shop
                    JOIN `' . _DB_PREFIX_ . 'orders` o ON ot.id_mother_order = o.id_order
                    JOIN `' . _DB_PREFIX_ . 'customer` c ON o.id_customer = c.id_customer
                    LEFT JOIN `' . _DB_PREFIX_ . 'order_detail` od ON o.id_order = od.id_order
                    WHERE ot.processed = 1 AND ot.id_mother_order IS NOT NULL';

            // Aplicar filtros
            if ($shop) {
                $sql .= ' AND cs.name LIKE "%' . pSQL($shop) . '%"';
            }
            if ($dateFrom) {
                $sql .= ' AND DATE(ot.date_processed) >= "' . pSQL($dateFrom) . '"';
            }
            if ($dateTo) {
                $sql .= ' AND DATE(ot.date_processed) <= "' . pSQL($dateTo) . '"';
            }
            if ($search) {
                $sql .= ' AND (o.reference LIKE "%' . pSQL($search) . '%" OR c.firstname LIKE "%' . pSQL($search) . '%" OR c.lastname LIKE "%' . pSQL($search) . '%" OR c.email LIKE "%' . pSQL($search) . '%")';
            }

            $sql .= ' GROUP BY o.id_order ORDER BY ot.date_processed DESC';

            $orders = Db::getInstance()->executeS($sql);

            // Generar CSV
            $filename = 'pedidos_procesados_' . date('Y-m-d_H-i-s') . '.csv';
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');

            $output = fopen('php://output', 'w');
            
            // Cabeceras CSV
            fputcsv($output, array(
                'ID Pedido Madre',
                'Referencia',
                'Tienda Origen',
                'Cliente',
                'Email',
                'Pedidos Originales',
                'Total Productos',
                'Total Pagado',
                'Fecha Procesado'
            ), ';');

            // Datos
            foreach ($orders as $order) {
                fputcsv($output, array(
                    $order['id_mother_order'],
                    $order['reference'],
                    $order['shop_name'],
                    $order['customer_name'],
                    $order['customer_email'],
                    $order['original_orders'],
                    $order['total_products'],
                    number_format($order['total_paid'], 2, ',', '.') . '€',
                    date('d/m/Y H:i', strtotime($order['date_processed']))
                ), ';');
            }

            fclose($output);
            exit;
            
        } catch (Exception $e) {
            $this->errors[] = 'Error en exportación: ' . $e->getMessage();
            $this->displayMainContent();
        }
    }

    // PROCESAMIENTO DE FORMULARIOS

    /**
     * Procesar adición de tienda
     */
    protected function processAddShop()
    {
        if (Tools::isSubmit('submitAddShop')) {
            $name = Tools::getValue('shop_name');
            $url = Tools::getValue('shop_url');
            $api_key = Tools::getValue('api_key');
            $id_customer = (int)Tools::getValue('id_customer');
            $id_group = (int)Tools::getValue('id_group');
            $id_address = (int)Tools::getValue('id_address');
            $id_carrier = Tools::getValue('id_carrier') ? (int)Tools::getValue('id_carrier') : null;
            $id_order_state = (int)Tools::getValue('id_order_state');
            $start_order_id = (int)Tools::getValue('start_order_id') ?: 1;
            $import_states = Tools::getValue('import_states');
            $active = Tools::getValue('active') ? 1 : 0;

            if (empty($name) || empty($url) || empty($api_key) || !$id_customer || !$id_group || !$id_address || !$id_order_state || empty($import_states)) {
                $this->errors[] = $this->l('Todos los campos obligatorios deben ser completados');
                return;
            }

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $this->errors[] = $this->l('La URL no es válida');
                return;
            }

            $import_states_str = is_array($import_states) ? implode(',', array_map('intval', $import_states)) : $import_states;

// Generar clave secreta para webhook
$webhook_secret = bin2hex(random_bytes(32));
$realtime_enabled = Tools::getValue('realtime_enabled') ? 1 : 0;

$inserted = Db::getInstance()->insert('syncrosevi_child_shops', array(
    'name' => pSQL($name),
    'url' => pSQL(rtrim($url, '/')),
    'api_key' => pSQL($api_key),
    'id_customer' => $id_customer,
    'id_group' => $id_group,
    'id_address' => $id_address,
    'id_carrier' => $id_carrier,
    'id_order_state' => $id_order_state,
    'start_order_id' => $start_order_id,
    'import_states' => pSQL($import_states_str),
    'active' => $active,
    'realtime_enabled' => $realtime_enabled,
    'webhook_secret' => pSQL($webhook_secret),
    'date_add' => date('Y-m-d H:i:s'),
    'date_upd' => date('Y-m-d H:i:s')
));

            if ($inserted) {
    $this->confirmations[] = $this->l('Tienda hija añadida correctamente');
    // Redirigir para evitar re-envío y actualizar la vista
    Tools::redirectAdmin($this->context->link->getAdminLink('AdminSyncrosevi'));
} else {
    $this->errors[] = $this->l('Error al añadir la tienda hija');
}
        }
    }

    /**
     * Procesar edición de tienda
     */
protected function processEditShop()
{
    if (Tools::isSubmit('submitEditShop')) {
        $id_shop = (int)Tools::getValue('id_child_shop');
        $name = Tools::getValue('shop_name');
        $url = Tools::getValue('shop_url');
        $api_key = Tools::getValue('api_key');
        $id_customer = (int)Tools::getValue('id_customer');
        $id_group = (int)Tools::getValue('id_group');
        $id_address = (int)Tools::getValue('id_address');
        $id_carrier = Tools::getValue('id_carrier') ? (int)Tools::getValue('id_carrier') : null;
        $id_order_state = (int)Tools::getValue('id_order_state');
        $start_order_id = (int)Tools::getValue('start_order_id') ?: 1;
        $import_states = Tools::getValue('import_states');
        $active = Tools::getValue('active') ? 1 : 0;

        if (!$id_shop || empty($name) || empty($url) || empty($api_key) || !$id_customer || !$id_group || !$id_address || !$id_order_state || empty($import_states)) {
            $this->errors[] = $this->l('Todos los campos obligatorios deben ser completados');
            return;
        }

        $import_states_str = is_array($import_states) ? implode(',', array_map('intval', $import_states)) : $import_states;

        $realtime_enabled = Tools::getValue('realtime_enabled') ? 1 : 0;

$updated = Db::getInstance()->update('syncrosevi_child_shops', array(
    'name' => pSQL($name),
    'url' => pSQL(rtrim($url, '/')),
    'api_key' => pSQL($api_key),
    'id_customer' => $id_customer,
    'id_group' => $id_group,
    'id_address' => $id_address,
    'id_carrier' => $id_carrier,
    'id_order_state' => $id_order_state,
    'start_order_id' => $start_order_id,
    'import_states' => pSQL($import_states_str),
    'active' => $active,
    'realtime_enabled' => $realtime_enabled,
    'date_upd' => date('Y-m-d H:i:s')
), 'id_child_shop = ' . $id_shop);

        if ($updated) {
            $this->confirmations[] = $this->l('Tienda hija actualizada correctamente');
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminSyncrosevi'));
        } else {
            $this->errors[] = $this->l('Error al actualizar la tienda hija');
        }
    }
}

    /**
     * Procesar eliminación de tienda
     */
    protected function processDeleteShop()
    {
        $id_shop = (int)Tools::getValue('id_child_shop');
        
        if (!$id_shop) {
            $this->errors[] = $this->l('ID de tienda inválido');
            return;
        }

        // Verificar pedidos pendientes
        $pendingCount = (int)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'syncrosevi_order_lines` 
             WHERE id_child_shop = ' . $id_shop . ' AND processed = 0'
        );
        
        if ($pendingCount > 0) {
            $this->errors[] = $this->l('No se puede eliminar la tienda porque tiene ') . $pendingCount . $this->l(' pedidos pendientes');
            return;
        }

        // Eliminar datos relacionados
        Db::getInstance()->delete('syncrosevi_order_tracking', 'id_child_shop = ' . $id_shop);
        Db::getInstance()->delete('syncrosevi_order_lines', 'id_child_shop = ' . $id_shop);
        
        $deleted = Db::getInstance()->delete('syncrosevi_child_shops', 'id_child_shop = ' . $id_shop);
        
        if ($deleted) {
            $this->confirmations[] = $this->l('Tienda hija eliminada correctamente');
        } else {
            $this->errors[] = $this->l('Error al eliminar la tienda hija');
        }
    }

    /**
     * Procesar test de conexión
     */
    protected function processTestConnection()
    {
        $id_shop = (int)Tools::getValue('id_child_shop');
        
        if (!$id_shop) {
            $this->ajaxResponse(false, 'ID de tienda inválido');
            return;
        }

        $shop = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'syncrosevi_child_shops` WHERE id_child_shop = ' . $id_shop
        );

        if (!$shop) {
            $this->ajaxResponse(false, 'Tienda no encontrada');
            return;
        }

        try {
            require_once(dirname(__FILE__).'/../../classes/SyncroSeviWebservice.php');
            $webservice = new SyncroSeviWebservice($shop['url'], $shop['api_key']);
            $connection = $webservice->testConnection();
            $shopInfo = array();
            
            if ($connection) {
                $shopInfo = $webservice->getShopInfo();
            }
            
            $this->ajaxResponse($connection, $connection ? 'Conexión exitosa' : 'Error de conexión', array('shop_info' => $shopInfo));
            
        } catch (Exception $e) {
            $this->ajaxResponse(false, $e->getMessage());
        }
    }

    /**
     * Procesar sincronización
     */
    protected function processSyncOrders()
    {
        try {
            $module = Module::getInstanceByName('syncrosevi');
            $results = $module->syncOrders();
            
            $this->confirmations[] = $this->l('Sincronización completada');
            
            foreach ($results as $result) {
                if ($result['status'] == 'success') {
                    $this->confirmations[] = $result['shop'] . ': ' . $result['count'] . ' pedidos sincronizados';
                } else {
                    $this->errors[] = $result['shop'] . ': Error - ' . $result['message'];
                }
            }
        } catch (Exception $e) {
            $this->errors[] = $this->l('Error en la sincronización: ') . $e->getMessage();
        }
    }

    /**
     * Procesar pedidos
     */
    protected function processOrders()
    {
        try {
            $module = Module::getInstanceByName('syncrosevi');
            $results = $module->processOrders();
            
            $this->confirmations[] = $this->l('Procesamiento completado');
            
            foreach ($results as $result) {
                if ($result['status'] == 'success') {
                    if (isset($result['order_id'])) {
                        $this->confirmations[] = $result['shop'] . ': Pedido #' . $result['order_id'] . ' creado con ' . $result['products_count'] . ' productos';
                    } else {
                        $this->confirmations[] = $result['shop'] . ': ' . ($result['message'] ?: 'Procesado correctamente');
                    }
                } else {
                    $this->errors[] = $result['shop'] . ': Error - ' . $result['message'];
                }
            }
        } catch (Exception $e) {
            $this->errors[] = $this->l('Error en el procesamiento: ') . $e->getMessage();
        }
    }

    // MÉTODOS DE DATOS

    /**
     * Obtener estadísticas
     */
    protected function getStats()
    {
        $totalShops = (int)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'syncrosevi_child_shops`'
        );
        
        $activeShops = (int)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'syncrosevi_child_shops` WHERE active = 1'
        );
        
        $pendingOrders = (int)Db::getInstance()->getValue(
            'SELECT COUNT(DISTINCT id_child_shop, id_original_order) FROM `' . _DB_PREFIX_ . 'syncrosevi_order_lines` WHERE processed = 0'
        );
        
        $processedOrders = (int)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'syncrosevi_order_tracking` WHERE processed = 1'
        );

        return array(
            'total_shops' => $totalShops,
            'active_shops' => $activeShops,
            'pending_orders' => $pendingOrders,
            'processed_orders' => $processedOrders
        );
    }

    /**
     * Obtener tiendas configuradas
     */
    protected function getConfiguredShops()
{
    return Db::getInstance()->executeS(
        'SELECT cs.*, c.firstname, c.lastname, c.email, 
                a.alias as address_alias, a.address1, a.city,
                g.name as group_name, os.name as state_name, ca.name as carrier_name,
                (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'syncrosevi_order_tracking` ot 
                 WHERE ot.id_child_shop = cs.id_child_shop AND ot.realtime_sync = 1 
                 AND ot.date_realtime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as realtime_orders_24h
         FROM `' . _DB_PREFIX_ . 'syncrosevi_child_shops` cs
         LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON cs.id_customer = c.id_customer
         LEFT JOIN `' . _DB_PREFIX_ . 'address` a ON cs.id_address = a.id_address
         LEFT JOIN `' . _DB_PREFIX_ . 'group_lang` g ON cs.id_group = g.id_group AND g.id_lang = ' . (int)$this->context->language->id . '
         LEFT JOIN `' . _DB_PREFIX_ . 'order_state_lang` os ON cs.id_order_state = os.id_order_state AND os.id_lang = ' . (int)$this->context->language->id . '
         LEFT JOIN `' . _DB_PREFIX_ . 'carrier` ca ON cs.id_carrier = ca.id_carrier
         ORDER BY cs.name ASC'
    );
}

    /**
     * Obtener direcciones
     */
    protected function getAddresses()
    {
        return Db::getInstance()->executeS(
            'SELECT a.id_address, a.id_customer, a.alias, a.firstname, a.lastname, a.address1, a.city, a.postcode
             FROM `' . _DB_PREFIX_ . 'address` a
             WHERE a.deleted = 0
             ORDER BY a.id_customer, a.alias ASC'
        );
    }

    /**
     * Obtener pedidos pendientes
     */
    protected function getPendingOrders()
    {
        return Db::getInstance()->executeS(
            'SELECT cs.name as shop_name, cs.id_child_shop,
                   ol.id_original_order, 
                   GROUP_CONCAT(CONCAT(ol.product_name, " (", ol.quantity, ")") SEPARATOR ", ") as products,
                   SUM(ol.quantity) as total_quantity,
                   COUNT(ol.id_line) as lines_count,
                   MIN(ol.date_add) as first_sync
            FROM `' . _DB_PREFIX_ . 'syncrosevi_order_lines` ol
            JOIN `' . _DB_PREFIX_ . 'syncrosevi_child_shops` cs ON ol.id_child_shop = cs.id_child_shop
            WHERE ol.processed = 0
            GROUP BY cs.id_child_shop, ol.id_original_order
            ORDER BY ol.date_add DESC
            LIMIT 10'
        );
    }

    /**
     * Obtener TODOS los pedidos pendientes
     */
    protected function getAllPendingOrders()
    {
        return Db::getInstance()->executeS(
            'SELECT cs.name as shop_name, cs.id_child_shop,
                   ol.id_original_order, 
                   GROUP_CONCAT(CONCAT(ol.product_name, " (", ol.quantity, ")") SEPARATOR ", ") as products,
                   SUM(ol.quantity) as total_quantity,
                   COUNT(ol.id_line) as lines_count,
                   MIN(ol.date_add) as first_sync
            FROM `' . _DB_PREFIX_ . 'syncrosevi_order_lines` ol
            JOIN `' . _DB_PREFIX_ . 'syncrosevi_child_shops` cs ON ol.id_child_shop = cs.id_child_shop
            WHERE ol.processed = 0
            GROUP BY cs.id_child_shop, ol.id_original_order
            ORDER BY cs.name ASC, ol.date_add DESC'
        );
    }

    /**
     * Respuesta AJAX estandarizada
     */
    protected function ajaxResponse($success, $message, $data = array())
    {
        header('Content-Type: application/json');
        die(json_encode(array_merge(array(
            'success' => $success,
            'message' => $message
        ), $data)));
    }

    /**
     * Cargar medios (CSS/JS) - HEREDADO
     */
    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        
        // Los assets se cargan ahora en loadAssets() para mayor control
    }
	/**
 * AJAX: Obtener productos de un pedido pendiente específico
 */
protected function getOrderProductsAjax()
{
    try {
        $shopId = (int)Tools::getValue('shop_id');
        $orderId = (int)Tools::getValue('order_id');
        
        if (!$shopId || !$orderId) {
            throw new Exception('Parámetros inválidos');
        }
        
        // Obtener información de la tienda
        $shop = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'syncrosevi_child_shops` WHERE id_child_shop = ' . $shopId
        );
        
        if (!$shop) {
            throw new Exception('Tienda no encontrada');
        }
        
        // Obtener productos del pedido
        $products = Db::getInstance()->executeS('
            SELECT 
                product_reference,
                product_name,
                quantity,
                date_add
            FROM `' . _DB_PREFIX_ . 'syncrosevi_order_lines`
            WHERE id_child_shop = ' . $shopId . ' 
            AND id_original_order = ' . $orderId . '
            AND processed = 0
            ORDER BY product_name ASC
        ');
        
        if (empty($products)) {
            throw new Exception('No se encontraron productos para este pedido');
        }
        
        $this->ajaxResponse(true, 'success', array(
            'shop' => $shop,
            'products' => $products,
            'total_products' => count($products),
            'total_quantity' => array_sum(array_column($products, 'quantity'))
        ));
        
    } catch (Exception $e) {
        $this->ajaxResponse(false, $e->getMessage());
    }
}
/**
 * AJAX: Eliminar un pedido pendiente específico
 */
protected function deletePendingOrderAjax()
{
    try {
        $shopId = (int)Tools::getValue('shop_id');
        $orderId = (int)Tools::getValue('order_id');
        
        if (!$shopId || !$orderId) {
            throw new Exception('Parámetros inválidos');
        }
        
        // Verificar que el pedido existe y está pendiente
        $pendingLines = Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'syncrosevi_order_lines`
            WHERE id_child_shop = ' . $shopId . ' 
            AND id_original_order = ' . $orderId . '
            AND processed = 0
        ');
        
        if (!$pendingLines) {
            throw new Exception('No se encontró el pedido pendiente');
        }
        
        Db::getInstance()->execute('START TRANSACTION');
        
        // Eliminar líneas del pedido
        $deletedLines = Db::getInstance()->delete(
            'syncrosevi_order_lines',
            'id_child_shop = ' . $shopId . ' AND id_original_order = ' . $orderId . ' AND processed = 0'
        );
        
        // Eliminar tracking del pedido
        $deletedTracking = Db::getInstance()->delete(
            'syncrosevi_order_tracking',
            'id_child_shop = ' . $shopId . ' AND id_original_order = ' . $orderId . ' AND processed = 0'
        );
        
        Db::getInstance()->execute('COMMIT');
        
        $this->ajaxResponse(true, 'Pedido eliminado correctamente', array(
            'deleted_lines' => $deletedLines,
            'deleted_tracking' => $deletedTracking
        ));
        
    } catch (Exception $e) {
        Db::getInstance()->execute('ROLLBACK');
        $this->ajaxResponse(false, $e->getMessage());
    }
}
/**
 * AJAX: Procesar TODOS los pedidos pendientes de UNA tienda específica
 */
protected function processShopOrdersAjax()
{
    try {
        $shopId = (int)Tools::getValue('shop_id');
        
        if (!$shopId) {
            throw new Exception('ID de tienda inválido');
        }
        
        // Verificar que la tienda tiene pedidos pendientes
        $pendingCount = (int)Db::getInstance()->getValue('
            SELECT COUNT(DISTINCT id_original_order) 
            FROM `' . _DB_PREFIX_ . 'syncrosevi_order_lines` 
            WHERE id_child_shop = ' . $shopId . ' AND processed = 0
        ');
        
        if ($pendingCount === 0) {
            throw new Exception('Esta tienda no tiene pedidos pendientes');
        }
        
        // Usar el método principal pero filtrado para esta tienda
        $module = Module::getInstanceByName('syncrosevi');
        
        // Temporalmente desactivar otras tiendas
        Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'syncrosevi_child_shops` 
            SET active = 0 
            WHERE id_child_shop != ' . $shopId
        );
        
        // Procesar (solo procesará la tienda activa)
        $results = $module->processOrders();
        
        // Reactivar todas las tiendas
        Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'syncrosevi_child_shops` 
            SET active = 1
        ');
        
        // Buscar resultado de nuestra tienda
        $shopResult = null;
        foreach ($results as $result) {
            if ($result['status'] === 'success') {
                $shopResult = $result;
                break;
            }
        }
        
        if ($shopResult && isset($shopResult['order_id'])) {
            $this->ajaxResponse(true, 'Pedidos de la tienda procesados correctamente', array(
                'order_id' => $shopResult['order_id'],
                'products_count' => $shopResult['products_count'],
                'shop_name' => $shopResult['shop']
            ));
        } else {
            throw new Exception('No se pudo procesar los pedidos de la tienda');
        }
        
    } catch (Exception $e) {
        // Asegurar reactivar tiendas en caso de error
        Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'syncrosevi_child_shops` SET active = 1');
        $this->ajaxResponse(false, $e->getMessage());
    }
}
/**
 * Regenerar clave secreta de webhook
 */
protected function regenerateWebhookSecret()
{
    try {
        $id_shop = (int)Tools::getValue('id_child_shop');
        
        if (!$id_shop) {
            throw new Exception('ID de tienda inválido');
        }
        
        // Generar nueva clave secreta
        $new_secret = bin2hex(random_bytes(32));
        
        $updated = Db::getInstance()->update('syncrosevi_child_shops', array(
            'webhook_secret' => pSQL($new_secret),
            'date_upd' => date('Y-m-d H:i:s')
        ), 'id_child_shop = ' . $id_shop);
        
        if ($updated) {
            $this->ajaxResponse(true, 'Clave secreta regenerada correctamente', array(
                'new_secret' => $new_secret
            ));
        } else {
            throw new Exception('Error al actualizar la clave secreta');
        }
        
    } catch (Exception $e) {
        $this->ajaxResponse(false, $e->getMessage());
    }
}

/**
 * AJAX: Obtener información de webhook de una tienda
 */
protected function getWebhookInfoAjax()
{
    try {
        $id_shop = (int)Tools::getValue('id_child_shop');
        
        if (!$id_shop) {
            throw new Exception('ID de tienda inválido');
        }
        
        $shop = Db::getInstance()->getRow(
            'SELECT name, url, webhook_secret, realtime_enabled, last_webhook 
             FROM `' . _DB_PREFIX_ . 'syncrosevi_child_shops` 
             WHERE id_child_shop = ' . $id_shop
        );
        
        if (!$shop) {
            throw new Exception('Tienda no encontrada');
        }
        
        // Generar URLs de webhook
        $webhook_token = Configuration::get('SYNCROSEVI_WEBHOOK_TOKEN');
        $base_url = $this->getBaseUrl();
        $webhook_url = $base_url . 'modules/syncrosevi/webhook.php?action=realtime_sync&security_token=' . $webhook_token;
        
        // Estadísticas de webhooks
        $webhook_stats = array(
            'total_received' => (int)Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'syncrosevi_order_tracking` 
                 WHERE id_child_shop = ' . $id_shop . ' AND realtime_sync = 1'
            ),
            'last_24h' => (int)Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'syncrosevi_order_tracking` 
                 WHERE id_child_shop = ' . $id_shop . ' AND realtime_sync = 1 
                 AND date_realtime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            )
        );
        
        $this->ajaxResponse(true, 'success', array(
            'shop' => $shop,
            'webhook_url' => $webhook_url,
            'webhook_stats' => $webhook_stats,
            'module_url_for_child' => $base_url . 'modules/syncrosevi/child_module/'
        ));
        
    } catch (Exception $e) {
        $this->ajaxResponse(false, $e->getMessage());
    }
}
/**
 * Generar y descargar módulo preconfigurado para tienda hija
 */
protected function downloadChildModule()
{
    try {
        $id_shop = (int)Tools::getValue('id_child_shop');
        
        if (!$id_shop) {
            throw new Exception('ID de tienda inválido');
        }
        
        // Obtener datos de la tienda
        $shop = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'syncrosevi_child_shops` 
             WHERE id_child_shop = ' . $id_shop
        );
        
        if (!$shop) {
            throw new Exception('Tienda no encontrada');
        }
        
        // Generar ZIP con módulo preconfigurado
        $zipFile = $this->generateChildModuleZip($shop);
        
        if (!$zipFile || !file_exists($zipFile)) {
            throw new Exception('Error generando el módulo');
        }
        
        // Enviar archivo para descarga
        $filename = 'SyncroSeviSender_' . $shop['name'] . '_Preconfigurado.zip';
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($zipFile));
        header('Cache-Control: no-cache, must-revalidate');
        
        readfile($zipFile);
        
        // Limpiar archivo temporal
        @unlink($zipFile);
        exit;
        
    } catch (Exception $e) {
        $this->errors[] = 'Error generando módulo: ' . $e->getMessage();
        $this->displayMainContent();
    }
}

/**
 * Generar ZIP del módulo hijo preconfigurado
 */
private function generateChildModuleZip($shop)
{
    // Crear directorio temporal
    $tempDir = sys_get_temp_dir() . '/syncrosevi_' . uniqid();
    $moduleDir = $tempDir . '/syncrosevisender';
    
    if (!mkdir($moduleDir, 0755, true)) {
        throw new Exception('No se puede crear directorio temporal');
    }
    
    try {
        // URLs para webhook
        $webhook_token = Configuration::get('SYNCROSEVI_WEBHOOK_TOKEN');
        $base_url = $this->getBaseUrl();
        $webhook_url = $base_url . 'modules/syncrosevi/webhook.php?action=realtime_sync&security_token=' . $webhook_token;
        
        // Generar contenido del módulo con configuración
        $moduleContent = $this->generateModuleContent($shop, $webhook_url);
        $configContent = $this->generateConfigXml();
        $indexContent = $this->generateIndexPhp();
        $readmeContent = $this->generateReadmeContent($shop, $webhook_url);
        $autoConfigContent = $this->generateAutoConfigContent($shop, $webhook_url);
        
        // Escribir archivos
        file_put_contents($moduleDir . '/syncrosevisender.php', $moduleContent);
        file_put_contents($moduleDir . '/config.xml', $configContent);
        file_put_contents($moduleDir . '/index.php', $indexContent);
        file_put_contents($moduleDir . '/README.txt', $readmeContent);
        file_put_contents($moduleDir . '/CONFIGURACION_AUTOMATICA.php', $autoConfigContent);
        
        // Crear ZIP
        $zipFile = $tempDir . '/syncrosevisender.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('No se puede crear archivo ZIP');
        }
        
        $this->addDirectoryToZip($zip, $moduleDir, 'syncrosevisender');
        $zip->close();
        
        // Limpiar directorio temporal
        $this->removeDirectory($moduleDir);
        
        return $zipFile;
        
    } catch (Exception $e) {
        // Limpiar en caso de error
        if (is_dir($tempDir)) {
            $this->removeDirectory($tempDir);
        }
        throw $e;
    }
}

/**
 * Generar contenido del módulo PHP preconfigurado
 */
private function generateModuleContent($shop, $webhook_url)
{
    $moduleContent = '<?php
/**
 * SyncroSevi Sender - Módulo Preconfigurado
 * 
 * Tienda: ' . $shop['name'] . '
 * Generado: ' . date('Y-m-d H:i:s') . '
 */

if (!defined(\'_PS_VERSION_\')) {
    exit;
}

class SyncroSeviSender extends Module
{
    private $debug = true;
    
    public function __construct()
    {
        $this->name = \'syncrosevisender\';
        $this->tab = \'administration\';
        $this->version = \'1.0.0\';
        $this->author = \'SyncroSevi Team\';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array(
            \'min\' => \'1.7.0.0\',
            \'max\' => \'8.99.99\'
        );
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l(\'SyncroSevi Sender - Webhooks Tiempo Real\');
        $this->description = $this->l(\'Envía webhooks automáticos a la tienda madre cuando cambia el estado de pedidos\');
        $this->confirmUninstall = $this->l(\'¿Estás seguro de que quieres desinstalar este módulo?\');
    }

    public function install()
    {
        if (!parent::install() ||
            !$this->registerHook(\'actionOrderHistoryAddAfter\') ||
            !$this->createConfiguration()) {
            return false;
        }
        
        // AUTO-CONFIGURACIÓN AL INSTALAR
        $this->autoConfigureModule();
        
        return true;
    }

    public function uninstall()
    {
        return parent::uninstall() && $this->deleteConfiguration();
    }

    private function createConfiguration()
    {
        Configuration::updateValue(\'SYNCROSEVISENDER_ACTIVE\', 0);
        Configuration::updateValue(\'SYNCROSEVISENDER_MOTHER_URL\', \'\');
        Configuration::updateValue(\'SYNCROSEVISENDER_SHOP_ID\', \'\');
        Configuration::updateValue(\'SYNCROSEVISENDER_SECRET_KEY\', \'\');
        Configuration::updateValue(\'SYNCROSEVISENDER_SYNC_STATES\', \'2,3,4,5\');
        Configuration::updateValue(\'SYNCROSEVISENDER_TIMEOUT\', 10);
        Configuration::updateValue(\'SYNCROSEVISENDER_MAX_RETRIES\', 3);
        
        return true;
    }

    private function deleteConfiguration()
    {
        Configuration::deleteByName(\'SYNCROSEVISENDER_ACTIVE\');
        Configuration::deleteByName(\'SYNCROSEVISENDER_MOTHER_URL\');
        Configuration::deleteByName(\'SYNCROSEVISENDER_SHOP_ID\');
        Configuration::deleteByName(\'SYNCROSEVISENDER_SECRET_KEY\');
        Configuration::deleteByName(\'SYNCROSEVISENDER_SYNC_STATES\');
        Configuration::deleteByName(\'SYNCROSEVISENDER_TIMEOUT\');
        Configuration::deleteByName(\'SYNCROSEVISENDER_MAX_RETRIES\');
        
        return true;
    }

    /**
     * Auto-configuración con datos preestablecidos
     */
    private function autoConfigureModule()
    {
        Configuration::updateValue(\'SYNCROSEVISENDER_ACTIVE\', 1);
        Configuration::updateValue(\'SYNCROSEVISENDER_MOTHER_URL\', \'' . addslashes($webhook_url) . '\');
        Configuration::updateValue(\'SYNCROSEVISENDER_SHOP_ID\', ' . (int)$shop['id_child_shop'] . ');
        Configuration::updateValue(\'SYNCROSEVISENDER_SECRET_KEY\', \'' . addslashes($shop['webhook_secret']) . '\');
        Configuration::updateValue(\'SYNCROSEVISENDER_SYNC_STATES\', \'' . addslashes($shop['import_states']) . '\');
        Configuration::updateValue(\'SYNCROSEVISENDER_TIMEOUT\', 10);
        Configuration::updateValue(\'SYNCROSEVISENDER_MAX_RETRIES\', 3);
        
        // Test inmediato de conexión
        $this->testConnectionOnInstall();
    }
    
    private function testConnectionOnInstall()
    {
        try {
            $motherUrl = Configuration::get(\'SYNCROSEVISENDER_MOTHER_URL\');
            $shopId = Configuration::get(\'SYNCROSEVISENDER_SHOP_ID\');
            $secretKey = Configuration::get(\'SYNCROSEVISENDER_SECRET_KEY\');
            
            if (empty($motherUrl) || empty($shopId) || empty($secretKey)) {
                $this->log(\'⚠ INSTALACIÓN: Configuración incompleta para test\');
                return;
            }
            
            $timestamp = time();
            $orderId = 0;
            $newState = 999;
            
            $payload = $shopId . $orderId . $newState . $timestamp;
            $signature = hash_hmac(\'sha256\', $payload, $secretKey);
            
            $parsedUrl = parse_url($motherUrl);
            $securityToken = \'\';
            if (isset($parsedUrl[\'query\'])) {
                parse_str($parsedUrl[\'query\'], $queryParams);
                $securityToken = isset($queryParams[\'security_token\']) ? $queryParams[\'security_token\'] : \'\';
            }
            
            $postData = array(
                \'action\' => \'realtime_sync\',
                \'shop_id\' => $shopId,
                \'order_id\' => $orderId,
                \'new_state\' => $newState,
                \'timestamp\' => $timestamp,
                \'signature\' => $signature,
                \'security_token\' => $securityToken,
                \'test_mode\' => 1
            );
            
            $result = $this->httpPost($motherUrl, $postData, 10);
            
            if ($result[\'success\']) {
                $this->log(\'✓ INSTALACIÓN: Test de conexión exitoso - Módulo listo para usar\');
            } else {
                $this->log(\'⚠ INSTALACIÓN: Test de conexión falló - Verificar configuración: \' . $result[\'error\']);
            }
            
        } catch (Exception $e) {
            $this->log(\'⚠ INSTALACIÓN: Error en test de conexión: \' . $e->getMessage());
        }
    }

    /**
     * Hook principal: Se ejecuta cuando cambia el estado de un pedido
     */
    public function hookActionOrderHistoryAddAfter($params)
    {
        if (!Configuration::get(\'SYNCROSEVISENDER_ACTIVE\')) {
            return;
        }

        $motherUrl = Configuration::get(\'SYNCROSEVISENDER_MOTHER_URL\');
        $shopId = Configuration::get(\'SYNCROSEVISENDER_SHOP_ID\');
        $secretKey = Configuration::get(\'SYNCROSEVISENDER_SECRET_KEY\');

        if (empty($motherUrl) || empty($shopId) || empty($secretKey)) {
            $this->log(\'ERROR: Configuración incompleta\');
            return;
        }

        try {
            if (!isset($params[\'order_history\']) || !($params[\'order_history\'] instanceof OrderHistory)) {
                $this->log(\'ERROR: Parámetro order_history no válido\');
                return;
            }
            
            $orderHistory = $params[\'order_history\'];
            $orderId = (int)$orderHistory->id_order;
            $newState = (int)$orderHistory->id_order_state;
            
            $syncStates = explode(\',\', Configuration::get(\'SYNCROSEVISENDER_SYNC_STATES\'));
            $syncStates = array_map(\'intval\', array_filter($syncStates));
            
            if (!in_array($newState, $syncStates)) {
                $this->log(\'DEBUG: Estado \' . $newState . \' no está en la lista de sincronización\');
                return;
            }
            
            $this->log(\'INFO: Pedido #\' . $orderId . \' cambió a estado \' . $newState . \' - Enviando webhook...\');
            
            $this->sendWebhook($orderId, $newState, $motherUrl, $shopId, $secretKey);
            
        } catch (Exception $e) {
            $this->log(\'ERROR CRÍTICO: \' . $e->getMessage());
        }
    }

    /**
     * Enviar webhook a la tienda madre
     */
    private function sendWebhook($orderId, $newState, $motherUrl, $shopId, $secretKey)
    {
        $timestamp = time();
        
        $payload = $shopId . $orderId . $newState . $timestamp;
        $signature = hash_hmac(\'sha256\', $payload, $secretKey);
        
        $parsedUrl = parse_url($motherUrl);
        $securityToken = \'\';
        if (isset($parsedUrl[\'query\'])) {
            parse_str($parsedUrl[\'query\'], $queryParams);
            $securityToken = isset($queryParams[\'security_token\']) ? $queryParams[\'security_token\'] : \'\';
        }
        
        $postData = array(
            \'action\' => \'realtime_sync\',
            \'shop_id\' => $shopId,
            \'order_id\' => $orderId,
            \'new_state\' => $newState,
            \'timestamp\' => $timestamp,
            \'signature\' => $signature,
            \'security_token\' => $securityToken
        );
        
        $maxRetries = (int)Configuration::get(\'SYNCROSEVISENDER_MAX_RETRIES\') ?: 3;
        $timeout = (int)Configuration::get(\'SYNCROSEVISENDER_TIMEOUT\') ?: 10;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $this->log(\'DEBUG: Intento \' . $attempt . \'/\' . $maxRetries . \' - Enviando a \' . $motherUrl);
                
                $result = $this->httpPost($motherUrl, $postData, $timeout);
                
                if ($result[\'success\']) {
                    $this->log(\'✓ SUCCESS: Webhook enviado correctamente para pedido #\' . $orderId);
                    return true;
                } else {
                    $this->log(\'✗ FAIL: Intento \' . $attempt . \' falló: \' . $result[\'error\']);
                    
                    if ($attempt < $maxRetries) {
                        sleep(pow(2, $attempt - 1));
                    }
                }
                
            } catch (Exception $e) {
                $this->log(\'✗ EXCEPTION: Intento \' . $attempt . \' - \' . $e->getMessage());
                
                if ($attempt < $maxRetries) {
                    sleep(pow(2, $attempt - 1));
                }
            }
        }
        
        $this->log(\'✗ TOTAL FAILURE: Webhook para pedido #\' . $orderId . \' falló después de \' . $maxRetries . \' intentos\');
        return false;
    }

    /**
     * Realizar petición HTTP POST
     */
    private function httpPost($url, $data, $timeout = 10)
    {
        if (!function_exists(\'curl_init\')) {
            throw new Exception(\'cURL no está disponible\');
        }
        
        $ch = curl_init();
        
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => \'SyncroSeviSender/1.0\',
            CURLOPT_HTTPHEADER => array(
                \'Content-Type: application/x-www-form-urlencoded\',
                \'Accept: application/json\'
            )
        ));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($response === false) {
            return array(\'success\' => false, \'error\' => \'cURL Error: \' . $error);
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
            return array(\'success\' => false, \'error\' => \'HTTP \' . $httpCode . \': \' . substr($response, 0, 200));
        }
        
        $jsonResponse = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($jsonResponse[\'success\']) && $jsonResponse[\'success\']) {
                return array(\'success\' => true, \'data\' => $jsonResponse);
            } else {
                return array(\'success\' => false, \'error\' => $jsonResponse[\'error\'] ?? \'Error desconocido\');
            }
        }
        
        return array(\'success\' => true, \'data\' => $response);
    }

    /**
     * Sistema de logging
     */
    private function log($message)
    {
        if (!$this->debug) {
            return;
        }
        
        $logDir = dirname(__FILE__) . \'/logs\';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . \'/syncrosevisender_\' . date(\'Y-m-d\') . \'.log\';
        $timestamp = date(\'Y-m-d H:i:s\');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        error_log(\'SyncroSeviSender: \' . $message);
    }

    /**
     * Página de configuración del módulo
     */
    public function getContent()
    {
        $output = \'\';
        
        if (Tools::isSubmit(\'submitSyncroSeviSender\')) {
            $output .= $this->processConfiguration();
        }
        
        $output .= $this->displayConfigurationForm();
        
        return $output;
    }

    private function processConfiguration()
    {
        $active = Tools::getValue(\'SYNCROSEVISENDER_ACTIVE\') ? 1 : 0;
        $motherUrl = trim(Tools::getValue(\'SYNCROSEVISENDER_MOTHER_URL\'));
        $shopId = (int)Tools::getValue(\'SYNCROSEVISENDER_SHOP_ID\');
        $secretKey = trim(Tools::getValue(\'SYNCROSEVISENDER_SECRET_KEY\'));
        $syncStates = Tools::getValue(\'SYNCROSEVISENDER_SYNC_STATES\');
        $timeout = (int)Tools::getValue(\'SYNCROSEVISENDER_TIMEOUT\') ?: 10;
        $maxRetries = (int)Tools::getValue(\'SYNCROSEVISENDER_MAX_RETRIES\') ?: 3;
        
        $errors = array();
        
        if ($active && empty($motherUrl)) {
            $errors[] = \'URL de la tienda madre es obligatoria\';
        }
        
        if ($active && !filter_var($motherUrl, FILTER_VALIDATE_URL)) {
            $errors[] = \'URL no es válida\';
        }
        
        if ($active && $shopId <= 0) {
            $errors[] = \'ID de tienda debe ser positivo\';
        }
        
        if ($active && empty($secretKey)) {
            $errors[] = \'Clave secreta es obligatoria\';
        }
        
        if (!empty($errors)) {
            return $this->displayError(implode(\'<br>\', $errors));
        }
        
        Configuration::updateValue(\'SYNCROSEVISENDER_ACTIVE\', $active);
        Configuration::updateValue(\'SYNCROSEVISENDER_MOTHER_URL\', $motherUrl);
        Configuration::updateValue(\'SYNCROSEVISENDER_SHOP_ID\', $shopId);
        Configuration::updateValue(\'SYNCROSEVISENDER_SECRET_KEY\', $secretKey);
        Configuration::updateValue(\'SYNCROSEVISENDER_SYNC_STATES\', $syncStates);
        Configuration::updateValue(\'SYNCROSEVISENDER_TIMEOUT\', $timeout);
        Configuration::updateValue(\'SYNCROSEVISENDER_MAX_RETRIES\', $maxRetries);
        
        return $this->displayConfirmation(\'Configuración guardada correctamente\');
    }

    private function displayConfigurationForm()
    {
        $active = Configuration::get(\'SYNCROSEVISENDER_ACTIVE\');
        $motherUrl = Configuration::get(\'SYNCROSEVISENDER_MOTHER_URL\');
        $shopId = Configuration::get(\'SYNCROSEVISENDER_SHOP_ID\');
        $secretKey = Configuration::get(\'SYNCROSEVISENDER_SECRET_KEY\');
        $syncStates = Configuration::get(\'SYNCROSEVISENDER_SYNC_STATES\');
        $timeout = Configuration::get(\'SYNCROSEVISENDER_TIMEOUT\') ?: 10;
        $maxRetries = Configuration::get(\'SYNCROSEVISENDER_MAX_RETRIES\') ?: 3;
        
        return \'<div class="alert alert-success">
            <h4>✓ Módulo Configurado Automáticamente</h4>
            <p>Este módulo fue generado y preconfigurado desde la tienda madre.</p>
            <ul>
                <li><strong>Estado:</strong> \' . ($active ? \'Activo\' : \'Inactivo\') . \'</li>
                <li><strong>Shop ID:</strong> \' . $shopId . \'</li>
                <li><strong>Estados sincronizados:</strong> \' . $syncStates . \'</li>
            </ul>
            <p>El módulo está listo para funcionar. Cambia el estado de un pedido para probarlo.</p>
        </div>\';
    }
}
?>';

    return $moduleContent;
}
/**
 * Generar config.xml
 */
private function generateConfigXml()
{
    return '<?xml version="1.0" encoding="UTF-8" ?>
<module>
    <n>syncrosevisender</n>
    <displayName><![CDATA[SyncroSevi Sender - Webhooks Tiempo Real]]></displayName>
    <version><![CDATA[1.0.0]]></version>
    <description><![CDATA[Envía webhooks automáticos a la tienda madre cuando cambia el estado de pedidos]]></description>
    <author><![CDATA[SyncroSevi Team]]></author>
    <tab><![CDATA[administration]]></tab>
    <confirmUninstall><![CDATA[¿Estás seguro de que quieres desinstalar este módulo?]]></confirmUninstall>
    <is_configurable>1</is_configurable>
    <need_instance>0</need_instance>
</module>';
}

/**
 * Generar index.php de protección
 */
private function generateIndexPhp()
{
    return '<?php
header(\'Expires: Mon, 26 Jul 1997 05:00:00 GMT\');
header(\'Last-Modified: \' . gmdate(\'D, d M Y H:i:s\') . \' GMT\');
header(\'Cache-Control: no-store, no-cache, must-revalidate\');
header(\'Cache-Control: post-check=0, pre-check=0\', false);
header(\'Pragma: no-cache\');
header(\'Location: ../\');
exit;';
}

/**
 * Generar README con instrucciones específicas
 */
private function generateReadmeContent($shop, $webhook_url)
{
    return '=== SYNCROSEVI SENDER - MÓDULO PRECONFIGURADO ===

Tienda: ' . $shop['name'] . '
Generado: ' . date('Y-m-d H:i:s') . '

INSTALACIÓN:
1. Sube la carpeta "syncrosevisender" a /modules/ de tu tienda
2. Ve a Módulos → Módulos instalados
3. Busca "SyncroSevi Sender" y haz clic en INSTALAR
4. ¡YA ESTÁ! El módulo se configura automáticamente

CONFIGURACIÓN PREESTABLECIDA:
- URL Madre: ' . $webhook_url . '
- Shop ID: ' . $shop['id_child_shop'] . '
- Estados a sincronizar: ' . $shop['import_states'] . '

VERIFICACIÓN:
1. Cambia el estado de un pedido
2. Revisa los logs en /modules/syncrosevisender/logs/
3. Confirma que aparece "SUCCESS" en los logs

SOPORTE:
Si tienes problemas, revisa el archivo de configuración
automática incluido: CONFIGURACION_AUTOMATICA.php

--- SyncroSevi Team ---';
}

/**
 * Generar script de configuración automática
 */
private function generateAutoConfigContent($shop, $webhook_url)
{
    return '<?php
/**
 * CONFIGURACIÓN AUTOMÁTICA - SyncroSevi Sender
 * 
 * Este archivo configura automáticamente el módulo si la instalación
 * normal no funcionó correctamente.
 * 
 * USO: Accede a este archivo desde el navegador después de instalar el módulo
 * URL: http://tu-tienda.com/modules/syncrosevisender/CONFIGURACION_AUTOMATICA.php
 */

$prestashop_root = realpath(dirname(__FILE__) . \'/../../..\');
if (!$prestashop_root || !file_exists($prestashop_root . \'/config/config.inc.php\')) {
    die("Error: No se puede encontrar PrestaShop");
}

require_once($prestashop_root . \'/config/config.inc.php\');
require_once($prestashop_root . \'/init.php\');

echo "<h1>Configuración Automática - SyncroSevi Sender</h1>";

try {
    // Verificar que el módulo esté instalado
    if (!Module::isInstalled(\'syncrosevisender\')) {
        throw new Exception(\'El módulo no está instalado. Instálalo primero desde el panel de módulos.\');
    }
    
    echo "<p>✓ Módulo instalado correctamente</p>";
    
    // Aplicar configuración
    Configuration::updateValue(\'SYNCROSEVISENDER_ACTIVE\', 1);
    Configuration::updateValue(\'SYNCROSEVISENDER_MOTHER_URL\', \'' . addslashes($webhook_url) . '\');
    Configuration::updateValue(\'SYNCROSEVISENDER_SHOP_ID\', ' . (int)$shop['id_child_shop'] . ');
    Configuration::updateValue(\'SYNCROSEVISENDER_SECRET_KEY\', \'' . addslashes($shop['webhook_secret']) . '\');
    Configuration::updateValue(\'SYNCROSEVISENDER_SYNC_STATES\', \'' . addslashes($shop['import_states']) . '\');
    Configuration::updateValue(\'SYNCROSEVISENDER_TIMEOUT\', 10);
    Configuration::updateValue(\'SYNCROSEVISENDER_MAX_RETRIES\', 3);
    
    echo "<p>✓ Configuración aplicada:</p>";
    echo "<ul>";
    echo "<li>URL Madre: ' . htmlspecialchars($webhook_url) . '</li>";
    echo "<li>Shop ID: ' . (int)$shop['id_child_shop'] . '</li>";
    echo "<li>Estados: ' . htmlspecialchars($shop['import_states']) . '</li>";
    echo "</ul>";
    
    echo "<h2>✓ CONFIGURACIÓN COMPLETADA</h2>";
    echo "<p><strong>El módulo ya está listo para funcionar.</strong></p>";
    echo "<p>Cambia el estado de un pedido para probar que funciona.</p>";
    
    // Eliminar este archivo por seguridad
    @unlink(__FILE__);
    echo "<p><em>Archivo de configuración eliminado por seguridad.</em></p>";
    
} catch (Exception $e) {
    echo "<p style=\"color: red;\">✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>';
}

/**
 * Añadir directorio al ZIP recursivamente
 */
private function addDirectoryToZip($zip, $dirPath, $zipPath = '')
{
    $files = scandir($dirPath);
    
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        
        $filePath = $dirPath . '/' . $file;
        $zipFilePath = $zipPath ? $zipPath . '/' . $file : $file;
        
        if (is_dir($filePath)) {
            $zip->addEmptyDir($zipFilePath);
            $this->addDirectoryToZip($zip, $filePath, $zipFilePath);
        } else {
            $zip->addFile($filePath, $zipFilePath);
        }
    }
}

/**
 * Eliminar directorio recursivamente
 */
private function removeDirectory($dir)
{
    if (!is_dir($dir)) return;
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        
        $filePath = $dir . '/' . $file;
        if (is_dir($filePath)) {
            $this->removeDirectory($filePath);
        } else {
            @unlink($filePath);
        }
    }
    @rmdir($dir);
}
}
?>