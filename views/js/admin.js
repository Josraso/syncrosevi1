/**
 * 2024 SyncroSevi
 * 
 * JavaScript principal para el panel de administración - REFACTORIZADO
 */

// Variables globales
var SyncroSeviAdmin = {
    customers_cache: [],
    addresses_cache: {},
    adminLink: "",
    token: "",
    
    // Inicialización
    init: function(config) {
        this.customers_cache = config.customers || [];
        this.addresses_cache = {};
        this.adminLink = config.adminLink || "";
        this.token = config.token || "";
        
        this.bindEvents();
        this.initTooltips();
    },
    
    // Eventos
    bindEvents: function() {
        var self = this;
        
        // Ocultar resultados al hacer clic fuera
        $(document).on("click", function(e) {
            if (!$(e.target).closest(".customer-search").length) {
                $(".customer-results").hide();
            }
        });
        
        // Confirmaciones de eliminación
        $(document).on('click', '[data-confirm]', function(e) {
            var message = $(this).data('confirm') || '¿Estás seguro?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
        
        // Auto-ocultar alertas de éxito
        setTimeout(function() {
            $('.alert-success, .alert-info').not('.persistent').fadeOut(5000);
        }, 5000);
    },
    
    // Inicializar tooltips
    initTooltips: function() {
        if (typeof $().tooltip === 'function') {
            $('[data-toggle="tooltip"]').tooltip();
        }
    }
};

/**
 * Búsqueda de clientes
 */
function searchCustomers(query, resultId, customerId) {
    if (query.length < 2) {
        $("#" + resultId).hide();
        return;
    }
    
    var filtered = SyncroSeviAdmin.customers_cache.filter(function(c) {
        var searchText = (c.firstname + " " + c.lastname + " " + c.email).toLowerCase();
        return searchText.indexOf(query.toLowerCase()) !== -1;
    });
    
    showCustomerResults(filtered.slice(0, 10), resultId, customerId);
}

function showCustomerResults(customers, resultId, customerId) {
    var resultsDiv = $("#" + resultId);
    resultsDiv.html("");
    
    customers.forEach(function(customer) {
        var div = $("<div>")
            .addClass("customer-item")
            .html("<strong>" + customer.firstname + " " + customer.lastname + "</strong><br><small>" + customer.email + "</small>")
            .click(function() { 
                selectCustomer(customer, resultId, customerId); 
            });
        resultsDiv.append(div);
    });
    
    resultsDiv.toggle(customers.length > 0);
}

function selectCustomer(customer, resultId, customerId) {
    var searchField = $("#" + resultId.replace("-results", "-search"));
    searchField.val(customer.firstname + " " + customer.lastname);
    $("#" + customerId).val(customer.id_customer);
    $("#" + resultId).hide();
    
    // Cargar direcciones del cliente - ARREGLO AQUÍ
    loadCustomerAddresses(customer.id_customer, "id_address");
}

function loadCustomerAddresses(customerId, addressSelectId) {
    // Verificar cache
    if (SyncroSeviAdmin.addresses_cache[customerId]) {
        populateAddresses(SyncroSeviAdmin.addresses_cache[customerId], addressSelectId);
        return;
    }
    
    // Cargar desde addresses_all global (definida en main.tpl)
    var allAddresses = window.addresses_all || [];
    var customerAddresses = allAddresses.filter(function(addr) {
        return addr.id_customer == customerId;
    });
    
    SyncroSeviAdmin.addresses_cache[customerId] = customerAddresses;
    populateAddresses(customerAddresses, addressSelectId);
}

function populateAddresses(addresses, selectId) {
    var select = $("#" + selectId);
    select.html('<option value="">Seleccionar dirección...</option>');
    
    addresses.forEach(function(address) {
        var option = $("<option>")
            .val(address.id_address)
            .text(address.alias + " - " + address.address1 + ", " + address.city + " (" + address.postcode + ")");
        select.append(option);
    });
    
    if (addresses.length === 0) {
        select.html('<option value="">Este cliente no tiene direcciones</option>');
    }
}

/**
 * Toggle opciones de transporte
 */
function toggleTransport(show, containerId) {
    var container = $("#" + (containerId || "transport-options"));
    container.toggle(show);
    if (!show) {
        container.find("select").val("");
    }
}

/**
 * Probar conexión con tienda
 */
function testConnection(shopId) {
    if (!shopId) {
        showAlert('error', 'ID de tienda inválido');
        return;
    }
    
    var btn = $('button[onclick*="testConnection(' + shopId + ')"]');
    var originalHtml = btn.html();
    
    btn.html('<i class="icon-spinner icon-spin"></i>').prop('disabled', true);
    
    $.ajax({
        url: SyncroSeviAdmin.adminLink + "&action=test_connection&id_child_shop=" + shopId,
        method: 'GET',
        dataType: 'json',
        timeout: 30000,
        success: function(response) {
            if (response.success) {
                var message = "✓ Conexión exitosa con la tienda";
                if (response.shop_info && response.shop_info.name) {
                    message += "\n\nInfo: " + response.shop_info.name;
                    if (response.shop_info.domain) {
                        message += "\nDominio: " + response.shop_info.domain;
                    }
                }
                alert(message);
            } else {
                alert("✗ Error de conexión\n\n" + response.message);
            }
        },
        error: function(xhr, status, error) {
            var message = "Error de comunicación";
            if (status === 'timeout') {
                message += " (tiempo agotado)";
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                message += ": " + xhr.responseJSON.message;
            }
            alert(message);
        },
        complete: function() {
            btn.html(originalHtml).prop('disabled', false);
        }
    });
}

/**
 * Cargar estados de la tienda hija
 */
function loadStatesFromShop() {
    var url = $("#shop_url").val();
    var apiKey = $("#api_key").val();
    
    if (!url || !apiKey) {
        alert("Completa primero URL y API Key");
        return;
    }
    
    var btn = $("#load-states-btn");
    btn.html('<i class="icon-spinner icon-spin"></i> Cargando...').prop('disabled', true);
    
    $.ajax({
        url: SyncroSeviAdmin.adminLink + "&action=get_states",
        method: 'GET',
        data: {
            shop_url: url,
            api_key: apiKey
        },
        dataType: 'json',
        timeout: 30000,
        success: function(response) {
            if (response.success && response.states) {
                var container = $("#import-states-container");
                container.html("");
                
                response.states.forEach(function(state) {
                    var div = $("<div>")
                        .addClass("checkbox")
                        .html('<label><input type="checkbox" name="import_states[]" value="' + state.id + '"> ID ' + state.id + ': ' + state.name + '</label>');
                    container.append(div);
                });
                
                container.show();
                showAlert('success', 'Estados cargados correctamente (' + response.states.length + ' encontrados)');
            } else {
                showAlert('error', 'Error cargando estados: ' + (response.message || 'Error desconocido'));
            }
        },
        error: function(xhr, status, error) {
            var message = "Error de comunicación al cargar estados";
            if (status === 'timeout') {
                message += " (tiempo agotado)";
            }
            showAlert('error', message);
        },
        complete: function() {
            btn.html('Cargar Estados de la Tienda Hija').prop('disabled', false);
        }
    });
}
// Función específica para el modal de crear tienda
function loadStatesFromShopModal() {
    var url = $("#shop_url").val();
    var apiKey = $("#api_key").val();
    
    if (!url || !apiKey) {
        alert("Completa primero URL y API Key");
        return;
    }
    
    var btn = $("#load-states-btn");
    btn.html('<i class="icon-spinner icon-spin"></i> Cargando...').prop('disabled', true);
    
    $.ajax({
        url: SyncroSeviAdmin.adminLink + "&action=get_states",
        method: 'GET',
        data: {
            shop_url: url,
            api_key: apiKey
        },
        dataType: 'json',
        timeout: 30000,
        success: function(response) {
            if (response.success && response.states) {
                var container = $("#import-states-container");
                container.html("");
                
                response.states.forEach(function(state) {
                    var div = $("<div>")
                        .addClass("checkbox")
                        .html('<label><input type="checkbox" name="import_states[]" value="' + state.id + '"> ' + 
      state.display_name + '</label>');
                    container.append(div);
                });
                
                container.show();
                showAlert('success', 'Estados cargados: ' + response.states.length + ' encontrados');
            } else {
                showAlert('error', 'Error cargando estados: ' + (response.message || 'Error desconocido'));
            }
        },
        error: function(xhr, status, error) {
            var message = "Error de comunicación al cargar estados";
            if (status === 'timeout') {
                message += " (tiempo agotado)";
            }
            showAlert('error', message);
        },
        complete: function() {
            btn.html('<i class="icon-download"></i> Cargar Estados de la Tienda Hija').prop('disabled', false);
        }
    });
}
// Función genérica para cargar estados (para edición)
function loadStatesFromShopGeneric(url, apiKey, containerId, preselectedStates) {
    if (!url || !apiKey) {
        alert("Completa primero URL y API Key");
        return;
    }
    
    preselectedStates = preselectedStates || [];
    
    $.ajax({
        url: SyncroSeviAdmin.adminLink + "&action=get_states",
        method: 'GET',
        data: {
            shop_url: url,
            api_key: apiKey
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.states) {
                var container = $("#" + containerId);
                container.html("");
                
                response.states.forEach(function(state) {
                    var div = $("<div>").addClass("checkbox");
                    var checked = preselectedStates.indexOf(state.id.toString()) !== -1 ? 'checked' : '';
                    div.html('<label><input type="checkbox" name="import_states[]" value="' + state.id + '" ' + checked + '> ' + 
         (state.display_name || ('ID ' + state.id + ' - ' + state.name)) + '</label>');
                    container.append(div);
                });
                
                container.show();
            }
        },
        error: function() {
            alert("Error cargando estados de la tienda");
        }
    });
}

/**
 * Navegación
 */
function editShop(shopId) {
    window.location.href = SyncroSeviAdmin.adminLink + "&action=edit&id_child_shop=" + shopId + "&token=" + SyncroSeviAdmin.token;
}

function deleteShop(shopId) {
    if (confirm('¿Estás seguro de eliminar esta tienda?\n\nEsta acción no se puede deshacer y eliminará:\n- La configuración de la tienda\n- El historial de sincronización\n- Los datos de tracking')) {
        window.location.href = SyncroSeviAdmin.adminLink + "&action=delete_shop&id_child_shop=" + shopId;
    }
}

/**
 * Mostrar alertas mejoradas
 */
function showAlert(type, message, persistent) {
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
    
    var persistentClass = persistent ? ' persistent' : '';
    var alertHtml = '<div class="alert ' + alertClass + ' alert-dismissible fade-in' + persistentClass + '">' +
                   '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                   '<span aria-hidden="true">&times;</span>' +
                   '</button>' +
                   '<i class="' + icon + '"></i> ' + message +
                   '</div>';
    
    // Insertar en el primer panel-body encontrado
    var target = $('.panel-body').first();
    if (target.length === 0) {
        target = $('body');
    }
    
    target.prepend(alertHtml);
    
    // Auto-ocultar después de 8 segundos si no es persistente
    if (!persistent && type !== 'error') {
        setTimeout(function() {
            $('.alert').not('.persistent').first().fadeOut(function() {
                $(this).remove();
            });
        }, 8000);
    }
}

/**
 * Utilidades
 */
function formatDate(dateString) {
    if (!dateString) return '';
    var date = new Date(dateString);
    return date.toLocaleDateString('es-ES') + ' ' + date.toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'});
}

function formatNumber(number) {
    return new Intl.NumberFormat('es-ES').format(number);
}

// Funciones específicas para toggle
function toggleStatesLoad(show) {
    var btn = $('#load-states-btn');
    var container = $('#import-states-container');
    
    if (show) {
        btn.show();
    } else {
        btn.hide();
        container.hide().html('');
    }
}

// Inicialización cuando el DOM esté listo
$(document).ready(function() {
    console.log('SyncroSevi Admin JS cargado');
    
    // Manejar envío del formulario de añadir tienda
    $('#addShopForm').on('submit', function(e) {
        console.log('Enviando formulario addShop');
        
        // Validar campos obligatorios
        var requiredFields = ['shop_name', 'shop_url', 'api_key', 'id_customer', 'id_group', 'id_address', 'id_order_state'];
        var errors = [];
        
        requiredFields.forEach(function(field) {
            var value = $('#' + field).val();
            if (!value || value === '') {
                errors.push(field);
            }
        });
        
        if (errors.length > 0) {
            e.preventDefault();
            alert('Por favor completa los campos: ' + errors.join(', '));
            return false;
        }
        
        // Procesar import_states si están seleccionados
        var importStates = [];
        $('input[name="import_states[]"]:checked').each(function() {
            importStates.push($(this).val());
        });
        
        if (importStates.length === 0) {
            // Usar valores por defecto
            $('#default_import_states').attr('name', 'import_states');
        } else {
            // Crear input hidden con los estados seleccionados
            $('<input>').attr({
                type: 'hidden',
                name: 'import_states',
                value: importStates.join(',')
            }).appendTo('#addShopForm');
        }
    });
});