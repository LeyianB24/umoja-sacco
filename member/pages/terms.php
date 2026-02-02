<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../inc/Auth.php';
require_once __DIR__ . '/../../inc/LayoutManager.php';

$layout = LayoutManager::create('member');

// Optional: Allow guests to view terms
// require_member();

$pageTitle = "Terms of Service";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= SITE_NAME ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --font-main: 'Plus Jakarta Sans', sans-serif;
            --forest: #0F392B;
            --lime: #D0F764;
        }
        
        body {
            font-family: var(--font-main);
            background: linear-gradient(135deg, #f0f7f4 0%, #e8f5e9 100%);
            min-height: 100vh;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 40px;
            min-height: 100vh;
        }
        
        .legal-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .legal-header {
            background: linear-gradient(135deg, var(--forest) 0%, #1a4d3e 100%);
            border-radius: 24px;
            padding: 60px 50px;
            color: white;
            margin-bottom: 40px;
            box-shadow: 0 20px 40px rgba(15, 57, 43, 0.15);
            position: relative;
            overflow: hidden;
        }
        
        .legal-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(208, 247, 100, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .legal-content {
            background: white;
            border-radius: 24px;
            padding: 50px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .legal-content h2 {
            color: var(--forest);
            font-weight: 800;
            margin-top: 40px;
            margin-bottom: 20px;
            font-size: 1.75rem;
        }
        
        .legal-content h2:first-child {
            margin-top: 0;
        }
        
        .legal-content h3 {
            color: var(--forest);
            font-weight: 700;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 1.25rem;
        }
        
        .legal-content p {
            color: #475569;
            line-height: 1.8;
            margin-bottom: 20px;
        }
        
        .legal-content ul, .legal-content ol {
            color: #475569;
            line-height: 1.8;
            margin-bottom: 20px;
            padding-left: 30px;
        }
        
        .legal-content li {
            margin-bottom: 10px;
        }
        
        .highlight-box {
            background: rgba(208, 247, 100, 0.1);
            border-left: 4px solid var(--lime);
            padding: 20px;
            border-radius: 12px;
            margin: 30px 0;
        }
        
        .last-updated {
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 12px;
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 30px;
        }
        
        @media (max-width: 991px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .legal-header {
                padding: 40px 30px;
            }
            
            .legal-content {
                padding: 30px 25px;
            }
        }
    </style>
</head>
<body>

<?php $layout->sidebar(); ?>

<div class="main-content">
    <?php $layout->topbar($pageTitle); ?>
    
    <div class="legal-container">
        <div class="legal-header">
            <div class="position-relative z-1">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="bg-white bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-file-text fs-3"></i>
                    </div>
                    <div>
                        <h1 class="mb-0 fw-bold">Terms of Service</h1>
                        <p class="mb-0 opacity-75">Umoja Drivers Sacco Membership Agreement</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="last-updated">
            <i class="bi bi-calendar-check me-2"></i>
            <strong>Last Updated:</strong> February 1, 2026
        </div>
        
        <div class="legal-content">
            <h2>1. Acceptance of Terms</h2>
            <p>
                By registering as a member of Umoja Drivers Sacco ("the Sacco"), you agree to be bound by these Terms of Service, 
                all applicable laws and regulations, and agree that you are responsible for compliance with any applicable local laws.
            </p>
            
            <div class="highlight-box">
                <strong><i class="bi bi-info-circle me-2"></i>Important:</strong> 
                If you do not agree with any of these terms, you are prohibited from using or accessing this platform and 
                participating in Sacco activities.
            </div>
            
            <h2>2. Membership Eligibility</h2>
            <p>To become a member of Umoja Drivers Sacco, you must:</p>
            <ul>
                <li>Be at least 18 years of age</li>
                <li>Be a professional driver or involved in the transport industry</li>
                <li>Provide valid identification documents (National ID or Passport)</li>
                <li>Pay the mandatory registration fee of KES 1,000</li>
                <li>Maintain active membership through regular contributions</li>
            </ul>
            
            <h2>3. Member Contributions</h2>
            
            <h3>3.1 Savings Contributions</h3>
            <p>
                Members are required to make regular savings contributions as determined by the Sacco's bylaws. 
                A minimum balance of KES 500 must be maintained in your savings account at all times.
            </p>
            
            <h3>3.2 Share Capital</h3>
            <p>
                Share capital contributions represent your ownership stake in the Sacco. The current share price is KES 100 per unit. 
                Share capital is non-withdrawable except upon membership exit or as determined by the Annual General Meeting (AGM).
            </p>
            
            <h3>3.3 Welfare Fund</h3>
            <p>
                Welfare contributions are pooled to provide financial support to members during emergencies, illness, or bereavement. 
                Welfare funds are only accessible through approved support cases.
            </p>
            
            <h2>4. Loan Services</h2>
            
            <h3>4.1 Loan Eligibility</h3>
            <p>Members may apply for loans subject to the following conditions:</p>
            <ul>
                <li>Minimum 6 months of active membership</li>
                <li>Loan limit is 3 times your total savings balance</li>
                <li>All previous loans must be fully repaid</li>
                <li>Provision of acceptable guarantors as required</li>
            </ul>
            
            <h3>4.2 Loan Repayment</h3>
            <p>
                Loans must be repaid according to the agreed schedule. Failure to repay may result in:
            </p>
            <ul>
                <li>Suspension of borrowing privileges</li>
                <li>Recovery action against guarantors</li>
                <li>Legal action for debt recovery</li>
                <li>Membership suspension or termination</li>
            </ul>
            
            <h2>5. Account Security</h2>
            <p>You are responsible for maintaining the confidentiality of your account credentials. You agree to:</p>
            <ul>
                <li>Keep your password secure and not share it with others</li>
                <li>Notify the Sacco immediately of any unauthorized access</li>
                <li>Accept responsibility for all activities under your account</li>
                <li>Use strong, unique passwords and change them regularly</li>
            </ul>
            
            <h2>6. Platform Usage</h2>
            
            <h3>6.1 Acceptable Use</h3>
            <p>You agree to use the Sacco platform only for lawful purposes. Prohibited activities include:</p>
            <ul>
                <li>Attempting to gain unauthorized access to any part of the system</li>
                <li>Interfering with the proper functioning of the platform</li>
                <li>Uploading malicious code or viruses</li>
                <li>Misrepresenting your identity or affiliation</li>
                <li>Using the platform for fraudulent activities</li>
            </ul>
            
            <h3>6.2 Mobile Money Transactions</h3>
            <p>
                The Sacco integrates with M-Pesa and other mobile money platforms for convenience. You acknowledge that:
            </p>
            <ul>
                <li>Transaction fees may apply as per the service provider's rates</li>
                <li>The Sacco is not responsible for mobile money service outages</li>
                <li>You must ensure sufficient funds in your mobile wallet for transactions</li>
                <li>All transactions are subject to verification and may be delayed</li>
            </ul>
            
            <h2>7. Dividends and Returns</h2>
            <p>
                Dividends on share capital are declared annually based on the Sacco's financial performance and are subject to:
            </p>
            <ul>
                <li>Approval by the Annual General Meeting</li>
                <li>Deduction of applicable taxes</li>
                <li>Retention of reserves as required by law</li>
                <li>Distribution proportional to share capital held</li>
            </ul>
            
            <h2>8. Membership Termination</h2>
            
            <h3>8.1 Voluntary Exit</h3>
            <p>
                Members may voluntarily exit the Sacco by submitting a written notice. Upon exit:
            </p>
            <ul>
                <li>All outstanding loans must be fully repaid</li>
                <li>Savings and share capital will be refunded after clearance</li>
                <li>A processing period of up to 90 days may apply</li>
                <li>Exit fees may be deducted as per Sacco bylaws</li>
            </ul>
            
            <h3>8.2 Involuntary Termination</h3>
            <p>The Sacco reserves the right to terminate membership for:</p>
            <ul>
                <li>Violation of these Terms of Service</li>
                <li>Fraudulent activities or misrepresentation</li>
                <li>Failure to meet membership obligations</li>
                <li>Conduct detrimental to the Sacco's interests</li>
            </ul>
            
            <h2>9. Limitation of Liability</h2>
            <p>
                The Sacco shall not be liable for any indirect, incidental, special, consequential, or punitive damages 
                resulting from your use of the platform or services. This includes, but is not limited to:
            </p>
            <ul>
                <li>Loss of profits or savings</li>
                <li>Service interruptions or data loss</li>
                <li>Third-party service failures (e.g., M-Pesa outages)</li>
                <li>Unauthorized access to your account due to your negligence</li>
            </ul>
            
            <h2>10. Dispute Resolution</h2>
            <p>
                Any disputes arising from these terms shall be resolved through:
            </p>
            <ol>
                <li>Internal mediation by the Sacco's Dispute Resolution Committee</li>
                <li>Arbitration as per the Kenyan Arbitration Act</li>
                <li>Legal proceedings in Kenyan courts as a last resort</li>
            </ol>
            
            <h2>11. Amendments</h2>
            <p>
                The Sacco reserves the right to modify these Terms of Service at any time. Members will be notified of 
                significant changes via email or platform notifications. Continued use of the platform after changes 
                constitutes acceptance of the modified terms.
            </p>
            
            <h2>12. Governing Law</h2>
            <p>
                These Terms of Service are governed by and construed in accordance with the laws of the Republic of Kenya, 
                including the Sacco Societies Act and regulations issued by the Sacco Societies Regulatory Authority (SASRA).
            </p>
            
            <h2>13. Contact Information</h2>
            <p>
                For questions or concerns regarding these Terms of Service, please contact:
            </p>
            <div class="highlight-box">
                <strong>Umoja Drivers Sacco</strong><br>
                Email: <a href="mailto:<?= defined('COMPANY_EMAIL') ? COMPANY_EMAIL : 'info@umojadriversacco.co.ke' ?>"><?= defined('COMPANY_EMAIL') ? COMPANY_EMAIL : 'info@umojadriversacco.co.ke' ?></a><br>
                Phone: <?= defined('COMPANY_PHONE') ? COMPANY_PHONE : '+254 700 000 000' ?><br>
                Address: <?= defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : 'Nairobi, Kenya' ?>
            </div>
            
            <div class="text-center mt-5 pt-4 border-top">
                <p class="text-muted small mb-0">
                    By using this platform, you acknowledge that you have read, understood, and agree to be bound by these Terms of Service.
                </p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
