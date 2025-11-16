# 🚗 MITSUBISHI DEALERSHIP SYSTEM - REVISION IMPLEMENTATION GUIDE

## 📋 OVERVIEW
Your client wants to implement a comprehensive financial calculation system for vehicle sales. This document explains **WHERE** and **HOW** to implement each formula from the System Revision Report.

**Document Purpose:** This guide maps the 13 financial formulas from your client's revision document to specific files, database tables, and code sections in your existing Mitsubishi dealership system.

---

## 🎯 WHAT NEEDS TO BE CHANGED

The revision document describes **13 financial formulas** that need to be implemented in your dealership system. Currently, your system has basic pricing (base_price, promotional_price, discount_amount, total_price, down_payment), but it's **missing many of the detailed calculations** required by the client.

**Why This Matters:** Your client needs detailed financial tracking for:
- **Accurate invoicing** with multiple discount types
- **Transparent customer pricing** showing all costs upfront
- **Dealer profitability tracking** with incentives and expenses
- **Regulatory compliance** for vehicle registration and insurance
- **Sales performance metrics** for agents and management

---

## 📊 CURRENT SYSTEM vs. REQUIRED SYSTEM

### ✅ What You Already Have:

#### **Pricing System:**
- **Base Price** (SRP of Unit) - `vehicles.base_price`
- **Promotional Price** - `vehicles.promotional_price`
- **Effective Price Logic** - Uses promo price if `promo_price > 0 AND promo_price < base_price`
- **Single Discount** - `orders.discount_amount` (currently combines all discounts)
- **Total Price** - `orders.total_price` (calculated as `base_price - discount_amount`)

#### **Payment Calculation:**
- **Centralized Calculator** - `includes/payment_calculator.php` (PaymentCalculator class)
- **Down Payment** - `orders.down_payment`
- **Financing Terms** - 3, 6, 12, 24, 36, 48, 60 months
- **Interest Rates** - Stored in `financing_rates` table or defaults (8.5% - 16.5%)
- **Monthly Payment** - Calculated using amortization formula
- **Payment Schedule** - Auto-generated in `payment_schedule` table

#### **Order Management:**
- **Order Creation** - `includes/api/create_order.php`
- **Order Status Tracking** - pending, confirmed, processing, completed, delivered, cancelled
- **Payment History** - `payment_history` table tracks all payments
- **Warranty Package** - Text field in `orders.warranty_package`
- **Insurance Details** - Text field in `orders.insurance_details`

#### **Sales Tracking:**
- **Sales Reports** - `api/sales-report.php` tracks revenue, units sold, avg order value
- **Agent Performance** - Tracks units sold and revenue per agent
- **Transaction Records** - `pages/main/transaction-records.php` (Admin view-only)
- **Commission Display** - Currently shows empty commission field (line 1152)

### ❌ What's Missing (Needs to be Added):

#### **1. Enhanced Unit Pricing:**
- **Body Package Cost** - Optional add-on for body kits/styling
- **Aircon Package Cost** - Optional air conditioning upgrade
- **White Color Surcharge** - Additional fee for white paint
- **Other Charges** - Miscellaneous unit-related fees

#### **2. Dual Discount System:**
- **Nominal Discount** - Standard/regular discount (currently combined with promo)
- **Promo Discount** - Promotional/seasonal discount (separate tracking needed)
- **Additional Discount** - Sum of both discounts (for calculations)

#### **3. Revised Pricing Calculations:**
- **Total Unit Price** - SRP + all add-ons (different from current base_price)
- **Amount to be Invoiced** - Total Unit Price - all discounts (different from total_price)
- **Amount Finance** - Percentage-based calculation of what's financed
- **Net Down Payment** - Down payment after applying discounts

#### **4. Incidentals (Government & Service Fees):**
- **Insurance Premium** - Vehicle insurance cost (currently just text notes)
- **CPTL Premium** - Compulsory Third-Party Liability insurance
- **LTO Registration** - Land Transportation Office registration fee
- **Chattel Mortgage Fee** - Loan processing/documentation fee
- **Chattel Income** - Income from chattel mortgage services
- **Extended Warranty** - 2-year warranty cost (currently just text)
- **Total Incidentals** - Sum of all above fees

#### **5. Customer Cash Requirement:**
- **Reservation Fee** - Upfront booking fee
- **Total Cash Outlay** - Final cash needed from customer (Net Down + Incidentals - Reservation)

#### **6. Dealer Financial Tracking:**
- **Gross Dealer Incentive %** - Commission percentage on financed amount
- **Gross Dealer Incentive** - Calculated commission amount
- **SFM Retain** - Amount retained by Sales Finance Manager
- **Net Dealer Incentive** - Commission after SFM retain

#### **7. Expense Tracking:**
- **Tipster Fee** - Referral/finder's fee
- **Accessories Cost** - Cost of add-on accessories
- **Other Expenses** - Miscellaneous expenses
- **SE Share** - Sales Executive's share/commission
- **Total Expenses** - Sum of all expenses

#### **8. Profitability Metrics:**
- **Gross vs. Net Summary** - Revenue vs. expenses comparison
- **Net Negative** - Final profit/loss calculation per transaction

---

## 🏗️ SYSTEM ARCHITECTURE CONTEXT

### **Current Data Flow:**
1. **Vehicle Selection** → `pages/main/orders.php` (lines 723-776)
   - Fetches vehicle from dropdown
   - Calculates effective price: `(promo_price > 0 && promo_price < base_price) ? promo_price : base_price`
   - Populates form fields

2. **Price Calculation** → JavaScript (lines 824-830)
   - `calculateTotalPrice()`: `totalPrice = basePrice - discountAmount`
   - `calculateMonthlyPayment()`: Calls `includes/payment_calculator.php` API

3. **Order Creation** → `includes/api/create_order.php` (lines 147-200)
   - Validates customer data
   - Inserts into `orders` table
   - Generates payment schedule if financing

4. **Payment Tracking** → `payment_history` & `payment_schedule` tables
   - Tracks all payments with receipts
   - Updates payment progress
   - Admin approval workflow

5. **Reporting** → `api/sales-report.php` & `pages/main/transaction-records.php`
   - Aggregates revenue, units sold
   - Tracks agent performance
   - **Currently missing:** Commission/profit calculations

### **Key Integration Points:**
- **PaymentCalculator** - Used by: orders, quotes, loan applications
- **Financing Rates** - Configurable in `pages/main/settings.php` (lines 638-657)
- **Min Down Payment** - Stored in `financing_rules` table (default 20%)
- **Order Status Flow** - pending → confirmed → processing → completed → delivered

---

## 🗂️ WHERE TO MAKE CHANGES

### 1️⃣ **DATABASE CHANGES** (HIGHEST PRIORITY)
**File:** Create new file `includes/database/add_financial_fields.sql`

**Why Database First:** All calculations depend on having the proper fields to store data. Without these columns, you can't save the calculated values.

You need to add new columns to existing tables:

#### A. **`vehicles` table** - Add optional unit costs:
**Current Structure:** The vehicles table already has `base_price`, `promotional_price`, `min_downpayment_percentage`

**Add These Columns:**
```sql
ALTER TABLE vehicles
ADD COLUMN body_package_price DECIMAL(12,2) DEFAULT 0 COMMENT 'Optional body package cost',
ADD COLUMN aircon_package_price DECIMAL(12,2) DEFAULT 0 COMMENT 'Optional air conditioning package',
ADD COLUMN white_color_surcharge DECIMAL(12,2) DEFAULT 0 COMMENT 'Additional charge for white color',
ADD COLUMN other_charges DECIMAL(12,2) DEFAULT 0 COMMENT 'Other miscellaneous charges';
```

**Impact:** These fields will be used in Formula #1 (Total Unit Price). They're optional add-ons that vary by vehicle model.

#### B. **`orders` table** - Add detailed financial fields:
**Current Structure:** The orders table currently has these financial fields:
- `base_price` - Vehicle base price at time of order
- `discount_amount` - Single combined discount
- `total_price` - Final price after discount
- `down_payment` - Down payment amount
- `financing_term` - Loan term in months
- `monthly_payment` - Monthly payment amount
- `warranty_package` - Text description
- `insurance_details` - Text description

**Add These Columns:**
```sql
ALTER TABLE orders
-- Pricing Breakdown
ADD COLUMN total_unit_price DECIMAL(12,2) DEFAULT 0 COMMENT 'SRP + all add-ons (Formula #1)',
ADD COLUMN nominal_discount DECIMAL(12,2) DEFAULT 0 COMMENT 'Standard discount',
ADD COLUMN promo_discount DECIMAL(12,2) DEFAULT 0 COMMENT 'Promotional discount',
ADD COLUMN amount_to_invoice DECIMAL(12,2) DEFAULT 0 COMMENT 'Final invoiced amount (Formula #2)',
ADD COLUMN amount_finance DECIMAL(12,2) DEFAULT 0 COMMENT 'Amount to be financed (Formula #3)',
ADD COLUMN finance_percentage DECIMAL(5,2) DEFAULT 0 COMMENT 'Percentage financed',
ADD COLUMN down_payment_percentage DECIMAL(5,2) DEFAULT 0 COMMENT 'Down payment percentage',
ADD COLUMN net_down_payment DECIMAL(12,2) DEFAULT 0 COMMENT 'Down payment after discounts (Formula #6)',
ADD COLUMN reservation_fee DECIMAL(12,2) DEFAULT 0 COMMENT 'Reservation fee paid',

-- Incidentals (Government & Service Fees)
ADD COLUMN insurance_premium DECIMAL(12,2) DEFAULT 0 COMMENT 'Vehicle insurance cost',
ADD COLUMN cptl_premium DECIMAL(12,2) DEFAULT 0 COMMENT 'Compulsory third-party liability',
ADD COLUMN lto_registration DECIMAL(12,2) DEFAULT 0 COMMENT 'LTO registration fee',
ADD COLUMN chattel_mortgage_fee DECIMAL(12,2) DEFAULT 0 COMMENT 'Chattel mortgage processing fee',
ADD COLUMN chattel_income DECIMAL(12,2) DEFAULT 0 COMMENT 'Chattel income',
ADD COLUMN extended_warranty DECIMAL(12,2) DEFAULT 0 COMMENT '2-year extended warranty cost',
ADD COLUMN total_incidentals DECIMAL(12,2) DEFAULT 0 COMMENT 'Sum of all incidentals (Formula #7)',
ADD COLUMN total_cash_outlay DECIMAL(12,2) DEFAULT 0 COMMENT 'Final cash required from customer (Formula #8)',

-- Dealer & Expense Tracking (Admin Only)
ADD COLUMN gross_dealer_incentive_pct DECIMAL(5,2) DEFAULT 0 COMMENT 'Dealer incentive percentage',
ADD COLUMN gross_dealer_incentive DECIMAL(12,2) DEFAULT 0 COMMENT 'Gross dealer incentive amount (Formula #9)',
ADD COLUMN sfm_retain DECIMAL(12,2) DEFAULT 0 COMMENT 'SFM retained amount',
ADD COLUMN sfm_additional DECIMAL(12,2) DEFAULT 0 COMMENT 'SFM additional charges',
ADD COLUMN net_dealer_incentive DECIMAL(12,2) DEFAULT 0 COMMENT 'Net dealer incentive after retain (Formula #10)',
ADD COLUMN tipster_fee DECIMAL(12,2) DEFAULT 0 COMMENT 'Tipster/referral fee',
ADD COLUMN accessories_cost DECIMAL(12,2) DEFAULT 0 COMMENT 'Accessories cost',
ADD COLUMN other_expenses DECIMAL(12,2) DEFAULT 0 COMMENT 'Other expenses',
ADD COLUMN total_expenses DECIMAL(12,2) DEFAULT 0 COMMENT 'Total expenses (Formula #11)',
ADD COLUMN gross_net_balance DECIMAL(12,2) DEFAULT 0 COMMENT 'Gross vs Net summary (Formula #12)',
ADD COLUMN net_negative DECIMAL(12,2) DEFAULT 0 COMMENT 'Net profit/loss (Formula #13)',
ADD COLUMN se_share DECIMAL(12,2) DEFAULT 0 COMMENT 'Sales Executive share';
```

**Impact:** These 30+ new fields will store all calculated values from the 13 formulas. The existing `discount_amount` and `total_price` fields will be kept for backward compatibility but will be calculated differently.

**Migration Note:** Existing orders will have these fields set to 0. You may want to run a data migration script to populate them based on existing `discount_amount` and `total_price` values.

---

### 2️⃣ **BACKEND CALCULATION ENGINE**
**File:** Create new file `includes/financial_calculator.php`

**Purpose:** Centralized calculation logic that implements all 13 formulas from the revision document.

**Why Create This:** Your system already has `includes/payment_calculator.php` for loan amortization. This new calculator will handle the comprehensive financial breakdown while integrating with the existing payment calculator.

**Architecture Pattern:** Follow the same pattern as `PaymentCalculator`:
- Class-based design
- PDO database connection
- Validation and error handling
- Return structured arrays
- Support both API and direct PHP usage

**Key Functions to Create:**

```php
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
        // Validates inputs and calculates all 13 formulas
        // Returns array with all calculated fields
    }

    // Individual calculation methods:
    public function calculateTotalUnitPrice($basePrice, $bodyPackage, $aircon, $whiteColor, $others)
        // Formula #1: SRP + Body + Aircon + White Color + Others

    public function calculateAmountToInvoice($totalUnitPrice, $nominalDiscount, $promoDiscount)
        // Formula #2: Total Unit Price - Nominal Discount - Promo Discount

    public function calculateAmountFinance($totalUnitPrice, $financePercentage)
        // Formula #3: Total Unit Price × Finance %

    public function calculateDownPayment($totalUnitPrice, $downPaymentPercentage)
        // Formula #4: Total Unit Price × Down Payment %

    public function calculateAdditionalDiscount($nominalDiscount, $promoDiscount)
        // Formula #5: Nominal Discount + Promo Discount

    public function calculateNetDownPayment($downPayment, $additionalDiscount)
        // Formula #6: Down Payment - Additional Discount

    public function calculateTotalIncidentals($insurance, $cptl, $lto, $chattelFee, $chattelIncome, $warranty)
        // Formula #7: Insurance + CPTL + LTO + Chattel Mortgage + Chattel Income + Warranty

    public function calculateTotalCashOutlay($netDownPayment, $totalIncidentals, $reservationFee)
        // Formula #8: Net Down Payment + Total Incidentals - Reservation

    public function calculateGrossDealerIncentive($amountFinance, $incentivePercentage)
        // Formula #9: Amount Finance × Dealer Incentive %

    public function calculateNetDealerIncentive($grossIncentive, $sfmRetain)
        // Formula #10: Gross Dealer Incentive - SFM Retain

    public function calculateTotalExpenses($nominalDiscount, $promoDiscount, $sfmAdditional, $incidentals, $tipster, $accessories, $others)
        // Formula #11: All-in Discount + SFM Add'l + Incidentals + Tipster + Accessories + Others

    public function calculateGrossNetBalance($grossIncentive, $sfmRetain, $totalExpenses)
        // Formula #12: (Gross & SFM Retain Total) - (Total Expenses)

    public function calculateNetNegative($totalExpenses, $seShare)
        // Formula #13: Total Expenses + SE Share
}
```

**Integration with Existing PaymentCalculator:**
The FinancialCalculator will work alongside PaymentCalculator:
- **FinancialCalculator** → Calculates pricing, discounts, incidentals, cash outlay
- **PaymentCalculator** → Calculates monthly payments, amortization schedule
- Both are called during order creation to get complete financial picture

---

### 3️⃣ **FRONTEND FORMS** (Where Users Enter Data)

#### A. **Orders Page** - `pages/main/orders.php`
**Current Structure:** The order form has these sections:
- **Customer Information** (lines 300-380) - Name, email, phone, address
- **Vehicle Selection** (lines 381-414) - Dropdown with vehicle details
- **Order Details** (lines 416-524) - Pricing, payment method, delivery, notes

**Lines to modify:** 416-524 (Order Details section)

**Current Fields:**
- Order Number (readonly)
- Order Status (dropdown)
- Base Price (readonly, from vehicle)
- Discount Amount (single field - **needs to be split**)
- Total Price (readonly, calculated)
- Payment Method (cash/financing/bank_transfer/check)
- Down Payment (if financing)
- Financing Term (if financing)
- Monthly Payment (readonly, calculated)
- Warranty Package (text field - **needs to become calculated**)
- Delivery Date
- Actual Delivery Date
- Delivery Address
- Order Notes
- Special Instructions
- Insurance Details (text field - **needs to become calculated**)

**What to add - NEW SECTIONS:**

**Section 1: Unit Cost Breakdown** (Insert after line 413, before Order Details)
```html
<div class="form-section">
  <h3>Unit Cost Breakdown</h3>
  <div class="form-row">
    <div class="form-group">
      <label for="body_package_price">Body Package (₱)</label>
      <input type="number" id="body_package_price" name="body_package_price" class="form-control" step="0.01" value="0">
    </div>
    <div class="form-group">
      <label for="aircon_package_price">Aircon Package (₱)</label>
      <input type="number" id="aircon_package_price" name="aircon_package_price" class="form-control" step="0.01" value="0">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label for="white_color_surcharge">White Color Surcharge (₱)</label>
      <input type="number" id="white_color_surcharge" name="white_color_surcharge" class="form-control" step="0.01" value="0">
    </div>
    <div class="form-group">
      <label for="other_charges">Other Charges (₱)</label>
      <input type="number" id="other_charges" name="other_charges" class="form-control" step="0.01" value="0">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label for="total_unit_price">Total Unit Price (₱)</label>
      <input type="number" id="total_unit_price" name="total_unit_price" class="form-control" step="0.01" readonly style="background-color: #f0f0f0; font-weight: bold;">
      <small class="form-text text-muted">Auto-calculated: SRP + Body + Aircon + White Color + Others</small>
    </div>
  </div>
</div>
```

**Section 2: Discounts** (Replace existing discount_amount field at line 442-444)
```html
<div class="form-row">
  <div class="form-group">
    <label for="nominal_discount">Nominal Discount (₱)</label>
    <input type="number" id="nominal_discount" name="nominal_discount" class="form-control" step="0.01" value="0">
    <small class="form-text text-muted">Standard/regular discount</small>
  </div>
  <div class="form-group">
    <label for="promo_discount">Promo Discount (₱)</label>
    <input type="number" id="promo_discount" name="promo_discount" class="form-control" step="0.01" value="0">
    <small class="form-text text-muted">Promotional/seasonal discount</small>
  </div>
</div>
<div class="form-row">
  <div class="form-group">
    <label for="amount_to_invoice">Amount to be Invoiced (₱)</label>
    <input type="number" id="amount_to_invoice" name="amount_to_invoice" class="form-control" step="0.01" readonly style="background-color: #f0f0f0; font-weight: bold;">
    <small class="form-text text-muted">Auto-calculated: Total Unit Price - Discounts</small>
  </div>
</div>
```

**Section 3: Financing Breakdown** (Modify existing financing section at lines 465-493)
```html
<div id="financingDetails" style="display: none;">
  <div class="form-row">
    <div class="form-group">
      <label for="finance_percentage">Finance Percentage (%)</label>
      <input type="number" id="finance_percentage" name="finance_percentage" class="form-control" step="0.01" min="0" max="100" value="80">
    </div>
    <div class="form-group">
      <label for="amount_finance">Amount to Finance (₱)</label>
      <input type="number" id="amount_finance" name="amount_finance" class="form-control" step="0.01" readonly style="background-color: #f0f0f0;">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label for="down_payment_percentage">Down Payment (%)</label>
      <input type="number" id="down_payment_percentage" name="down_payment_percentage" class="form-control" step="0.01" min="0" max="100" value="20">
    </div>
    <div class="form-group">
      <label for="down_payment">Down Payment (₱)</label>
      <input type="number" id="down_payment" name="down_payment" class="form-control" step="0.01" readonly style="background-color: #f0f0f0;">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label for="net_down_payment">Net Down Payment (₱)</label>
      <input type="number" id="net_down_payment" name="net_down_payment" class="form-control" step="0.01" readonly style="background-color: #f0f0f0; font-weight: bold;">
      <small class="form-text text-muted">Down Payment - Discounts</small>
    </div>
    <div class="form-group">
      <label for="financing_term">Financing Term</label>
      <select id="financing_term" name="financing_term" class="form-control">
        <option value="">Select Term</option>
        <option value="12">12 months</option>
        <option value="24">24 months</option>
        <option value="36">36 months</option>
        <option value="48">48 months</option>
        <option value="60">60 months</option>
      </select>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label for="monthly_payment">Monthly Payment (₱)</label>
      <input type="number" id="monthly_payment" name="monthly_payment" class="form-control" step="0.01" readonly style="background-color: #f0f0f0;">
    </div>
  </div>
</div>
```

**Section 4: Incidentals** (Insert after financing section, before line 495)
```html
<div class="form-section">
  <h3>Incidentals (Government & Service Fees)</h3>
  <div class="form-row">
    <div class="form-group">
      <label for="insurance_premium">Insurance Premium (₱)</label>
      <input type="number" id="insurance_premium" name="insurance_premium" class="form-control" step="0.01" value="0">
    </div>
    <div class="form-group">
      <label for="cptl_premium">CPTL Premium (₱)</label>
      <input type="number" id="cptl_premium" name="cptl_premium" class="form-control" step="0.01" value="0">
      <small class="form-text text-muted">Compulsory Third-Party Liability</small>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label for="lto_registration">LTO Registration (₱)</label>
      <input type="number" id="lto_registration" name="lto_registration" class="form-control" step="0.01" value="0">
    </div>
    <div class="form-group">
      <label for="chattel_mortgage_fee">Chattel Mortgage Fee (₱)</label>
      <input type="number" id="chattel_mortgage_fee" name="chattel_mortgage_fee" class="form-control" step="0.01" value="0">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label for="chattel_income">Chattel Income (₱)</label>
      <input type="number" id="chattel_income" name="chattel_income" class="form-control" step="0.01" value="0">
    </div>
    <div class="form-group">
      <label for="extended_warranty">Extended Warranty (₱)</label>
      <input type="number" id="extended_warranty" name="extended_warranty" class="form-control" step="0.01" value="0">
      <small class="form-text text-muted">2-year extended warranty</small>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label for="total_incidentals">Total Incidentals (₱)</label>
      <input type="number" id="total_incidentals" name="total_incidentals" class="form-control" step="0.01" readonly style="background-color: #f0f0f0; font-weight: bold;">
    </div>
  </div>
</div>
```

**Section 5: Customer Cash Requirement** (Insert before delivery date section)
```html
<div class="form-section" style="background-color: #fff3cd; padding: 15px; border-radius: 5px; border: 2px solid #ffc107;">
  <h3 style="color: #856404;">💰 Customer Cash Requirement</h3>
  <div class="form-row">
    <div class="form-group">
      <label for="reservation_fee">Reservation Fee (₱)</label>
      <input type="number" id="reservation_fee" name="reservation_fee" class="form-control" step="0.01" value="0">
    </div>
    <div class="form-group">
      <label for="total_cash_outlay" style="font-weight: bold; font-size: 1.1em;">TOTAL CASH OUTLAY (₱)</label>
      <input type="number" id="total_cash_outlay" name="total_cash_outlay" class="form-control" step="0.01" readonly style="background-color: #ffc107; font-weight: bold; font-size: 1.2em; color: #000;">
      <small class="form-text text-muted">Net Down Payment + Incidentals - Reservation</small>
    </div>
  </div>
</div>
```

**Section 6: Dealer & Expenses** (Admin/Agent Only - Insert before notes section)
```html
<div class="form-section" id="dealerExpensesSection" style="display: none;">
  <h3>🏢 Dealer Incentives & Expenses (Admin Only)</h3>
  <div class="form-row">
    <div class="form-group">
      <label for="gross_dealer_incentive_pct">Dealer Incentive (%)</label>
      <input type="number" id="gross_dealer_incentive_pct" name="gross_dealer_incentive_pct" class="form-control" step="0.01" value="0">
    </div>
    <div class="form-group">
      <label for="gross_dealer_incentive">Gross Dealer Incentive (₱)</label>
      <input type="number" id="gross_dealer_incentive" name="gross_dealer_incentive" class="form-control" step="0.01" readonly style="background-color: #f0f0f0;">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label for="sfm_retain">SFM Retain (₱)</label>
      <input type="number" id="sfm_retain" name="sfm_retain" class="form-control" step="0.01" value="0">
    </div>
    <div class="form-group">
      <label for="net_dealer_incentive">Net Dealer Incentive (₱)</label>
      <input type="number" id="net_dealer_incentive" name="net_dealer_incentive" class="form-control" step="0.01" readonly style="background-color: #f0f0f0;">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label for="tipster_fee">Tipster Fee (₱)</label>
      <input type="number" id="tipster_fee" name="tipster_fee" class="form-control" step="0.01" value="0">
    </div>
    <div class="form-group">
      <label for="accessories_cost">Accessories Cost (₱)</label>
      <input type="number" id="accessories_cost" name="accessories_cost" class="form-control" step="0.01" value="0">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label for="other_expenses">Other Expenses (₱)</label>
      <input type="number" id="other_expenses" name="other_expenses" class="form-control" step="0.01" value="0">
    </div>
    <div class="form-group">
      <label for="se_share">SE Share (₱)</label>
      <input type="number" id="se_share" name="se_share" class="form-control" step="0.01" value="0">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label for="net_negative">Net Profit/Loss (₱)</label>
      <input type="number" id="net_negative" name="net_negative" class="form-control" step="0.01" readonly style="background-color: #f0f0f0; font-weight: bold;">
    </div>
  </div>
</div>
```

**JavaScript to add:** Real-time calculation functions (Insert after line 830, after existing calculateTotalPrice function)

```javascript
// Show/hide dealer expenses section based on user role
document.addEventListener('DOMContentLoaded', function() {
  const userRole = '<?php echo $_SESSION['user_role'] ?? ''; ?>';
  if (userRole === 'Admin') {
    document.getElementById('dealerExpensesSection').style.display = 'block';
  }
});

// Real-time financial calculations
function calculateFinancials() {
  // Get all input values
  const basePrice = parseFloat(document.getElementById('order_base_price').value) || 0;
  const bodyPackage = parseFloat(document.getElementById('body_package_price').value) || 0;
  const airconPackage = parseFloat(document.getElementById('aircon_package_price').value) || 0;
  const whiteColor = parseFloat(document.getElementById('white_color_surcharge').value) || 0;
  const otherCharges = parseFloat(document.getElementById('other_charges').value) || 0;

  const nominalDiscount = parseFloat(document.getElementById('nominal_discount').value) || 0;
  const promoDiscount = parseFloat(document.getElementById('promo_discount').value) || 0;

  const financePercentage = parseFloat(document.getElementById('finance_percentage').value) || 0;
  const downPaymentPercentage = parseFloat(document.getElementById('down_payment_percentage').value) || 0;

  const insurance = parseFloat(document.getElementById('insurance_premium').value) || 0;
  const cptl = parseFloat(document.getElementById('cptl_premium').value) || 0;
  const lto = parseFloat(document.getElementById('lto_registration').value) || 0;
  const chattelFee = parseFloat(document.getElementById('chattel_mortgage_fee').value) || 0;
  const chattelIncome = parseFloat(document.getElementById('chattel_income').value) || 0;
  const warranty = parseFloat(document.getElementById('extended_warranty').value) || 0;

  const reservationFee = parseFloat(document.getElementById('reservation_fee').value) || 0;

  const dealerIncentivePct = parseFloat(document.getElementById('gross_dealer_incentive_pct').value) || 0;
  const sfmRetain = parseFloat(document.getElementById('sfm_retain').value) || 0;
  const tipsterFee = parseFloat(document.getElementById('tipster_fee').value) || 0;
  const accessoriesCost = parseFloat(document.getElementById('accessories_cost').value) || 0;
  const otherExpenses = parseFloat(document.getElementById('other_expenses').value) || 0;
  const seShare = parseFloat(document.getElementById('se_share').value) || 0;

  // Formula #1: Total Unit Price
  const totalUnitPrice = basePrice + bodyPackage + airconPackage + whiteColor + otherCharges;
  document.getElementById('total_unit_price').value = totalUnitPrice.toFixed(2);

  // Formula #2: Amount to Invoice
  const amountToInvoice = totalUnitPrice - nominalDiscount - promoDiscount;
  document.getElementById('amount_to_invoice').value = Math.max(0, amountToInvoice).toFixed(2);

  // Formula #3: Amount Finance
  const amountFinance = totalUnitPrice * (financePercentage / 100);
  document.getElementById('amount_finance').value = amountFinance.toFixed(2);

  // Formula #4: Down Payment
  const downPayment = totalUnitPrice * (downPaymentPercentage / 100);
  document.getElementById('down_payment').value = downPayment.toFixed(2);

  // Formula #6: Net Down Payment
  const netDownPayment = downPayment - nominalDiscount - promoDiscount;
  document.getElementById('net_down_payment').value = Math.max(0, netDownPayment).toFixed(2);

  // Formula #7: Total Incidentals
  const totalIncidentals = insurance + cptl + lto + chattelFee + chattelIncome + warranty;
  document.getElementById('total_incidentals').value = totalIncidentals.toFixed(2);

  // Formula #8: Total Cash Outlay
  const totalCashOutlay = Math.max(0, netDownPayment) + totalIncidentals - reservationFee;
  document.getElementById('total_cash_outlay').value = Math.max(0, totalCashOutlay).toFixed(2);

  // Formula #9: Gross Dealer Incentive
  const grossDealerIncentive = amountFinance * (dealerIncentivePct / 100);
  document.getElementById('gross_dealer_incentive').value = grossDealerIncentive.toFixed(2);

  // Formula #10: Net Dealer Incentive
  const netDealerIncentive = grossDealerIncentive - sfmRetain;
  document.getElementById('net_dealer_incentive').value = netDealerIncentive.toFixed(2);

  // Formula #13: Net Negative (simplified - full formula in backend)
  const totalExpenses = nominalDiscount + promoDiscount + totalIncidentals + tipsterFee + accessoriesCost + otherExpenses;
  const netNegative = totalExpenses + seShare;
  document.getElementById('net_negative').value = netNegative.toFixed(2);

  // Update legacy fields for backward compatibility
  document.getElementById('discount_amount').value = (nominalDiscount + promoDiscount).toFixed(2);
  document.getElementById('total_price').value = Math.max(0, amountToInvoice).toFixed(2);
}

// Attach event listeners to all input fields
document.querySelectorAll('#orderModal input[type="number"]').forEach(input => {
  input.addEventListener('input', calculateFinancials);
});
```

---

#### B. **Loan Application Forms**
**Files to modify:**
- `pages/loan_excel_form.php` (lines 200-500)
- `pages/loan_document_submission.php` (lines 50-150)

**What to add:**
- Incidentals input fields
- Display calculated values from the financial calculator

---

### 4️⃣ **API ENDPOINTS** (Backend Processing)

#### A. **Create Order API** - `includes/api/create_order.php`
**Current Structure:** This file handles POST requests to create new orders
- **Lines 1-30:** Database connection, session validation, input sanitization
- **Lines 31-80:** Customer validation (existing vs. new customer)
- **Lines 81-146:** Vehicle validation and price fetching
- **Lines 147-200:** Order insertion with transaction handling
- **Lines 201-250:** Payment schedule generation (if financing)
- **Lines 251-280:** Response with order details

**Lines to modify:** 147-200 (Order insertion section)

**Current INSERT statement (line 147-165):**
```php
$stmt = $connect->prepare("
    INSERT INTO orders (
        order_number, customer_id, sales_agent_id, vehicle_id,
        client_type, vehicle_model, vehicle_variant, vehicle_color, model_year,
        base_price, discount_amount, total_price,
        payment_method, down_payment, financing_term, monthly_payment,
        order_status, delivery_date, actual_delivery_date, delivery_address,
        order_notes, special_instructions, warranty_package, insurance_details,
        created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
");
```

**What to change:**

**Step 1:** Add FinancialCalculator integration (Insert at line 146, before INSERT)
```php
// Calculate all financial values using the new calculator
require_once '../financial_calculator.php';
$calculator = new FinancialCalculator($connect);

try {
    // Prepare input for calculator
    $calculatorInput = [
        // Unit pricing
        'base_price' => $input['base_price'],
        'body_package_price' => $input['body_package_price'] ?? 0,
        'aircon_package_price' => $input['aircon_package_price'] ?? 0,
        'white_color_surcharge' => $input['white_color_surcharge'] ?? 0,
        'other_charges' => $input['other_charges'] ?? 0,

        // Discounts
        'nominal_discount' => $input['nominal_discount'] ?? 0,
        'promo_discount' => $input['promo_discount'] ?? 0,

        // Financing
        'finance_percentage' => $input['finance_percentage'] ?? 0,
        'down_payment_percentage' => $input['down_payment_percentage'] ?? 20,

        // Incidentals
        'insurance_premium' => $input['insurance_premium'] ?? 0,
        'cptl_premium' => $input['cptl_premium'] ?? 0,
        'lto_registration' => $input['lto_registration'] ?? 0,
        'chattel_mortgage_fee' => $input['chattel_mortgage_fee'] ?? 0,
        'chattel_income' => $input['chattel_income'] ?? 0,
        'extended_warranty' => $input['extended_warranty'] ?? 0,

        // Customer payment
        'reservation_fee' => $input['reservation_fee'] ?? 0,

        // Dealer & expenses (admin only)
        'gross_dealer_incentive_pct' => $input['gross_dealer_incentive_pct'] ?? 0,
        'sfm_retain' => $input['sfm_retain'] ?? 0,
        'sfm_additional' => $input['sfm_additional'] ?? 0,
        'tipster_fee' => $input['tipster_fee'] ?? 0,
        'accessories_cost' => $input['accessories_cost'] ?? 0,
        'other_expenses' => $input['other_expenses'] ?? 0,
        'se_share' => $input['se_share'] ?? 0
    ];

    // Calculate all financial values
    $financials = $calculator->calculateAll($calculatorInput);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Financial calculation error: ' . $e->getMessage()
    ]);
    exit;
}
```

**Step 2:** Update INSERT statement to include all new fields (Replace lines 147-165)
```php
$stmt = $connect->prepare("
    INSERT INTO orders (
        -- Existing fields
        order_number, customer_id, sales_agent_id, vehicle_id,
        client_type, vehicle_model, vehicle_variant, vehicle_color, model_year,
        order_status, delivery_date, actual_delivery_date, delivery_address,
        order_notes, special_instructions, payment_method,

        -- Enhanced pricing fields
        base_price, total_unit_price,
        nominal_discount, promo_discount, discount_amount,
        amount_to_invoice, total_price,

        -- Financing fields
        finance_percentage, amount_finance,
        down_payment_percentage, down_payment, net_down_payment,
        financing_term, monthly_payment,

        -- Incidentals
        insurance_premium, cptl_premium, lto_registration,
        chattel_mortgage_fee, chattel_income, extended_warranty,
        total_incidentals, warranty_package, insurance_details,

        -- Customer payment
        reservation_fee, total_cash_outlay,

        -- Dealer & expenses
        gross_dealer_incentive_pct, gross_dealer_incentive,
        sfm_retain, sfm_additional, net_dealer_incentive,
        tipster_fee, accessories_cost, other_expenses, se_share,
        total_expenses, gross_net_balance, net_negative,

        -- Timestamps
        created_at, updated_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        NOW(), NOW()
    )
");
```

**Step 3:** Update execute() parameters with calculated values
```php
$stmt->execute([
    // Existing fields
    $input['order_number'],
    $customer_id,
    $input['sales_agent_id'],
    $input['vehicle_id'],
    $input['client_type'],
    $input['vehicle_model'],
    $input['vehicle_variant'],
    $input['vehicle_color'],
    $input['model_year'],
    $input['order_status'] ?? 'pending',
    $input['delivery_date'] ?? null,
    $input['actual_delivery_date'] ?? null,
    $input['delivery_address'] ?? '',
    $input['order_notes'] ?? '',
    $input['special_instructions'] ?? '',
    $input['payment_method'],

    // Enhanced pricing (from calculator)
    $input['base_price'],
    $financials['total_unit_price'],
    $financials['nominal_discount'],
    $financials['promo_discount'],
    $financials['discount_amount'],
    $financials['amount_to_invoice'],
    $financials['total_price'],

    // Financing (from calculator)
    $financials['finance_percentage'],
    $financials['amount_finance'],
    $financials['down_payment_percentage'],
    $financials['down_payment'],
    $financials['net_down_payment'],
    $input['financing_term'] ?? null,
    $input['monthly_payment'] ?? 0,

    // Incidentals (from calculator)
    $financials['insurance_premium'],
    $financials['cptl_premium'],
    $financials['lto_registration'],
    $financials['chattel_mortgage_fee'],
    $financials['chattel_income'],
    $financials['extended_warranty'],
    $financials['total_incidentals'],
    $input['warranty_package'] ?? '',
    $input['insurance_details'] ?? '',

    // Customer payment (from calculator)
    $financials['reservation_fee'],
    $financials['total_cash_outlay'],

    // Dealer & expenses (from calculator)
    $financials['gross_dealer_incentive_pct'],
    $financials['gross_dealer_incentive'],
    $financials['sfm_retain'],
    $financials['sfm_additional'],
    $financials['net_dealer_incentive'],
    $financials['tipster_fee'],
    $financials['accessories_cost'],
    $financials['other_expenses'],
    $financials['se_share'],
    $financials['total_expenses'],
    $financials['gross_net_balance'],
    $financials['net_negative']
]);
```

**Integration Notes:**
- **FinancialCalculator** runs first to get all pricing/discount/incidental calculations
- **PaymentCalculator** runs second (only if financing) to get monthly payment
- Both calculators work together to provide complete financial picture
- Transaction handling ensures all-or-nothing database updates

---

#### B. **Loan Applications API** - `api/loan-applications.php`
**Lines to modify:** Around lines 880-920 (order creation from loan)

**What to change:**
- Include incidentals in loan approval process
- Calculate total cash outlay for customer
- Use FinancialCalculator when converting approved loan to order

---

### 5️⃣ **DISPLAY/REPORTING PAGES**

#### A. **Transaction Records** - `pages/main/transaction-records.php`
**Current:** Shows basic order info (order_number, customer, vehicle, total_price, agent)
**Commission field is currently empty** (line 1152)

**What to add:**
- Display all calculated financial breakdowns in transaction details modal
- Show profit/loss (Net Negative) - Admin only
- Show dealer incentives - Admin only
- Update commission field to show `net_dealer_incentive`

**Specific Changes:**
- Add financial breakdown sections to transaction details modal (after line 250)
- Update JavaScript to populate new fields (around line 1200)
- Add role-based visibility for dealer/profit sections
- Update commission display in DataTable (line 1152)

---

#### B. **Sales Reports** - `api/sales-report.php`
**Current Metrics:** total_revenue, units_sold, avg_order_value, revenue by agent/model
**Missing:** Dealer incentives, expenses, profit margins

**What to add:**
- Total dealer incentives (gross & net)
- Total expenses
- Net profit/loss
- Commission per agent in agent performance report

**Specific Changes:**
- Add dealer incentive calculations (after line 100)
- Update agent performance query to include commission, expenses, profit (after line 150)
- Add new metrics to response JSON

---

#### C. **Order Details** - `pages/order_details.php`
**Lines to modify:** Around lines 1370-1520 (pricing details section)

**What to add:**
- Detailed financial breakdown showing all formulas
- Incidentals breakdown
- Cash outlay summary
- Dealer incentives (admin only)

---

## 🔢 FORMULA IMPLEMENTATION MAPPING

### Formula #1: Total Unit Price
**Where:** `includes/financial_calculator.php`
```
Total Unit Price = SRP + Body + Aircon + White Color + Others
```
**Database fields:** `base_price + body_package_price + aircon_package_price + white_color_surcharge + other_charges`

---

### Formula #2: Amount to be Invoiced
**Where:** `includes/financial_calculator.php`
```
Amount to Invoice = Total Unit Price - Nominal Discount - Promo Discount
```
**Database fields:** `total_unit_price - nominal_discount - promo_discount`

---

### Formula #3: Amount Finance
**Where:** `includes/financial_calculator.php`
```
Amount Finance = Total Unit Price × Finance %
```
**Database fields:** `total_unit_price * (finance_percentage / 100)`

---

### Formula #4: Down Payment
**Where:** `includes/financial_calculator.php`
```
Down Payment = Total Unit Price × Down Payment %
```
**Database fields:** `total_unit_price * (down_payment_percentage / 100)`

---

### Formula #5: Additional Discount
**Where:** `includes/financial_calculator.php`
```
Additional Discount = Nominal Discount + Promo Discount
```
**Database fields:** `nominal_discount + promo_discount`

---

### Formula #6: Net Down Payment
**Where:** `includes/financial_calculator.php`
```
Net Down Payment = Down Payment - Additional Discount
```
**Database fields:** `down_payment - (nominal_discount + promo_discount)`

---

### Formula #7: Total Incidentals Applied
**Where:** `includes/financial_calculator.php`
```
Total Incidentals = Insurance + CPTL + LTO + Chattel Mortgage Fee + Chattel Income + Extended Warranty
```
**Database fields:** `insurance_premium + cptl_premium + lto_registration + chattel_mortgage_fee + chattel_income + extended_warranty`

---

### Formula #8: Total Cash Outlay
**Where:** `includes/financial_calculator.php`
```
Total Cash Outlay = Net Down Payment + Total Incidentals - Reservation
```
**Database fields:** `net_down_payment + total_incidentals - reservation_fee`

---

### Formula #9: Gross Dealer's Incentives
**Where:** `includes/financial_calculator.php`
```
Gross Dealer Incentive = Amount Finance × Dealer Incentive %
```
**Database fields:** `amount_finance * (gross_dealer_incentive_pct / 100)`

---

### Formula #10: Net Dealer Incentive After Retain
**Where:** `includes/financial_calculator.php`
```
Net Dealer Incentive = Gross Dealer Incentive - SFM Retain
```
**Database fields:** `gross_dealer_incentive - sfm_retain`

---

### Formula #11: Expense Summary
**Where:** `includes/financial_calculator.php`
```
Total Expenses = All-in Discount + SFM Add'l + Incidentals + Tipster + Accessories + Others
```
**Database fields:** `(nominal_discount + promo_discount) + sfm_additional + total_incidentals + tipster_fee + accessories_cost + other_expenses`

---

### Formula #12: Gross vs. Net Summary
**Where:** `includes/financial_calculator.php`
```
Resulting Balance = (Gross & SFM Retain Total) - (Total Expenses)
```
**Database fields:** `(gross_dealer_incentive + sfm_retain) - total_expenses`

---

### Formula #13: Net Negative Calculation
**Where:** `includes/financial_calculator.php`
```
Net Negative = Total Expenses + SE Share
```
**Database fields:** `total_expenses + se_share`

---

## 📝 IMPLEMENTATION PRIORITY ORDER

### Phase 1: Database (MUST DO FIRST)
1. ✅ Run the SQL script to add all new columns
2. ✅ Test that columns are added successfully

### Phase 2: Backend Calculator
3. ✅ Create `includes/financial_calculator.php`
4. ✅ Implement all 13 formulas as functions
5. ✅ Test calculations with sample data

### Phase 3: Forms (User Input)
6. ✅ Update `pages/main/orders.php` - add input fields
7. ✅ Add JavaScript for real-time calculations
8. ✅ Update loan application forms

### Phase 4: API Integration
9. ✅ Update `includes/api/create_order.php` - integrate calculator
10. ✅ Update `api/loan-applications.php` - integrate calculator

### Phase 5: Display/Reports
11. ✅ Update transaction records page
12. ✅ Update order details page
13. ✅ Add financial breakdown displays

---

## 🎨 USER INTERFACE CHANGES

### Orders Form - New Sections to Add:

**Section 1: Unit Cost Breakdown**
```
[ ] Body Package: ₱_______
[ ] Aircon Package: ₱_______
[ ] White Color Surcharge: ₱_______
[ ] Other Charges: ₱_______
= Total Unit Price: ₱_______ (auto-calculated)
```

**Section 2: Discounts**
```
[ ] Nominal Discount: ₱_______
[ ] Promo Discount: ₱_______
= Total Discount: ₱_______ (auto-calculated)
= Amount to Invoice: ₱_______ (auto-calculated)
```

**Section 3: Payment Breakdown**
```
[ ] Finance Percentage: ____%
= Amount Finance: ₱_______ (auto-calculated)
= Down Payment: ₱_______ (auto-calculated)
= Net Down Payment: ₱_______ (auto-calculated)
```

**Section 4: Incidentals**
```
[ ] Insurance Premium: ₱_______
[ ] CPTL Premium: ₱_______
[ ] LTO Registration: ₱_______
[ ] Chattel Mortgage Fee: ₱_______
[ ] Chattel Income: ₱_______
[ ] Extended Warranty (2 yrs): ₱_______
= Total Incidentals: ₱_______ (auto-calculated)
```

**Section 5: Final Cash Requirement**
```
[ ] Reservation Fee: ₱_______
= TOTAL CASH OUTLAY: ₱_______ (auto-calculated, highlighted)
```

**Section 6: Dealer & Expenses (Admin/Agent Only)**
```
[ ] Dealer Incentive %: ____%
= Gross Dealer Incentive: ₱_______ (auto-calculated)
[ ] SFM Retain: ₱_______
= Net Dealer Incentive: ₱_______ (auto-calculated)

[ ] Tipster Fee: ₱_______
[ ] Accessories Cost: ₱_______
[ ] Other Expenses: ₱_______
[ ] SE Share: ₱_______
= Net Profit/Loss: ₱_______ (auto-calculated)
```

---

## 🔐 SECURITY & PERMISSIONS

**Important:** Some fields should only be visible/editable by certain roles:

- **Sales Agents:** Can enter customer data, incidentals, reservation fee
- **Admin Only:** Dealer incentives, SFM retain, expenses, profit/loss calculations
- **Auto-calculated fields:** Should be read-only (calculated by backend)

---

## 📖 REAL-WORLD EXAMPLE: Complete Order Creation Flow

### **Scenario:** Customer wants to buy a Mitsubishi Montero Sport with financing

**Customer:** Juan Dela Cruz
**Vehicle:** Montero Sport GLS 4x2 AT
**Sales Agent:** Maria Santos
**Payment Method:** Financing (36 months)

### **Step-by-Step Calculation:**

#### **1. Unit Pricing (Formula #1)**
```
Base Price (SRP):           ₱1,500,000
Body Package:               ₱   50,000
Aircon Package:             ₱   30,000
White Color Surcharge:      ₱   10,000
Other Charges:              ₱        0
─────────────────────────────────────
Total Unit Price:           ₱1,590,000
```

#### **2. Discounts & Invoice Amount (Formulas #2, #5)**
```
Total Unit Price:           ₱1,590,000
Nominal Discount:           ₱  -20,000
Promo Discount:             ₱  -30,000
─────────────────────────────────────
Amount to be Invoiced:      ₱1,540,000
Additional Discount:        ₱   50,000 (sum of both discounts)
```

#### **3. Financing Breakdown (Formulas #3, #4, #6)**
```
Total Unit Price:           ₱1,590,000
Finance Percentage:         80%
Down Payment Percentage:    20%

Amount to Finance:          ₱1,272,000 (1,590,000 × 80%)
Down Payment:               ₱  318,000 (1,590,000 × 20%)
Additional Discount:        ₱  -50,000
─────────────────────────────────────
Net Down Payment:           ₱  268,000
```

#### **4. Incidentals (Formula #7)**
```
Insurance Premium:          ₱   25,000
CPTL Premium:               ₱    5,000
LTO Registration:           ₱   15,000
Chattel Mortgage Fee:       ₱   10,000
Chattel Income:             ₱        0
Extended Warranty (2yr):    ₱   20,000
─────────────────────────────────────
Total Incidentals:          ₱   75,000
```

#### **5. Customer Cash Requirement (Formula #8)**
```
Net Down Payment:           ₱  268,000
Total Incidentals:          ₱   75,000
Reservation Fee:            ₱  -10,000
─────────────────────────────────────
TOTAL CASH OUTLAY:          ₱  333,000 ← Customer pays this upfront
```

#### **6. Monthly Payment (PaymentCalculator)**
```
Amount to Finance:          ₱1,272,000
Financing Term:             36 months
Interest Rate:              13.5% per annum
─────────────────────────────────────
Monthly Payment:            ₱   43,200 (approx, calculated by PaymentCalculator)
```

#### **7. Dealer Incentives (Formulas #9, #10) - Admin Only**
```
Amount to Finance:          ₱1,272,000
Dealer Incentive %:         3%

Gross Dealer Incentive:     ₱   38,160 (1,272,000 × 3%)
SFM Retain:                 ₱   -5,000
─────────────────────────────────────
Net Dealer Incentive:       ₱   33,160
```

#### **8. Expenses & Profitability (Formulas #11, #12, #13) - Admin Only**
```
Nominal Discount:           ₱   20,000
Promo Discount:             ₱   30,000
SFM Additional:             ₱        0
Incidentals:                ₱   75,000
Tipster Fee:                ₱    5,000
Accessories Cost:           ₱   10,000
Other Expenses:             ₱    2,000
─────────────────────────────────────
Total Expenses:             ₱  142,000

Gross & SFM Total:          ₱   43,160 (38,160 + 5,000)
Total Expenses:             ₱ -142,000
─────────────────────────────────────
Gross vs Net Balance:       ₱  -98,840

Total Expenses:             ₱  142,000
SE Share:                   ₱   10,000
─────────────────────────────────────
Net Negative (Profit/Loss): ₱ -152,000 ← Company loses money on this deal
```

### **Data Flow Through System:**

**1. Frontend (orders.php):**
- Sales agent enters all values in form
- JavaScript calculates in real-time and displays results
- Agent sees Total Cash Outlay: ₱333,000 to inform customer

**2. Backend (create_order.php):**
- Receives form data via POST
- Calls `FinancialCalculator->calculateAll()` to validate and compute all formulas
- Calls `PaymentCalculator->calculatePlan()` to get monthly payment
- Inserts all calculated values into `orders` table
- Generates payment schedule in `payment_schedule` table

**3. Database (orders table):**
```sql
INSERT INTO orders (
    order_number, customer_id, vehicle_id, sales_agent_id,
    base_price, total_unit_price, nominal_discount, promo_discount,
    amount_to_invoice, amount_finance, down_payment, net_down_payment,
    insurance_premium, cptl_premium, lto_registration, chattel_mortgage_fee,
    extended_warranty, total_incidentals, reservation_fee, total_cash_outlay,
    gross_dealer_incentive_pct, gross_dealer_incentive, sfm_retain,
    net_dealer_incentive, tipster_fee, accessories_cost, other_expenses,
    total_expenses, net_negative, financing_term, monthly_payment, ...
) VALUES (
    'ORD-2025-001', 123, 456, 789,
    1500000, 1590000, 20000, 30000,
    1540000, 1272000, 318000, 268000,
    25000, 5000, 15000, 10000,
    20000, 75000, 10000, 333000,
    3.00, 38160, 5000,
    33160, 5000, 10000, 2000,
    142000, -152000, 36, 43200, ...
);
```

**4. Display (transaction-records.php):**
- Admin views transaction
- Sees complete financial breakdown
- Sees Net Negative: -₱152,000 (loss)
- Can analyze why deal was unprofitable

**5. Reporting (sales-report.php):**
- Aggregates all orders
- Shows total revenue, total expenses, total profit/loss
- Shows commission earned per agent
- Helps management make pricing decisions

---

## ✅ TESTING CHECKLIST

After implementation, test with the above scenario and verify:

**Expected Database Values:**
1. ✅ Total Unit Price = ₱1,590,000
2. ✅ Amount to Invoice = ₱1,540,000
3. ✅ Amount Finance = ₱1,272,000
4. ✅ Down Payment = ₱318,000
5. ✅ Net Down Payment = ₱268,000
6. ✅ Total Incidentals = ₱75,000
7. ✅ Total Cash Outlay = ₱333,000
8. ✅ Gross Dealer Incentive = ₱38,160
9. ✅ Net Dealer Incentive = ₱33,160
10. ✅ Total Expenses = ₱142,000
11. ✅ Net Negative = -₱152,000

**Expected UI Behavior:**
- ✅ All calculated fields update in real-time as user types
- ✅ Total Cash Outlay is prominently displayed (yellow highlight)
- ✅ Dealer/Expense section only visible to Admin users
- ✅ Transaction details modal shows complete financial breakdown
- ✅ Sales report shows commission and profit metrics

---

## 📞 SUMMARY

**What:** Implement 13 financial calculation formulas
**Where:** Database, Backend Calculator, Forms, APIs, Display Pages
**Why:** Client needs detailed financial breakdown for vehicle sales
**Priority:** Database first, then calculator, then forms, then integration

**Files to Create:**
- `includes/database/add_financial_fields.sql`
- `includes/financial_calculator.php`

**Files to Modify:**
- `pages/main/orders.php`
- `includes/api/create_order.php`
- `api/loan-applications.php`
- `pages/main/transaction-records.php`
- `pages/order_details.php`

---

**Need help with implementation? Start with Phase 1 (Database) and let me know when you're ready for Phase 2!**


