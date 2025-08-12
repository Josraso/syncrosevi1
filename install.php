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

// Prevenir acceso directo
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Script de instalación para el módulo SyncroSevi
 */

// Configurar token de webhook durante la instalación
$webhook_token = md5('syncrosevi_' . Configuration::get('PS_SHOP_NAME') . '_' . Configuration::get('PS_SHOP_EMAIL') . '_' . date('Y-m'));
Configuration::updateValue('SYNCROSEVI_WEBHOOK_TOKEN', $webhook_token);

// Crear directorio de logs si no existe
$logDir = dirname(__FILE__) . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Crear archivo .htaccess para proteger logs
$htaccessContent = "Order Deny,Allow\nDeny from all\n";
file_put_contents($logDir . '/.htaccess', $htaccessContent);

echo "SyncroSevi instalado correctamente.\n";
echo "Token de webhook configurado: " . $webhook_token . "\n";
echo "Directorio de logs creado: " . $logDir . "\n";
?>