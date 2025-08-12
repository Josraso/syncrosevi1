/**
 * 2024 SyncroSevi - JavaScript para edición de tiendas
 */

// Variables globales
var customers_cache = [];
var addresses_all = [];
var adminLink = '';
var import_states_array = [];

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    console.log('SyncroSevi edit-shop.js cargado');
    
    // Inicializar transportista al cargar
    var useCarrierChecked = document.querySelector('input[name="use_carrier"]:checked');
    if (useCarrierChecked) {
        toggleTransportEdit(useCarrierChecked.value == '1');
    }
});

// ====== FUNCIONES ESPECÍFICAS DE EDICIÓN ======
function toggleTransportEdit(show) {
    var container = document.getElementById('edit-transport-options');
    container.style.display = show ? 'block' : 'none';
    if (!show && container.querySelector('select')) {
        container.querySelector('select').value = '';
    }
}

function searchCustomersEdit(query) {
    searchCustomers(query, 'edit-customer-results', 'edit-selected-customer-id');
}

function loadStatesFromShopEdit() {
    var url = document.getElementById('edit_shop_url').value;
    var apiKey = document.getElementById('edit_api_key').value;
    loadStatesFromShopGeneric(url, apiKey, 'edit-import-states-container');
}

function loadStatesFromShopGeneric(url, apiKey, containerId) {
    if (!url || !apiKey) {
        alert('Completa primero URL y API Key');
        return;
    }
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', adminLink + '&action=get_states&shop_url=' + encodeURIComponent(url) + '&api_key=' + encodeURIComponent(apiKey), true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    var container = document.getElementById(containerId);
                    container.innerHTML = '';
                    
                    response.states.forEach(function(state) {
                        var div = document.createElement('div');
                        div.className = 'checkbox';
                        var checked = import_states_array.indexOf(state.id.toString()) !== -1 ? 'checked' : '';
                        div.innerHTML = '<label><input type="checkbox" name="import_states[]" value="' + state.id + '" ' + checked + '> ID ' + state.id + ': ' + state.name + '</label>';
                        container.appendChild(div);
                    });
                    
                    container.style.display = 'block';
                }
            } catch(e) {
                alert('Error procesando respuesta');
            }
        }
    };
    xhr.send();
}

// Reutilizar funciones del admin.js principal
function searchCustomers(query, resultId, customerId) {
    if (window.parent && window.parent.searchCustomers) {
        return window.parent.searchCustomers(query, resultId, customerId);
    }
    
    // Implementación local si no está disponible
    if (query.length < 2) {
        document.getElementById(resultId).style.display = 'none';
        return;
    }
    
    var filtered = customers_cache.filter(function(c) {
        return (c.firstname + ' ' + c.lastname + ' ' + c.email).toLowerCase().indexOf(query.toLowerCase()) !== -1;
    });
    
    showCustomerResults(filtered.slice(0, 10), resultId, customerId);
}

function showCustomerResults(customers, resultId, customerId) {
    var resultsDiv = document.getElementById(resultId);
    resultsDiv.innerHTML = '';
    
    customers.forEach(function(customer) {
        var div = document.createElement('div');
        div.className = 'customer-item';
        div.innerHTML = '<strong>' + customer.firstname + ' ' + customer.lastname + '</strong><br><small>' + customer.email + '</small>';
        div.onclick = function() { selectCustomer(customer, resultId, customerId); };
        resultsDiv.appendChild(div);
    });
    
    resultsDiv.style.display = customers.length > 0 ? 'block' : 'none';
}

function selectCustomer(customer, resultId, customerId) {
    var searchField = document.getElementById(resultId.replace('-results', '-search'));
    searchField.value = customer.firstname + ' ' + customer.lastname;
    document.getElementById(customerId).value = customer.id_customer;
    document.getElementById(resultId).style.display = 'none';
    loadCustomerAddresses(customer.id_customer, resultId.replace('customer-results', 'id_address'));
}

function loadCustomerAddresses(customerId, addressSelectId) {
    var customerAddresses = addresses_all.filter(function(addr) {
        return addr.id_customer == customerId;
    });
    populateAddresses(customerAddresses, addressSelectId);
}

function populateAddresses(addresses, selectId) {
    var select = document.getElementById(selectId);
    select.innerHTML = '<option value="">Seleccionar dirección...</option>';
    
    addresses.forEach(function(address) {
        var option = document.createElement('option');
        option.value = address.id_address;
        option.textContent = address.alias + ' - ' + address.address1 + ', ' + address.city + ' (' + address.postcode + ')';
        select.appendChild(option);
    });
    
    if (addresses.length === 0) {
        select.innerHTML = '<option value="">Este cliente no tiene direcciones</option>';
    }
}

// ====== FUNCIONES UTILITARIAS ======
function initializeEditShop(customersData, addressesData, adminLinkUrl, importStatesData) {
    customers_cache = customersData;
    addresses_all = addressesData;
    adminLink = adminLinkUrl;
    import_states_array = importStatesData;
    console.log('Edit shop inicializado');
}