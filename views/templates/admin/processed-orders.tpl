{*
* 2024 SyncroSevi - Template de Pedidos Procesados
*}

<div class="syncrosevi-panel">
    <div class="panel panel-info">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="icon-list-alt"></i> Pedidos Procesados
                <a href="{$admin_link}&token={$token}" class="btn btn-default btn-sm pull-right">
                    <i class="icon-arrow-left"></i> Volver al Panel Principal
                </a>
            </h3>
        </div>
        <div class="panel-body">
            
            <div id="orders-loading" style="text-align: center; padding: 50px;">
                <i class="icon-spinner icon-spin" style="font-size: 24px;"></i>
                <p>Cargando pedidos procesados...</p>
            </div>
            
            <div id="orders-error" style="display: none;">
                <div class="alert alert-danger">
                    <i class="icon-exclamation-triangle"></i>
                    <strong>Error:</strong> <span class="error-message"></span>
                </div>
            </div>
            
            <div id="orders-table-container" style="display: none;">
                <div class="table-responsive">
                    <table id="orders-table" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID Pedido</th>
                                <th>Referencia</th>
                                <th>Tienda Origen</th>
                                <th>Cliente</th>
                                <th>Pedidos Orig.</th>
                                <th>Productos</th>
                                <th>Total</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
                
                <div id="pagination-container"></div>
                <div id="pagination-info"></div>
            </div>
            
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    if (typeof ProcessedOrders !== 'undefined') {
        ProcessedOrders.init({
            adminLink: '{$admin_link}',
            token: '{$token}'
        });
    } else {
        // Fallback simple si no está disponible ProcessedOrders
        $('#orders-loading').hide();
        $('#orders-error .error-message').text('El módulo JavaScript de pedidos procesados no está disponible');
        $('#orders-error').show();
    }
});
</script>