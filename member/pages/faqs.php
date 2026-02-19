<?php
session_start();
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

// Check authentication
require_member();

$pageTitle = "Frequently Asked Questions";
$layout = LayoutManager::create('member');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="/usms/public/assets/css/darkmode.css">
    <script>(function(){const s=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',s);})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= SITE_NAME ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= ASSET_BASE ?>/css/style.css?v=<?= time() ?>">
    
    <style>
        :root {
            --forest-deep: #0f2e25;
            --forest-light: #1a4d3d;
            --lime-vibrant: #d0f35d;
            --lime-dark: #a8cf12;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8f9fa;
        }
        .accordion-button:not(.collapsed) {
            color: var(--forest-deep);
            background-color: rgba(208, 243, 93, 0.2);
            box-shadow: inset 0 -1px 0 rgba(0,0,0,.125);
        }
        .accordion-button:focus {
            border-color: var(--lime-vibrant);
            box-shadow: 0 0 0 0.25rem rgba(208, 243, 93, 0.25);
        }
        .accordion-button::after {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%230f2e25'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
        }
        .faq-header {
            background: linear-gradient(135deg, var(--forest-deep) 0%, var(--forest-light) 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 30px 30px;
        }
    </style>

    <?php require_once 'C:/xampp/htdocs/usms/inc/dark_mode_loader.php'; ?>
</head>
<body>


   <div class="d-flex">
        <?php $layout->sidebar(); ?>

        <div class="flex-fill main-content-wrapper" style="margin-left: 280px; transition: margin-left 0.3s ease;">
            
            <?php $layout->topbar($pageTitle ?? ''); ?>

        <!-- FAQ Header -->
        <div class="faq-header text-center animate-fade-in">
            <h1 class="fw-bold mb-2">How can we help you?</h1>
            <p class="lead opacity-75">Find answers to common questions about your Sacco membership.</p>
            <div class="mt-4">
                <a href="support.php" class="btn btn-lime text-forest fw-bold rounded-pill px-4 py-2">
                    <i class="bi bi-headset me-2"></i>Contact Support
                </a>
            </div>
        </div>

        <div class="container pb-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    
                    <!-- Section: Membership & Account -->
                    <h5 class="fw-bold text-forest mb-3 mt-4"><i class="bi bi-person-badge me-2"></i>Membership & Account</h5>
                    <div class="accordion shadow-sm rounded-3 overflow-hidden mb-4" id="accordionMembership">
                        <div class="accordion-item border-0 border-bottom">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                    How do I update my profile details?
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#accordionMembership">
                                <div class="accordion-body text-muted">
                                    You can update your personal information, including phone number and email, directly from the <a href="profile.php" class="text-success fw-bold">Profile Page</a>. For sensitive changes like Name or ID number, please contact support with valid documentation.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                    Is my data secure?
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionMembership">
                                <div class="accordion-body text-muted">
                                    Yes, absolutely. We use industry-standard encryption protocols (SSL) to protect your data in transit and at rest. Your password is hashed, and we never share your personal information with third parties without your consent.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Loans & Finance -->
                    <h5 class="fw-bold text-forest mb-3 mt-4"><i class="bi bi-cash-coin me-2"></i>Loans & Finance</h5>
                    <div class="accordion shadow-sm rounded-3 overflow-hidden mb-4" id="accordionLoans">
                        <div class="accordion-item border-0 border-bottom">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLoan1">
                                    How is my loan limit calculated?
                                </button>
                            </h2>
                            <div id="collapseLoan1" class="accordion-collapse collapse" data-bs-parent="#accordionLoans">
                                <div class="accordion-body text-muted">
                                    Your loan limit is primarily based on your <strong>Savings Balance</strong> (multiplied by 3 or 4 depending on the product) and your <strong>Share Capital</strong>. Consistent savings and a good repayment history can increase your eligibility.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 border-bottom">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLoan2">
                                    How long does loan processing take?
                                </button>
                            </h2>
                            <div id="collapseLoan2" class="accordion-collapse collapse" data-bs-parent="#accordionLoans">
                                <div class="accordion-body text-muted">
                                    Instant mobile loans are processed immediately. Development and emergency loans are typically reviewed and approved within <strong>24-48 hours</strong>, subject to guarantor confirmation.
                                </div>
                            </div>
                        </div>
                         <div class="accordion-item border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLoan3">
                                    Can I pay via M-Pesa?
                                </button>
                            </h2>
                            <div id="collapseLoan3" class="accordion-collapse collapse" data-bs-parent="#accordionLoans">
                                <div class="accordion-body text-muted">
                                    Yes! All repayments and deposits can be made via our M-Pesa Paybill. The system automatically updates your statement once the transaction is received.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Welfare -->
                    <h5 class="fw-bold text-forest mb-3 mt-4"><i class="bi bi-heart-pulse me-2"></i>Welfare & Benevolence</h5>
                    <div class="accordion shadow-sm rounded-3 overflow-hidden mb-5" id="accordionWelfare">
                        <div class="accordion-item border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseWelfare1">
                                    What is covered under the Welfare Fund?
                                </button>
                            </h2>
                            <div id="collapseWelfare1" class="accordion-collapse collapse" data-bs-parent="#accordionWelfare">
                                <div class="accordion-body text-muted">
                                    The Welfare Fund supports members during significant life events such as hospitalization, bereavement (member or immediate family), and other emergencies as defined in the Sacco by-laws. To claim, submit a request via the <a href="welfare.php" class="text-success fw-bold">Welfare Page</a>.
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <?php $layout->footer(); ?>
        </div>
    </div>
</div>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= ASSET_BASE ?>/js/main.js"></script>

</body>
</html>
