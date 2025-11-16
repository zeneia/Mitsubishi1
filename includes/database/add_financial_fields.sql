-- ============================================================================
-- MITSUBISHI DEALERSHIP SYSTEM - FINANCIAL FIELDS MIGRATION
-- ============================================================================
-- This migration adds comprehensive financial tracking fields to support
-- the 13 financial formulas from the System Revision Implementation Guide
-- ============================================================================

-- ============================================================================
-- PART 1: ALTER VEHICLES TABLE
-- ============================================================================
-- Add optional unit cost fields for enhanced pricing calculations

ALTER TABLE vehicles
ADD COLUMN IF NOT EXISTS body_package_price DECIMAL(12,2) DEFAULT 0 COMMENT 'Optional body package cost',
ADD COLUMN IF NOT EXISTS aircon_package_price DECIMAL(12,2) DEFAULT 0 COMMENT 'Optional air conditioning package',
ADD COLUMN IF NOT EXISTS white_color_surcharge DECIMAL(12,2) DEFAULT 0 COMMENT 'Additional charge for white color',
ADD COLUMN IF NOT EXISTS other_charges DECIMAL(12,2) DEFAULT 0 COMMENT 'Other miscellaneous charges';

-- ============================================================================
-- PART 2: ALTER ORDERS TABLE
-- ============================================================================
-- Add comprehensive financial tracking fields for all 13 formulas

-- Pricing Breakdown Fields (Formulas #1, #2, #5)
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS total_unit_price DECIMAL(12,2) DEFAULT 0 COMMENT 'SRP + all add-ons (Formula #1)',
ADD COLUMN IF NOT EXISTS nominal_discount DECIMAL(12,2) DEFAULT 0 COMMENT 'Standard discount',
ADD COLUMN IF NOT EXISTS promo_discount DECIMAL(12,2) DEFAULT 0 COMMENT 'Promotional discount',
ADD COLUMN IF NOT EXISTS amount_to_invoice DECIMAL(12,2) DEFAULT 0 COMMENT 'Final invoiced amount (Formula #2)';

-- Financing Fields (Formulas #3, #4, #6)
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS amount_finance DECIMAL(12,2) DEFAULT 0 COMMENT 'Amount to be financed (Formula #3)',
ADD COLUMN IF NOT EXISTS finance_percentage DECIMAL(5,2) DEFAULT 0 COMMENT 'Percentage financed',
ADD COLUMN IF NOT EXISTS down_payment_percentage DECIMAL(5,2) DEFAULT 0 COMMENT 'Down payment percentage',
ADD COLUMN IF NOT EXISTS net_down_payment DECIMAL(12,2) DEFAULT 0 COMMENT 'Down payment after discounts (Formula #6)';

-- Incidentals - Government & Service Fees (Formula #7)
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS insurance_premium DECIMAL(12,2) DEFAULT 0 COMMENT 'Vehicle insurance cost',
ADD COLUMN IF NOT EXISTS cptl_premium DECIMAL(12,2) DEFAULT 0 COMMENT 'Compulsory third-party liability',
ADD COLUMN IF NOT EXISTS lto_registration DECIMAL(12,2) DEFAULT 0 COMMENT 'LTO registration fee',
ADD COLUMN IF NOT EXISTS chattel_mortgage_fee DECIMAL(12,2) DEFAULT 0 COMMENT 'Chattel mortgage processing fee',
ADD COLUMN IF NOT EXISTS chattel_income DECIMAL(12,2) DEFAULT 0 COMMENT 'Chattel income',
ADD COLUMN IF NOT EXISTS extended_warranty DECIMAL(12,2) DEFAULT 0 COMMENT '2-year extended warranty cost',
ADD COLUMN IF NOT EXISTS total_incidentals DECIMAL(12,2) DEFAULT 0 COMMENT 'Sum of all incidentals (Formula #7)';

-- Customer Payment Fields (Formula #8)
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS reservation_fee DECIMAL(12,2) DEFAULT 0 COMMENT 'Reservation fee paid',
ADD COLUMN IF NOT EXISTS total_cash_outlay DECIMAL(12,2) DEFAULT 0 COMMENT 'Final cash required from customer (Formula #8)';

-- Dealer & Expense Tracking - Admin Only (Formulas #9, #10, #11, #12, #13)
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS gross_dealer_incentive_pct DECIMAL(5,2) DEFAULT 0 COMMENT 'Dealer incentive percentage',
ADD COLUMN IF NOT EXISTS gross_dealer_incentive DECIMAL(12,2) DEFAULT 0 COMMENT 'Gross dealer incentive amount (Formula #9)',
ADD COLUMN IF NOT EXISTS sfm_retain DECIMAL(12,2) DEFAULT 0 COMMENT 'SFM retained amount',
ADD COLUMN IF NOT EXISTS sfm_additional DECIMAL(12,2) DEFAULT 0 COMMENT 'SFM additional charges',
ADD COLUMN IF NOT EXISTS net_dealer_incentive DECIMAL(12,2) DEFAULT 0 COMMENT 'Net dealer incentive after retain (Formula #10)',
ADD COLUMN IF NOT EXISTS tipster_fee DECIMAL(12,2) DEFAULT 0 COMMENT 'Tipster/referral fee',
ADD COLUMN IF NOT EXISTS accessories_cost DECIMAL(12,2) DEFAULT 0 COMMENT 'Accessories cost',
ADD COLUMN IF NOT EXISTS other_expenses DECIMAL(12,2) DEFAULT 0 COMMENT 'Other expenses',
ADD COLUMN IF NOT EXISTS se_share DECIMAL(12,2) DEFAULT 0 COMMENT 'Sales Executive share',
ADD COLUMN IF NOT EXISTS total_expenses DECIMAL(12,2) DEFAULT 0 COMMENT 'Total expenses (Formula #11)',
ADD COLUMN IF NOT EXISTS gross_net_balance DECIMAL(12,2) DEFAULT 0 COMMENT 'Gross vs Net summary (Formula #12)',
ADD COLUMN IF NOT EXISTS net_negative DECIMAL(12,2) DEFAULT 0 COMMENT 'Net profit/loss (Formula #13)';

-- ============================================================================
-- MIGRATION NOTES
-- ============================================================================
-- 1. All new fields default to 0 to maintain backward compatibility
-- 2. Existing orders will have these fields set to 0
-- 3. The existing discount_amount and total_price fields are kept for compatibility
-- 4. New orders should use the enhanced financial calculation system
-- 5. IF NOT EXISTS clause prevents errors if migration is run multiple times
-- ============================================================================

-- Verify migration
SELECT 'Migration completed successfully. New financial fields added to vehicles and orders tables.' AS status;

