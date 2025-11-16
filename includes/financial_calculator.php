<?php
/**
 * Centralized Financial Calculator for Mitsubishi Dealership System
 * 
 * This calculator implements all 13 financial formulas from the System Revision:
 * - Formula #1: Total Unit Price
 * - Formula #2: Amount to be Invoiced
 * - Formula #3: Amount Finance
 * - Formula #4: Down Payment
 * - Formula #5: Additional Discount
 * - Formula #6: Net Down Payment
 * - Formula #7: Total Incidentals Applied
 * - Formula #8: Total Cash Outlay
 * - Formula #9: Gross Dealer's Incentives
 * - Formula #10: Net Dealer Incentive After Retain
 * - Formula #11: Expense Summary
 * - Formula #12: Gross vs. Net Summary
 * - Formula #13: Net Negative Calculation
 * 
 * Usage:
 *   require_once('includes/financial_calculator.php');
 *   $calculator = new FinancialCalculator($pdo);
 *   $result = $calculator->calculateAll($inputData);
 */

class FinancialCalculator {
    private $pdo;
    
    public function __construct($databaseConnection) {
        $this->pdo = $databaseConnection;
    }
    
    /**
     * Calculate all financial values at once
     * @param array $input - All input values from order form
     * @return array - All calculated values
     */
    public function calculateAll($input) {
        // Extract and validate inputs
        $basePrice = floatval($input['base_price'] ?? 0);
        $bodyPackage = floatval($input['body_package_price'] ?? 0);
        $airconPackage = floatval($input['aircon_package_price'] ?? 0);
        $whiteColor = floatval($input['white_color_surcharge'] ?? 0);
        $otherCharges = floatval($input['other_charges'] ?? 0);
        
        $nominalDiscount = floatval($input['nominal_discount'] ?? 0);
        $promoDiscount = floatval($input['promo_discount'] ?? 0);
        
        $financePercentage = floatval($input['finance_percentage'] ?? 0);
        $downPaymentPercentage = floatval($input['down_payment_percentage'] ?? 20);
        
        $insurancePremium = floatval($input['insurance_premium'] ?? 0);
        $cptlPremium = floatval($input['cptl_premium'] ?? 0);
        $ltoRegistration = floatval($input['lto_registration'] ?? 0);
        $chattelMortgageFee = floatval($input['chattel_mortgage_fee'] ?? 0);
        $chattelIncome = floatval($input['chattel_income'] ?? 0);
        $extendedWarranty = floatval($input['extended_warranty'] ?? 0);
        
        $reservationFee = floatval($input['reservation_fee'] ?? 0);
        
        $grossDealerIncentivePct = floatval($input['gross_dealer_incentive_pct'] ?? 0);
        $sfmRetain = floatval($input['sfm_retain'] ?? 0);
        $sfmAdditional = floatval($input['sfm_additional'] ?? 0);
        $tipsterFee = floatval($input['tipster_fee'] ?? 0);
        $accessoriesCost = floatval($input['accessories_cost'] ?? 0);
        $otherExpenses = floatval($input['other_expenses'] ?? 0);
        $seShare = floatval($input['se_share'] ?? 0);
        
        // Calculate all formulas
        $totalUnitPrice = $this->calculateTotalUnitPrice($basePrice, $bodyPackage, $airconPackage, $whiteColor, $otherCharges);
        $additionalDiscount = $this->calculateAdditionalDiscount($nominalDiscount, $promoDiscount);
        $amountToInvoice = $this->calculateAmountToInvoice($totalUnitPrice, $nominalDiscount, $promoDiscount);
        $amountFinance = $this->calculateAmountFinance($totalUnitPrice, $financePercentage);
        $downPayment = $this->calculateDownPayment($totalUnitPrice, $downPaymentPercentage);
        $netDownPayment = $this->calculateNetDownPayment($downPayment, $additionalDiscount);
        $totalIncidentals = $this->calculateTotalIncidentals($insurancePremium, $cptlPremium, $ltoRegistration, $chattelMortgageFee, $chattelIncome, $extendedWarranty);
        $totalCashOutlay = $this->calculateTotalCashOutlay($netDownPayment, $totalIncidentals, $reservationFee);
        $grossDealerIncentive = $this->calculateGrossDealerIncentive($amountFinance, $grossDealerIncentivePct);
        $netDealerIncentive = $this->calculateNetDealerIncentive($grossDealerIncentive, $sfmRetain);
        $totalExpenses = $this->calculateTotalExpenses($nominalDiscount, $promoDiscount, $sfmAdditional, $totalIncidentals, $tipsterFee, $accessoriesCost, $otherExpenses);
        $grossNetBalance = $this->calculateGrossNetBalance($grossDealerIncentive, $sfmRetain, $totalExpenses);
        $netNegative = $this->calculateNetNegative($totalExpenses, $seShare);
        
        // Return all calculated values
        return [
            // Pricing breakdown
            'total_unit_price' => $totalUnitPrice,
            'nominal_discount' => $nominalDiscount,
            'promo_discount' => $promoDiscount,
            'discount_amount' => $additionalDiscount, // For backward compatibility
            'amount_to_invoice' => $amountToInvoice,
            'total_price' => $amountToInvoice, // For backward compatibility
            
            // Financing
            'finance_percentage' => $financePercentage,
            'amount_finance' => $amountFinance,
            'down_payment_percentage' => $downPaymentPercentage,
            'down_payment' => $downPayment,
            'net_down_payment' => $netDownPayment,
            
            // Incidentals
            'insurance_premium' => $insurancePremium,
            'cptl_premium' => $cptlPremium,
            'lto_registration' => $ltoRegistration,
            'chattel_mortgage_fee' => $chattelMortgageFee,
            'chattel_income' => $chattelIncome,
            'extended_warranty' => $extendedWarranty,
            'total_incidentals' => $totalIncidentals,
            
            // Customer payment
            'reservation_fee' => $reservationFee,
            'total_cash_outlay' => $totalCashOutlay,
            
            // Dealer & expenses
            'gross_dealer_incentive_pct' => $grossDealerIncentivePct,
            'gross_dealer_incentive' => $grossDealerIncentive,
            'sfm_retain' => $sfmRetain,
            'sfm_additional' => $sfmAdditional,
            'net_dealer_incentive' => $netDealerIncentive,
            'tipster_fee' => $tipsterFee,
            'accessories_cost' => $accessoriesCost,
            'other_expenses' => $otherExpenses,
            'se_share' => $seShare,
            'total_expenses' => $totalExpenses,
            'gross_net_balance' => $grossNetBalance,
            'net_negative' => $netNegative
        ];
    }
    
    /**
     * Formula #1: Total Unit Price
     * Total Unit Price = SRP + Body + Aircon + White Color + Others
     */
    public function calculateTotalUnitPrice($basePrice, $bodyPackage, $aircon, $whiteColor, $others) {
        return $basePrice + $bodyPackage + $aircon + $whiteColor + $others;
    }

    /**
     * Formula #2: Amount to be Invoiced
     * Amount to Invoice = Total Unit Price - Nominal Discount - Promo Discount
     */
    public function calculateAmountToInvoice($totalUnitPrice, $nominalDiscount, $promoDiscount) {
        return max(0, $totalUnitPrice - $nominalDiscount - $promoDiscount);
    }

    /**
     * Formula #3: Amount Finance
     * Amount Finance = Total Unit Price × Finance %
     */
    public function calculateAmountFinance($totalUnitPrice, $financePercentage) {
        return $totalUnitPrice * ($financePercentage / 100);
    }

    /**
     * Formula #4: Down Payment
     * Down Payment = Total Unit Price × Down Payment %
     */
    public function calculateDownPayment($totalUnitPrice, $downPaymentPercentage) {
        return $totalUnitPrice * ($downPaymentPercentage / 100);
    }

    /**
     * Formula #5: Additional Discount
     * Additional Discount = Nominal Discount + Promo Discount
     */
    public function calculateAdditionalDiscount($nominalDiscount, $promoDiscount) {
        return $nominalDiscount + $promoDiscount;
    }

    /**
     * Formula #6: Net Down Payment
     * Net Down Payment = Down Payment - Additional Discount
     */
    public function calculateNetDownPayment($downPayment, $additionalDiscount) {
        return max(0, $downPayment - $additionalDiscount);
    }

    /**
     * Formula #7: Total Incidentals Applied
     * Total Incidentals = Insurance + CPTL + LTO + Chattel Mortgage Fee + Chattel Income + Extended Warranty
     */
    public function calculateTotalIncidentals($insurance, $cptl, $lto, $chattelFee, $chattelIncome, $warranty) {
        return $insurance + $cptl + $lto + $chattelFee + $chattelIncome + $warranty;
    }

    /**
     * Formula #8: Total Cash Outlay
     * Total Cash Outlay = Net Down Payment + Total Incidentals - Reservation
     */
    public function calculateTotalCashOutlay($netDownPayment, $totalIncidentals, $reservationFee) {
        return max(0, $netDownPayment + $totalIncidentals - $reservationFee);
    }

    /**
     * Formula #9: Gross Dealer's Incentives
     * Gross Dealer Incentive = Amount Finance × Dealer Incentive %
     */
    public function calculateGrossDealerIncentive($amountFinance, $incentivePercentage) {
        return $amountFinance * ($incentivePercentage / 100);
    }

    /**
     * Formula #10: Net Dealer Incentive After Retain
     * Net Dealer Incentive = Gross Dealer Incentive - SFM Retain
     */
    public function calculateNetDealerIncentive($grossIncentive, $sfmRetain) {
        return $grossIncentive - $sfmRetain;
    }

    /**
     * Formula #11: Expense Summary
     * Total Expenses = All-in Discount + SFM Add'l + Incidentals + Tipster + Accessories + Others
     */
    public function calculateTotalExpenses($nominalDiscount, $promoDiscount, $sfmAdditional, $incidentals, $tipster, $accessories, $others) {
        $allInDiscount = $nominalDiscount + $promoDiscount;
        return $allInDiscount + $sfmAdditional + $incidentals + $tipster + $accessories + $others;
    }

    /**
     * Formula #12: Gross vs. Net Summary
     * Resulting Balance = (Gross & SFM Retain Total) - (Total Expenses)
     */
    public function calculateGrossNetBalance($grossIncentive, $sfmRetain, $totalExpenses) {
        return ($grossIncentive + $sfmRetain) - $totalExpenses;
    }

    /**
     * Formula #13: Net Negative Calculation
     * Net Negative = Total Expenses + SE Share
     */
    public function calculateNetNegative($totalExpenses, $seShare) {
        return $totalExpenses + $seShare;
    }
}

