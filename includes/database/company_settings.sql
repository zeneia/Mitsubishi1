-- Company Settings Table
-- Stores general company information displayed on the website
CREATE TABLE IF NOT EXISTS company_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50) DEFAULT 'text',
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT DEFAULT NULL,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default company settings
INSERT INTO company_settings (setting_key, setting_value, setting_type, description) VALUES
('company_name', 'Mitsubishi Motors San Pablo', 'text', 'Company name displayed on website'),
('company_address', 'Km 85.5 Maharlika Highway, Brgy.San Ignacio', 'text', 'Street address'),
('company_city', 'San Pablo City', 'text', 'City'),
('company_postal_code', '4000', 'text', 'Postal/ZIP code'),
('company_province', 'Laguna', 'text', 'Province/State'),
('company_country', 'Philippines', 'text', 'Country'),
('company_phone', '+63(000 - 000 - 000)', 'text', 'Primary phone number'),
('company_email', 'mitsubishi@gmail.com', 'email', 'Primary email address'),
('company_facebook', 'https://facebook.com/MitsubishiMotorsSanPablo', 'url', 'Facebook page URL'),
('business_hours_weekday', 'Mon-Sat: 8:00 AM - 6:00 PM', 'text', 'Weekday business hours'),
('business_hours_weekend', 'Sunday: 9:00 AM - 5:00 PM', 'text', 'Weekend business hours')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

