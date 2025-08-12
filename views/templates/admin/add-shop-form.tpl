{*
* 2024 SyncroSevi - Formulario para añadir tienda hija
*}

{assign var="showFormOpen" value="block"}
{assign var="iconDirection" value="down"}
{if $shops && count($shops) > 0}
    {assign var="showFormOpen" value="none"}
    {assign var="iconDirection" value="right"}
{/if}

<div class="panel syncrosevi-panel">
    <div class="panel-heading" style="cursor: pointer;" onclick="toggleAccordion('add-shop-form')">
        <i class="icon-plus"></i> Añadir Nueva Tienda Hija 
        <i class="icon-chevron-{$iconDirection} pull-right" id="add-shop-form-icon"></i>
    </div>
    <div class="panel-body" id="add-shop-form" style="display: {$showFormOpen};">
        <form method="post" class="form-horizontal">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Nombre de la Tienda *</label>
                        <input type="text" name="shop_name" class="form-control" placeholder="Ej: Tienda Barcelona" required>
                    </div>
                    <div class="form-group">
                        <label class="control-label">URL de la Tienda *</label>
                        <input type="url" name="shop_url" id="shop_url" class="form-control" placeholder="https://tienda-hija.com" required>
                    </div>
                    <div class="form-group">
                        <label class="control-label">Clave API WebService *</label>
                        <input type="text" name="api_key" id="api_key" class="form-control" placeholder="Clave del WebService de la tienda hija" required>
                        <small class="help-block">Generar en: Parámetros Avanzados > WebService</small>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Cliente Asignado *</label>
                        <div class="customer-search">
                            <input type="text" id="customer-search" class="form-control" placeholder="Buscar cliente..." onkeyup="searchCustomers(this.value, 'customer-results', 'selected-customer-id')" autocomplete="off">
                            <input type="hidden" id="selected-customer-id" name="id_customer" required>
                            <div id="customer-results" class="customer-results"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label">Dirección de Facturación *</label>
                        <select name="id_address" id="id_address" class="form-control" required>
                            <option value="">Primero selecciona un cliente</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label">Grupo de Precios *</label>
                        <select name="id_group" class="form-control" required>
                            <option value="">Seleccionar grupo...</option>
                            {foreach from=$groups item=group}
                                <option value="{$group.id_group}">{$group.name}</option>
                            {/foreach}
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">¿Usar Transportista Específico?</label>
                        <div>
                            <label class="radio-inline">
                                <input type="radio" name="use_carrier" value="0" onclick="toggleTransport(false)" checked> No
                            </label>
                            <label class="radio-inline">
                                <input type="radio" name="use_carrier" value="1" onclick="toggleTransport(true)"> Sí
                            </label>
                        </div>
                        <div id="transport-options" class="transport-options">
                            <select name="id_carrier" class="form-control">
                                <option value="">Seleccionar transportista...</option>
                                {foreach from=$carriers item=carrier}
                                    <option value="{$carrier.id_carrier}">{$carrier.name}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label">Importar desde el pedido ID *</label>
                        <input type="number" name="start_order_id" class="form-control" value="1" min="1" required>
                        <small class="help-block">Solo se importarán pedidos con ID igual o mayor a este número</small>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Estado del Pedido *</label>
                        <select name="id_order_state" class="form-control" required>
                            <option value="">Seleccionar estado...</option>
                            {foreach from=$order_states item=state}
                                <option value="{$state.id_order_state}">{$state.name}</option>
                            {/foreach}
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="active" value="1" checked> Tienda activa
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label">Estados de pedidos a importar *</label>
                <div class="alert alert-info">
                    <small>Primero completa URL y API Key, luego haz clic en "Cargar Estados"</small>
                </div>
                <button type="button" id="load-states-btn" class="btn btn-info btn-sm" onclick="loadStatesFromShop()">
                    Cargar Estados de la Tienda Hija
                </button>
                <div id="import-states-container" class="import-states-container"></div>
            </div>
            
            <div class="form-group">
                <button type="submit" name="submitAddShop" class="btn btn-primary btn-lg">
                    <i class="icon-plus"></i> Añadir Tienda Hija
                </button>
            </div>
        </form>
    </div>
</div>