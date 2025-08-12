{*
* 2024 SyncroSevi - Template de Edición de Tienda
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
*}

<div class="syncrosevi-panel">
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="icon-edit"></i> Editar Tienda Hija: {$shop.name}
                <a href="{$admin_link}&token={$token}" class="btn btn-default btn-sm pull-right">
                    <i class="icon-arrow-left"></i> Volver al Panel
                </a>
            </h3>
        </div>
        <div class="panel-body">
            
            {* Información actual de la tienda *}
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h4 class="panel-title"><i class="icon-info-circle"></i> Información Actual</h4>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-condensed">
                                <tr>
                                    <td><strong>ID Tienda:</strong></td>
                                    <td>{$shop.id_child_shop}</td>
                                </tr>
                                <tr>
                                    <td><strong>Fecha Creación:</strong></td>
                                    <td>{$shop.date_add|date_format:"%d/%m/%Y %H:%M"}</td>
                                </tr>
                                <tr>
                                    <td><strong>Última Actualización:</strong></td>
                                    <td>{$shop.date_upd|date_format:"%d/%m/%Y %H:%M"}</td>
                                </tr>
                                <tr>
                                    <td><strong>Estados a Importar:</strong></td>
                                    <td><code>{$shop.import_states}</code></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            {if $shop_stats}
                            <table class="table table-condensed">
                                <tr>
                                    <td><strong>Pedidos Pendientes:</strong></td>
                                    <td><span class="badge badge-warning">{$shop_stats.pending_orders}</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Pedidos Procesados:</strong></td>
                                    <td><span class="badge badge-success">{$shop_stats.processed_orders}</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Última Sincronización:</strong></td>
                                    <td>{$shop_stats.last_sync}</td>
                                </tr>
                                <tr>
                                    <td><strong>Estado:</strong></td>
                                    <td>
                                        {if $shop.active}
                                            <span class="badge badge-success">Activa</span>
                                        {else}
                                            <span class="badge badge-danger">Inactiva</span>
                                        {/if}
                                    </td>
                                </tr>
                            </table>
                            {/if}
                        </div>
                    </div>
                    
                    {* Acciones rápidas *}
                    <div class="btn-group" role="group">
    <button type="button" class="btn btn-info btn-sm" onclick="testConnection({$shop.id_child_shop})">
        <i class="icon-link"></i> Probar Conexión
    </button>
    <button type="button" class="btn btn-warning btn-sm" onclick="syncThisShop({$shop.id_child_shop})">
        <i class="icon-refresh"></i> Sincronizar Esta Tienda
    </button>
    <button type="button" class="btn btn-success btn-sm" onclick="processThisShop({$shop.id_child_shop})">
        <i class="icon-cogs"></i> Procesar Pedidos de Esta Tienda
    </button>
    <button type="button" class="btn btn-danger btn-sm" onclick="resetShopData({$shop.id_child_shop})" 
            data-confirm="¿Estás seguro de restablecer todos los datos de esta tienda? Se eliminarán TODOS los pedidos sincronizados y procesados.">
        <i class="icon-trash"></i> Restablecer Tienda
    </button>
</div>
                </div>
            </div>
            
            {* Formulario de edición *}
            <form method="post" action="{$admin_link}" id="editShopForm" class="form-horizontal">
                
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title"><i class="icon-cogs"></i> Configuración General</h4>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="shop_name" class="control-label">Nombre de la Tienda *</label>
                                    <input type="text" class="form-control" id="shop_name" name="shop_name" 
                                           value="{$shop.name}" required>
                                    <small class="help-block">Nombre descriptivo para identificar la tienda</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="shop_url" class="control-label">URL de la Tienda *</label>
                                    <input type="url" class="form-control" id="shop_url" name="shop_url" 
                                           value="{$shop.url}" required>
                                    <small class="help-block">URL completa de la tienda hija</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="api_key" class="control-label">Clave API *</label>
                                    <input type="text" class="form-control" id="api_key" name="api_key" 
                                           value="{$shop.api_key}" required>
                                    <small class="help-block">Clave de WebService de la tienda hija</small>
                                    <button type="button" class="btn btn-info btn-xs" onclick="testApiKey()">
                                        <i class="icon-link"></i> Probar Clave
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_order_id" class="control-label">ID Pedido Inicial</label>
                                    <input type="number" class="form-control" id="start_order_id" name="start_order_id" 
                                           value="{$shop.start_order_id}" min="1">
                                    <small class="help-block">Desde qué pedido empezar a sincronizar</small>
                                </div>
                                
                               <div class="form-group">
    <div class="checkbox">
        <label>
            <input type="checkbox" name="active" value="1" {if $shop.active}checked{/if}> 
            Tienda activa para sincronización
        </label>
    </div>
    <small class="help-block">Solo las tiendas activas participan en la sincronización automática</small>
</div>

<div class="form-group">
    <div class="checkbox">
        <label>
            <input type="checkbox" name="realtime_enabled" value="1" {if $shop.realtime_enabled}checked{/if}> 
            Sincronización en tiempo real habilitada
        </label>
    </div>
    <small class="help-block">Si está desactivado, solo sincronizará por cron programado</small>
</div>
                                
                                <div class="form-group">
                                    <button type="button" class="btn btn-info" onclick="loadStatesFromShopEdit()">
                                        <i class="icon-download"></i> Actualizar Estados desde Tienda
                                    </button>
                                    <small class="help-block">Cargar estados actuales de la tienda hija</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title"><i class="icon-user"></i> Configuración de Cliente</h4>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="customer-search-edit" class="control-label">Cliente Asignado *</label>
                                    <div class="customer-search">
                                        <input type="text" class="form-control" id="customer-search-edit" 
                                               value="{$customer.firstname} {$customer.lastname} ({$customer.email})"
                                               onkeyup="searchCustomers(this.value, 'customer-results-edit', 'id_customer_edit')">
                                        <div id="customer-results-edit" class="customer-results"></div>
                                    </div>
                                    <input type="hidden" id="id_customer_edit" name="id_customer" 
                                           value="{$shop.id_customer}" required>
                                    <small class="help-block">Cliente que se asignará a los pedidos consolidados</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="id_group_edit" class="control-label">Grupo de Precios *</label>
                                    <select class="form-control" id="id_group_edit" name="id_group" required>
                                        <option value="">Seleccionar grupo...</option>
                                        {foreach from=$groups item=group}
                                            <option value="{$group.id_group}" {if $group.id_group == $shop.id_group}selected{/if}>
                                                {$group.name}
                                            </option>
                                        {/foreach}
                                    </select>
                                    <small class="help-block">Grupo de precios para aplicar descuentos</small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="id_address_edit" class="control-label">Dirección de Facturación *</label>
                                    <select class="form-control" id="id_address_edit" name="id_address" required>
                                        {foreach from=$addresses item=address}
                                            <option value="{$address.id_address}" {if $address.id_address == $shop.id_address}selected{/if}>
                                                {$address.alias} - {$address.firstname} {$address.lastname}, {$address.address1}, {$address.city}
                                            </option>
                                        {/foreach}
                                    </select>
                                    <small class="help-block">Dirección para facturación y envío</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="id_order_state_edit" class="control-label">Estado del Pedido *</label>
                                    <select class="form-control" id="id_order_state_edit" name="id_order_state" required>
                                        <option value="">Seleccionar estado...</option>
                                        {foreach from=$order_states item=state}
                                            <option value="{$state.id_order_state}" {if $state.id_order_state == $shop.id_order_state}selected{/if}>
                                                {$state.name}
                                            </option>
                                        {/foreach}
                                    </select>
                                    <small class="help-block">Estado inicial de los pedidos creados</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title"><i class="icon-truck"></i> Configuración de Envío</h4>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="id_carrier_edit" class="control-label">Transportista</label>
                                    <select class="form-control" id="id_carrier_edit" name="id_carrier">
                                        <option value="">Sin transportista específico</option>
                                        {foreach from=$carriers item=carrier}
                                            <option value="{$carrier.id_carrier}" {if $carrier.id_carrier == $shop.id_carrier}selected{/if}>
                                                {$carrier.name}
                                            </option>
                                        {/foreach}
                                    </select>
                                    <small class="help-block">Si no se especifica, se usará el transportista por defecto</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-info">
                                    <i class="icon-info-circle"></i>
                                    <strong>Nota:</strong> El transportista afecta al cálculo de gastos de envío en los pedidos consolidados.
                                </div>
                            </div>
                        </div>
                </div>
</div>



<div class="panel panel-default">
    <div class="panel-heading">
        <h4 class="panel-title">
            <i class="icon-list-ol"></i> Estados a Importar
                            <button type="button" class="btn btn-info btn-xs pull-right" onclick="loadStatesFromShopEdit()">
                                <i class="icon-refresh"></i> Actualizar desde Tienda
                            </button>
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div id="import-states-container-edit" class="import-states-container" style="display: block;">
                            {assign var="selectedStates" value=","|explode:$shop.import_states}
                            <div class="alert alert-info">
    <i class="icon-info-circle"></i>
    Estados actuales seleccionados: <strong>{$shop.import_states}</strong>
    {if $child_states}
        <br><br><strong>Estados disponibles en la tienda hija:</strong>
        <ul style="margin-top: 10px;">
        {foreach from=$child_states item=state}
            <li>ID {$state.id}: {$state.name}</li>
        {/foreach}
        </ul>
    {/if}
</div>
                            <p>Haz clic en "Actualizar desde Tienda" para cargar los estados disponibles y modificar la selección.</p>
                        </div>
                        <input type="hidden" name="import_states" value="{$shop.import_states}" id="hidden_import_states">
                    </div>
                </div>
                <div class="panel panel-info">
    <div class="panel-heading">
        <h4 class="panel-title">
            <i class="icon-flash"></i> Configuración de Webhook (Tiempo Real)
            {if $shop.realtime_enabled}
                <span class="badge badge-success pull-right">Activo</span>
            {else}
                <span class="badge badge-warning pull-right">Inactivo</span>
            {/if}
        </h4>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <h5><i class="icon-key"></i> Clave Secreta:</h5>
                <div class="input-group">
                    <input type="text" class="form-control" value="{$shop.webhook_secret}" readonly id="webhook-secret-display">
                    <span class="input-group-btn">
                        <button type="button" class="btn btn-warning" onclick="regenerateSecret({$shop.id_child_shop})">
                            <i class="icon-refresh"></i> Regenerar
                        </button>
                        <button type="button" class="btn btn-info" onclick="copyToClipboard('webhook-secret-display')">
                            <i class="icon-copy"></i> Copiar
                        </button>
                    </span>
                </div>
                <small class="help-block">Esta clave se usa para validar webhooks desde la tienda hija</small>
            </div>
            <div class="col-md-6">
                <h5><i class="icon-link"></i> URL de Webhook:</h5>
                <div class="input-group">
                    <input type="text" class="form-control" value="{$admin_link}&action=realtime_sync&security_token={$token}" readonly id="webhook-url-display">
                    <span class="input-group-btn">
                        <button type="button" class="btn btn-info" onclick="copyToClipboard('webhook-url-display')">
                            <i class="icon-copy"></i> Copiar
                        </button>
                    </span>
                </div>
                <small class="help-block">URL que debe configurar en la tienda hija</small>
            </div>
        </div>
        
        {if $shop.last_webhook}
        <div class="alert alert-success" style="margin-top: 15px;">
            <i class="icon-check-circle"></i>
            <strong>Último webhook recibido:</strong> {$shop.last_webhook|date_format:"%d/%m/%Y %H:%M:%S"}
        </div>
        {else}
        <div class="alert alert-warning" style="margin-top: 15px;">
            <i class="icon-exclamation-triangle"></i>
            <strong>Atención:</strong> Aún no se han recibido webhooks de esta tienda. Verifica la configuración.
        </div>
        {/if}
        
        <div class="alert alert-info persistent" style="margin-top: 15px;">
    <h5><i class="icon-download"></i> Módulo para Tienda Hija:</h5>
    <p>Para habilitar el envío automático de webhooks, instala este mini-módulo en la tienda hija:</p>
    <a href="{$admin_link}&action=download_child_module&id_child_shop={$shop.id_child_shop}&token={$token}" 
       class="btn btn-primary btn-sm">
        <i class="icon-download"></i> Descargar SyncroSeviSender.zip
    </a>
    <br><small class="text-muted">El módulo ya viene preconfigurado con las URLs y claves de esta tienda.</small>
</div>
    </div>
</div>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title"><i class="icon-save"></i> Guardar Cambios</h4>
                    </div>
                    <div class="panel-body">
                        <div class="btn-group" role="group">
                            <button type="submit" name="submitEditShop" class="btn btn-primary btn-lg">
                                <i class="icon-save"></i> Guardar Cambios
                            </button>
                            <a href="{$admin_link}&token={$token}" class="btn btn-default">
                                <i class="icon-times"></i> Cancelar
                            </a>
                            <button type="button" class="btn btn-info" onclick="previewChanges()">
                                <i class="icon-eye"></i> Vista Previa
                            </button>
                        </div>
                    </div>
                </div>
                
                <input type="hidden" name="action" value="update_shop">
                <input type="hidden" name="id_child_shop" value="{$shop.id_child_shop}">
                <input type="hidden" name="token" value="{$token}">
            </form>
        </div>
    </div>
</div>

{* Modal de vista previa *}
<div class="modal fade" id="previewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="icon-eye"></i> Vista Previa de Cambios</h4>
            </div>
            <div class="modal-body" id="previewContent">
                <!-- Contenido de vista previa -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="submitForm()">
                    <i class="icon-save"></i> Confirmar y Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var adminLink = '{$admin_link}';
var token = '{$token}';
var shopId = {$shop.id_child_shop};
// Mostrar información de webhook
function showWebhookInfo(shopId) {
    $('#webhookInfoModal').modal('show');
    $('#webhookInfoContent').html('<div class="text-center"><i class="icon-spinner icon-spin"></i> Cargando información...</div>');
    
    $.ajax({
        url: SyncroSeviAdmin.adminLink,
        method: 'GET',
        data: {
            action: 'get_webhook_info',
            id_child_shop: shopId,
            token: SyncroSeviAdmin.token
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.shop) {
                renderWebhookInfo(response);
            } else {
                $('#webhookInfoContent').html('<div class="alert alert-danger">Error: ' + (response.message || 'Error desconocido') + '</div>');
            }
        },
        error: function() {
            $('#webhookInfoContent').html('<div class="alert alert-danger">Error de comunicación</div>');
        }
    });
}

// Renderizar información de webhook
function renderWebhookInfo(data) {
    var shop = data.shop;
    var stats = data.webhook_stats;
    
    $('#webhook-shop-name').text(shop.name);
    
    var content = '<div class="row">';
    
    // Estado del webhook
    content += '<div class="col-md-6">';
    content += '<h5><i class="icon-info-circle"></i> Estado del Webhook</h5>';
    content += '<table class="table table-condensed">';
    content += '<tr><td><strong>Tiempo Real:</strong></td><td>';
    if (shop.realtime_enabled == 1) {
        content += '<span class="badge badge-success">Habilitado</span>';
    } else {
        content += '<span class="badge badge-warning">Deshabilitado</span>';
    }
    content += '</td></tr>';
    content += '<tr><td><strong>Último Webhook:</strong></td><td>' + (shop.last_webhook || 'Nunca') + '</td></tr>';
    content += '<tr><td><strong>Webhooks Totales:</strong></td><td><span class="badge badge-info">' + stats.total_received + '</span></td></tr>';
    content += '<tr><td><strong>Últimas 24h:</strong></td><td><span class="badge badge-warning">' + stats.last_24h + '</span></td></tr>';
    content += '</table>';
    content += '</div>';
    
    // Configuración
    content += '<div class="col-md-6">';
    content += '<h5><i class="icon-cogs"></i> Configuración</h5>';
    content += '<div class="form-group">';
    content += '<label>URL del Webhook:</label>';
    content += '<div class="input-group">';
    content += '<input type="text" class="form-control" value="' + data.webhook_url + '" readonly id="webhook-url-copy">';
    content += '<span class="input-group-btn">';
    content += '<button type="button" class="btn btn-info btn-sm" onclick="copyToClipboard(\'webhook-url-copy\')">';
    content += '<i class="icon-copy"></i></button></span></div></div>';
    
    content += '<div class="form-group">';
    content += '<label>Clave Secreta:</label>';
    content += '<div class="input-group">';
    content += '<input type="text" class="form-control" value="' + shop.webhook_secret + '" readonly id="webhook-secret-copy">';
    content += '<span class="input-group-btn">';
    content += '<button type="button" class="btn btn-info btn-sm" onclick="copyToClipboard(\'webhook-secret-copy\')">';
    content += '<i class="icon-copy"></i></button></span></div></div>';
    content += '</div>';
    
    content += '</div>';
    
    // Instrucciones
    content += '<div class="alert alert-info">';
    content += '<h5><i class="icon-lightbulb-o"></i> Instrucciones:</h5>';
    content += '<ol>';
    content += '<li>Descarga e instala el módulo SyncroSeviSender en la tienda hija</li>';
    content += '<li>Configura la URL del webhook y la clave secreta</li>';
    content += '<li>Activa el módulo y verifica que los pedidos se sincronicen automáticamente</li>';
    content += '</ol>';
    content += '</div>';
    
    $('#webhookInfoContent').html(content);
}

// Copiar al portapapeles
function copyToClipboard(elementId) {
    var element = document.getElementById(elementId);
    element.select();
    element.setSelectionRange(0, 99999);
    document.execCommand('copy');
    
    showAlert('success', 'Copiado al portapapeles');
}
// Cargar direcciones del cliente seleccionado
function loadCustomerAddressesEdit(customerId) {
    var addressSelect = $('#id_address_edit');
    addressSelect.html('<option value="">Cargando direcciones...</option>');
    
    // Filtrar direcciones por customer ID
    var customerAddresses = [];
    if (typeof addresses_all !== 'undefined') {
        customerAddresses = addresses_all.filter(function(addr) {
            return addr.id_customer == customerId;
        });
    }
    
    addressSelect.html('<option value="">Seleccionar dirección...</option>');
    customerAddresses.forEach(function(address) {
        var option = $('<option>')
            .val(address.id_address)
            .text(address.alias + ' - ' + address.address1 + ', ' + address.city + ' (' + address.postcode + ')');
        addressSelect.append(option);
    });
    
    if (customerAddresses.length === 0) {
        addressSelect.html('<option value="">Este cliente no tiene direcciones</option>');
    }
}

// Probar clave API
function testApiKey() {
    var url = $('#shop_url').val();
    var apiKey = $('#api_key').val();
    
    if (!url || !apiKey) {
        alert('Por favor completa URL y API Key');
        return;
    }
    
    var btn = event.target;
    var originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="icon-spinner icon-spin"></i>';
    btn.disabled = true;
    
    $.ajax({
        url: adminLink + '&action=test&id_shop=' + shopId,
        method: 'GET',
        dataType: 'json',
        timeout: 15000,
        success: function(response) {
            if (response.success) {
                showAlert('success', '? Conexión exitosa con la tienda');
            } else {
                showAlert('error', '? Error de conexión: ' + response.message);
            }
        },
        error: function() {
            showAlert('error', 'Error de comunicación al probar la conexión');
        },
        complete: function() {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    });
}

// Cargar estados desde la tienda (versión para edición)
function loadStatesFromShopEdit() {
    var url = $('#shop_url').val();
    var apiKey = $('#api_key').val();
    
    if (!url || !apiKey) {
        alert('Completa primero URL y API Key');
        return;
    }
    
    var currentStates = '{$shop.import_states}'.split(',');
    loadStatesFromShopGeneric(url, apiKey, 'import-states-container-edit', currentStates);
}

// Vista previa de cambios
function previewChanges() {
    var formData = $('#editShopForm').serializeArray();
    var preview = '<div class="table-responsive"><table class="table table-striped">';
    preview += '<tr><th>Campo</th><th>Valor Actual</th><th>Nuevo Valor</th></tr>';
    
    // Campos principales para mostrar en preview
    var fieldsToShow = {
        'shop_name': 'Nombre de Tienda',
        'shop_url': 'URL',
        'api_key': 'Clave API',
        'id_customer': 'ID Cliente',
        'id_group': 'ID Grupo',
        'id_address': 'ID Dirección',
        'id_carrier': 'ID Transportista',
        'id_order_state': 'Estado Pedido',
        'start_order_id': 'Pedido Inicial',
        'active': 'Activo'
    };
    
    formData.forEach(function(field) {
        if (fieldsToShow[field.name]) {
            var currentVal = getOriginalValue(field.name);
            preview += '<tr>';
            preview += '<td><strong>' + fieldsToShow[field.name] + '</strong></td>';
            preview += '<td>' + (currentVal || 'N/A') + '</td>';
            preview += '<td>' + (field.value || 'N/A') + '</td>';
            preview += '</tr>';
        }
    });
    
    preview += '</table></div>';
    preview += '<div class="alert alert-warning">';
    preview += '<i class="icon-exclamation-triangle"></i> ';
    preview += '<strong>Importante:</strong> Revisa los cambios antes de guardar. ';
    preview += 'Algunos cambios pueden afectar la sincronización en curso.';
    preview += '</div>';
    
    $('#previewContent').html(preview);
    $('#previewModal').modal('show');
}

// Obtener valor original del campo
function getOriginalValue(fieldName) {
    switch(fieldName) {
        case 'shop_name': return '{$shop.name}';
        case 'shop_url': return '{$shop.url}';
        case 'api_key': return '{$shop.api_key}';
        case 'id_customer': return '{$shop.id_customer}';
        case 'id_group': return '{$shop.id_group}';
        case 'id_address': return '{$shop.id_address}';
        case 'id_carrier': return '{$shop.id_carrier}';
        case 'id_order_state': return '{$shop.id_order_state}';
        case 'start_order_id': return '{$shop.start_order_id}';
        case 'active': return '{if $shop.active}1{else}0{/if}';
        default: return '';
    }
}

// Enviar formulario
function submitForm() {
    $('#previewModal').modal('hide');
    $('#editShopForm').submit();
}

// Sincronizar solo esta tienda
function syncThisShop(shopId) {
    if (confirm('¿Sincronizar pedidos solo de esta tienda?')) {
        showAlert('info', 'Funcionalidad de sincronización individual pendiente de implementación');
    }
}

// Procesar pedidos solo de esta tienda
function processThisShop(shopId) {
    if (confirm('¿Procesar pedidos pendientes solo de esta tienda?')) {
        showAlert('info', 'Funcionalidad de procesamiento individual pendiente de implementación');
    }
}
// Restablecer datos de la tienda
function resetShopData(shopId) {
    if (confirm('¿ESTÁS COMPLETAMENTE SEGURO?\n\nEsto eliminará:\n- Todos los pedidos sincronizados\n- Todos los pedidos procesados\n- Todo el historial de esta tienda\n\nEsta acción NO SE PUEDE DESHACER.')) {
        if (confirm('ÚLTIMA CONFIRMACIÓN:\n\n¿Eliminar TODOS los datos de sincronización de esta tienda?')) {
            window.location.href = adminLink + '&action=reset_shop&id_child_shop=' + shopId + '&token=' + token;
        }
    }
}
// Función para mostrar alertas
function showAlert(type, message) {
    var alertClass = 'alert-info';
    var icon = 'icon-info-circle';
    
    switch(type) {
        case 'success':
            alertClass = 'alert-success';
            icon = 'icon-check-circle';
            break;
        case 'error':
            alertClass = 'alert-danger';
            icon = 'icon-exclamation-triangle';
            break;
        case 'warning':
            alertClass = 'alert-warning';
            icon = 'icon-exclamation-circle';
            break;
    }
    
    var alertHtml = '<div class="alert ' + alertClass + ' alert-dismissible fade-in">' +
                   '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                   '<i class="' + icon + '"></i> ' + message +
                   '</div>';
    
    $('.panel-body').first().prepend(alertHtml);
    
    // Auto-ocultar después de 1 minutos segundos
    setTimeout(function() {
        $('.alert').first().fadeOut(function() { $(this).remove(); });
    }, 60000);
}

// Inicialización
$(document).ready(function() {
    // Cargar datos para los selectores
    if (typeof SyncroSeviAdmin !== 'undefined') {
        SyncroSeviAdmin.init({
            customers: {$customers|json_encode},
            addresses: {$addresses_all|json_encode},
            adminLink: adminLink,
            token: token
        });
    }
    
    // Validación del formulario
    $('#editShopForm').on('submit', function(e) {
        var requiredFields = ['shop_name', 'shop_url', 'api_key', 'id_customer', 'id_group', 'id_address', 'id_order_state'];
        var errors = [];
        
        requiredFields.forEach(function(field) {
            var value = $('[name="' + field + '"]').val();
            if (!value || value === '') {
                errors.push(field);
            }
        });
        
        if (errors.length > 0) {
            e.preventDefault();
            showAlert('error', 'Por favor completa los campos requeridos: ' + errors.join(', '));
            return false;
        }
        
        // Confirmar cambios importantes
        if ($('#shop_url').val() !== '{$shop.url}' || $('#api_key').val() !== '{$shop.api_key}') {
            if (!confirm('Has cambiado la URL o API Key. Esto puede afectar la sincronización. ¿Continuar?')) {
                e.preventDefault();
                return false;
            }
        }
    });
    

    
    // Inicializar tooltips
    $('[data-toggle="tooltip"]').tooltip();
});
</script>

<style>
.form-group {
    margin-bottom: 20px;
}

.help-block {
    color: #737373;
    font-size: 12px;
    margin-top: 5px;
}

.panel-body .alert:first-child {
    margin-top: 0;
}

.table-condensed td, .table-condensed th {
    padding: 5px;
}

.badge {
    font-size: 11px;
    margin: 2px;
}

.btn-group .btn {
    margin-right: 5px;
}

.import-states-container {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ddd;
    padding: 15px;
    background-color: #f9f9f9;
    border-radius: 4px;
}

.import-states-container .checkbox {
    margin: 8px 0;
}

.customer-search {
    position: relative;
}

.customer-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.customer-item {
    padding: 10px 15px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
    transition: background-color 0.2s;
}

.customer-item:hover {
    background: #f5f5f5;
}

.customer-item:last-child {
    border-bottom: none;
}

@media (max-width: 768px) {
    .btn-group .btn {
        margin-bottom: 5px;
        display: block;
        width: 100%;
    }
    
    .panel-body {
        padding: 10px;
    }
    
    .form-horizontal .control-label {
        text-align: left;
        margin-bottom: 5px;
    }
}
</style>
<style>
.no-auto-hide {
    animation: none !important;
    transition: none !important;
}

.no-auto-hide.fade {
    opacity: 1 !important;
}
</style>

<script>
$(document).ready(function() {
    // Prevenir que las alertas con clase no-auto-hide desaparezcan
    $('.no-auto-hide').off('close.bs.alert');
    
    // Si Bootstrap intenta ocultarlas, cancelar
    $('.no-auto-hide').on('close.bs.alert', function(e) {
        e.preventDefault();
        e.stopPropagation();
        return false;
    });
    
    // También prevenir fadeOut manual
    $('.no-auto-hide').fadeOut = function() { return this; };
    $('.no-auto-hide').hide = function() { return this; };
});
</script>