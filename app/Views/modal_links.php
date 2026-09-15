<!-- Modal Links -->
<div class="modal fade" id="modalLinks" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-link-45deg"></i> Links Úteis</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action" onclick="abrirPowerBI(); return false;">
                        <i class="bi bi-bar-chart-fill text-primary"></i> Power BI - Dashboard
                    </a>
                    <a href="https://dnitgov.sharepoint.com/:b:/s/COAC-contratos-Oramento/EZY5bMtHGKtDiYtuFRUYcDEBZMqwYF6XCOk4h3_ge5J2hA?e=9UHDoC" target="_blank" class="list-group-item list-group-item-action">
                        <i class="bi bi-file-earmark-text text-success"></i> Manual de Contratos
                    </a>
                    <a href="https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/cib/dataList?dataset_id=26" target="_blank" class="list-group-item list-group-item-action">
                        <i class="bi bi-database text-warning"></i> Dados RDCI
                    </a>
                    <a href="https://supra.dnit.gov.br/index_cgcont_common.php/cgcont/atlasCgcontInterno" target="_blank" class="list-group-item list-group-item-action">
                        <i class="bi bi-globe text-info"></i> Atlas CGCont
                    </a>
                    <a href="https://dnitgov.sharepoint.com/:x:/s/COAC-contratos/IQBTU_dXm9akT5aVULitBUOwAfutn9yWpyTdDPwqhK_6fC0?e=pKlUiY" target="_blank" class="list-group-item list-group-item-action">
                        <i class="bi bi-table text-secondary"></i> Planilha de Contratos
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function abrirPowerBI() {
    var modal = bootstrap.Modal.getInstance(document.getElementById('modalLinks'));
    if (modal) modal.hide();
    window.location.href = 'links_powerbi';
}
</script>