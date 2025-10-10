<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

$stmt = $connect->prepare("SELECT * FROM accounts WHERE Id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$displayName = !empty($user['FirstName']) ? $user['FirstName'] : $user['Username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requirements Guide - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
        /* Common styles */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', sans-serif; }

        body { background: #ffffff; 
            min-height: 100vh; 
            color: white; }

        .header { background: #ffffff; 
            padding: 20px 30px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            backdrop-filter: blur(20px); 
            border-bottom: 3px solid #b30000; }

        .logo-section { display: flex; align-items: center; gap: 20px; }

        .logo { width: 60px; }

        .brand-text { font-size: 1.4rem; 
            font-weight: 700; background: 
            linear-gradient(45deg, #d60000, #b30000); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; }

        .user-section { display: flex; align-items: center; gap: 20px; }

        .user-avatar { width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            background: linear-gradient(45deg, #d60000, #b30000); 
            display: flex; align-items: center; 
            justify-content: center; 
            font-weight: bold; 
            color: #ffffff; font-size: 1.2rem; }

        .welcome-text { font-size: 1rem;
            color: #000000
         }

        .logout-btn { background: linear-gradient(45deg, #d60000, #b30000); 
            color: white; 
            border: none; 
            padding: 12px 50px; 
            border-radius: 25px; 
            cursor: pointer; 
            font-size: 0.9rem; 
            font-weight: 600; 
            transition: all 0.3s ease; }

        .container { max-width: 1000px; 
            margin: 0 auto; 
            padding: 50px 30px; }

        .back-btn { display: inline-block; 
            margin-bottom: 30px; 
            background: linear-gradient(45deg, #d60000, #b30000); 
            color: #ffffff; padding: 10px 20px; 
            border-radius: 10px; text-decoration: none; 
            font-weight: 600; 
            transition: all 0.3s ease; }

        .back-btn:hover { background: #ffd700; color: #1a1a1a; }

        .page-title { text-align: center; 
            font-size: 2.8rem; color: #b30000; 
            margin-bottom: 40px; }

        /* Requirements Guide Styles */
        .requirements-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .intro-text {
            text-align: center;
            font-size: 1.2rem;
            margin-bottom: 40px;
            opacity: 0.9;
            line-height: 1.6;
            color: #020202ff
        }
        .requirements-card {
            background: #4A4A4A;
            border-radius: 15px;
            border: 2px solid rgba(253, 253, 253, 0.67);
            max-width: 1000px;
            overflow: hidden;
            margin-bottom: 30px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 15px rgb(0, 0, 0, 0.2);

              
 
        }
        .requirements-card:hover {
            border-color: rgba(255, 255, 255, 0.73);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .card-header {
            background: rgba(77, 77, 77, 0.62);
            padding: 20px 30px;
            border-bottom: 1px solid rgba(255,215,0,0.1);
        }
        .card-header h2 {
            font-size: 1.8rem;
            color: #ffffff;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .card-header i {
            font-size: 2rem;
            color: #ffffff;
        }
        .card-content {
            padding: 30px;
            background: #ffffff;
        }
        .requirements-table {
            width: 100%;
            border-collapse: collapse;
            color: #585757ff;
        }
        .requirements-table th {
            background: rgba(255,215,0,0.1);
            color: #4d4a4aff;
            padding: 15px;
            text-align: left;
            font-weight: 700;
            font-size: 1.1rem;
            border-bottom: 2px solid rgba(255,215,0,0.2);
        }
        .requirements-table td {
            color: #464444ff;
            padding: 12px 15px;
           
            border-bottom: 1px solid rgba(255, 255, 255, 0.54);
            
        }
        .requirements-table tr:hover {
            background: rgba(255,215,0,0.05);
        }
        .req-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .req-item i {
            color: #000000;
            margin-top: 3px;
            font-size: 1.1rem;
        }
        .req-item-content {
            flex: 1;
        }
        .req-item-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .req-item-desc {
            font-size: 0.9rem;
            opacity: 0.8;
            line-height: 1.4;
        }
        .highlight-note {
            background: #b4b2a3e5;
            border-left: 4px solid #70706eb6;
            opacity: 0.8;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 10px 10px 0;
        }
        .highlight-note h4 {
            color: #e60013e1;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        .highlight-note p {
            margin: 0;
            line-height: 1.6;
            color: #000000;
        }

        /* Custom Scrollbar Styles */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(255, 215, 0, 0.3);
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 215, 0, 0.5);
        }
        ::-webkit-scrollbar-corner {
            background: rgba(255, 255, 255, 0.05);
        }

        /* Firefox Scrollbar */
        * {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 215, 0, 0.3) rgba(255, 255, 255, 0.05);
        }

        @media (max-width: 768px) {
            .container { padding: 30px 20px; }
            .page-title { font-size: 2.2rem; }
            .card-header { padding: 15px 20px; }
            .card-header h2 { font-size: 1.4rem; }
            .card-content { padding: 20px; }
            .requirements-table th,
            .requirements-table td { padding: 12px; }
            .intro-text { font-size: 1rem; }
        }

            /* Tablet */
    @media (max-width: 1024px) {
        .container {
            max-width: 95%;
        }
    }

    /* Phones */
    @media (max-width: 768px) {
        .header {
            flex-direction: column;
            gap: 15px;
            padding: 15px 20px;
        }

        .user-section {
            flex-direction: column;
            gap: 12px;
            text-align: center;
            width: 100%;
        }

        .container {
            padding: 20px 15px;
        }

        .form-container {
            padding: 20px;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Large Desktops */
    @media (min-width: 1200px) {
        .container {
            max-width: 1100px;
        }

        .inquiry-card {
            max-width: 100%;
        }

        .form-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    </style>

</head>
<body>
    <header class="header">
        <div class="logo-section">
            <img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo">
            <div class="brand-text">MITSUBISHI MOTORS</div>
        </div>
        <div class="user-section">
            <div class="user-avatar"><?php echo strtoupper(substr($displayName, 0, 1)); ?></div>
            <span class="welcome-text">Welcome, <?php echo htmlspecialchars($displayName); ?>!</span>
            <button class="logout-btn" onclick="window.location.href='logout.php'"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </div>
    </header>

    <div class="container">
        <a href="customer.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <h1 class="page-title">Requirements Guide</h1>
        
        <div class="requirements-container">
            <p class="intro-text">The following requirements are also necessary if you want to visit the branch directly to inquire about cars.</p>
            
            <div class="requirements-card">
                <div class="card-header">
                    <h2><i class="fas fa-money-check-alt"></i> Loan Requirements</h2>
                </div>
                <div class="card-content">
                    <table class="requirements-table">
                        <tbody>
                            <tr>
                                <td>
                                    <div class="req-item">
                                        <i class="fas fa-briefcase"></i>
                                        <div class="req-item-content">
                                            <div class="req-item-title">EMPLOYED</div>
                                            <div class="req-item-desc">Two (2) Valid ID's (GOV'T ISSUED)</div>
                                            <div class="req-item-desc">COEC or Three (3) Months Latest Payslip</div>
                                            <div class="req-item-desc">ITR (2316)</div>
                                            <div class="req-item-desc">Proof Of Billing (ORIGINAL)</div>
                                            <div class="req-item-desc">ADA/PDC</div>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <div class="req-item">
                                        <i class="fas fa-building"></i>
                                        <div class="req-item-content">
                                            <div class="req-item-title">BUSINESS</div>
                                            <div class="req-item-desc">Two (2) Valid ID's (GOV'T ISSUES)</div>
                                            <div class="req-item-desc">Bank Statement (LATEST 3 MONTHS)</div>
                                            <div class="req-item-desc">ITR(1701)</div>
                                            <div class="req-item-desc">DTI Permit</div>
                                            <div class="req-item-desc">Proof of Billing (ORIGINAL)</div>
                                            <div class="req-item-desc">ADA/PDC</div>
                                        </div>
                                    </div>
                                </td>
                            </tr>


                                <td>
                                    <div class="req-item">
                                        <i class="fas fa-earth-asia"></i>
                                        <div class="req-item-content">
                                            <div class="req-item-title">OFW</div>
                                            <div class="req-item-desc">Two (2) Valid ID's (GOV'T ISSUES)</div>
                                            <div class="req-item-desc">Proof Of Remittance (LATEST 3 MONTHS)</div>
                                            <div class="req-item-desc">Latest Contract</div>
                                            <div class="req-item-desc">SPA</div>
                                            <div class="req-item-desc">Proof Of Billing (ORIGINAL)</div>
                                            <div class="req-item-desc">ADA/PDC</div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
 
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="highlight-note">
                <h4><i class="fas fa-info-circle"></i> Important Note</h4>
                <p>Please ensure all documents are valid and up-to-date. Photocopies should be clear and readable. For employed individuals, additional employment verification documents may be required depending on your chosen financing option.</p>
            </div>

            <div class="highlight-note">
                <h4><i class="fas fa-phone"></i> Need Help?</h4>
                <p>If you have questions about specific requirements or need clarification, please contact our customer service team or visit our Help Center for more detailed information.</p>
            </div>
        </div>
    </div>
</body>
</html>
                                      