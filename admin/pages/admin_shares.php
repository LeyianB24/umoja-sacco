require_once '../../inc/LayoutManager.php';

Auth::requireAdmin();
$layout = LayoutManager::create('admin');
require_permission();

$pageTitle = "Equity & Share Management";
$svEngine = new ShareValuationEngine($conn);
$valuation = $svEngine->getValuation();

// Handle Dividend Distribution POST
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'distribute_dividend') {
    $pool = (float)$_POST['dividend_pool'];
    $ref = "DIV-" . strtoupper(uniqid());
    
    if ($pool > 0) {
        try {
            if ($svEngine->distributeDividends($pool, $ref)) {
                $msg = "<div class='alert alert-success'>Successfully distributed KES " . number_format($pool, 2) . " across all shareholders.</div>";
                // Refresh valuation
                $valuation = $svEngine->getValuation();
            }
        } catch (Exception $e) {
            $msg = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

// Fetch Top Shareholders
$sqlTop = "SELECT m.full_name, ms.units_owned, ms.total_amount_paid, (ms.units_owned / ?) * 100 as ownership_pct 
           FROM member_shareholdings ms 
           JOIN members m ON ms.member_id = m.member_id 
           WHERE ms.units_owned > 0 
           ORDER BY ms.units_owned DESC LIMIT 10";
$stmt = $conn->prepare($sqlTop);
$totalU = (float)$valuation['total_units'] ?: 1;
$stmt->bind_param("d", $totalU);
$stmt->execute();
$topHolders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
<?php $layout->header($pageTitle); ?>
    <style>
        .main-content-wrapper { margin-left: 280px; transition: 0.3s; min-height: 100vh; padding: 2.5rem; background: #f0f4f3; }
        @media (max-width: 991px) { .main-content-wrapper { margin-left: 0; padding: 1.5rem; } }
        
        .equity-hero { 
            background: linear-gradient(135deg, var(--forest) 0%, var(--forest-mid) 100%); 
            border-radius: 30px; padding: 40px; color: white; margin-bottom: 30px;
        }
        .glass-card { 
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
        }
    </style>

<div class="d-flex">
    <?php $layout->sidebar(); ?>
    <div class="flex-fill main-content-wrapper">
        <?php $layout->topbar($pageTitle ?? ''); ?>

        <div class="container-fluid py-4">
            <?= $msg ?>
            
            <div class="row g-4 mb-4">
                <!-- NAV Card -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-body p-4 bg-primary text-white">
                            <h6 class="text-uppercase small fw-bold opacity-75">Sacco Net Asset Value (NAV)</h6>
                            <h2 class="fw-bold mb-0">KES <?= number_format($valuation['equity'], 2) ?></h2>
                            <hr class="opacity-10 my-3">
                            <div class="d-flex justify-content-between">
                                <span>Corporate Net Worth</span>
                                <i class="bi bi-bank fs-4 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Unit Price Card -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body p-4">
                            <h6 class="text-uppercase text-muted small fw-bold mb-3">Current Unit Price</h6>
                            <h2 class="fw-bold mb-1" style="color: var(--accent-green);">KES <?= number_format($valuation['price'], 2) ?></h2>
                            <p class="text-muted small mb-0">Calculated based on <?= number_format($valuation['total_units'], 2) ?> issued units.</p>
                        </div>
                    </div>
                </div>

                <!-- Dividend Action Card -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-light border-start border-4 border-success">
                        <div class="card-body p-4">
                            <h6 class="text-uppercase text-muted small fw-bold mb-3">Reward Shareholders</h6>
                            <form method="POST">
                                <input type="hidden" name="action" value="distribute_dividend">
                                <div class="input-group mb-3">
                                    <span class="input-group-text bg-white border-end-0">KES</span>
                                    <input type="number" step="0.01" name="dividend_pool" class="form-control border-start-0" placeholder="Total Pool Amount" required>
                                    <button class="btn btn-success px-4" type="submit">Pay</button>
                                </div>
                                <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i> Proportional payout to all unit holders.</p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="fw-bold mb-0">Top Shareholders</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4">Shareholder</th>
                                            <th>Units Owned</th>
                                            <th>Equity Value</th>
                                            <th class="text-end pe-4">Ownership %</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topHolders as $holder): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold"><?= htmlspecialchars($holder['full_name']) ?></div>
                                            </td>
                                            <td><?= number_format($holder['units_owned'], 4) ?></td>
                                            <td>KES <?= number_format($holder['units_owned'] * $valuation['price'], 2) ?></td>
                                            <td class="text-end pe-4">
                                                <span class="badge bg-primary-subtle text-primary border-0"><?= number_format($holder['ownership_pct'], 2) ?>%</span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="fw-bold mb-0">Valuation Ledger</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-3 pb-2 border-bottom">
                                <span class="text-muted">Total Assets</span>
                                <span class="fw-bold text-success">KES <?= number_format($valuation['total_assets'], 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-3 pb-2 border-bottom">
                                <span class="text-muted">Member Liabilities</span>
                                <span class="fw-bold text-danger">KES <?= number_format($valuation['liabilities'], 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mt-4">
                                <span class="fw-bold h5">Corporate Equity</span>
                                <span class="fw-bold h5 text-primary">KES <?= number_format($valuation['equity'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php $layout->footer(); ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
