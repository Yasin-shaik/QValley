-- Create database
CREATE DATABASE IF NOT EXISTS quantumsafe;
USE quantumsafe;

-- =========================================================
-- Table for Common Man (End-user) Analysis Logs
-- =========================================================
DROP TABLE IF EXISTS common_analysis;
CREATE TABLE common_analysis (
  id INT AUTO_INCREMENT PRIMARY KEY,
  feature VARCHAR(50) NOT NULL,       -- Which feature was used (screenshot/chatbot/microfraud)
  input_value TEXT,                   -- User input (screenshot name, UPI ID, chat text, etc.)
  score INT NOT NULL,                 -- Trust Score (0–100)
  verdict ENUM('SAFE','SUSPICIOUS','FRAUD') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================================================
-- Table for Bank/FinTech Transactions
-- =========================================================
DROP TABLE IF EXISTS bank_transactions;
CREATE TABLE bank_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account VARCHAR(100) NOT NULL,      -- Account number / ID
  payee VARCHAR(100) NOT NULL,        -- Payee name / UPI ID
  amount DECIMAL(12,2) NOT NULL,      -- Transaction amount
  score INT NOT NULL,                 -- Trust Score (0–100)
  verdict ENUM('SAFE','SUSPICIOUS','FRAUD') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================================================
-- Insert some dummy demo data (optional)
-- =========================================================
INSERT INTO common_analysis (feature, input_value, score, verdict) VALUES
('screenshot', 'fake_upi_payment.png', 25, 'FRAUD'),
('chatbot', 'User asked: Should I send ₹500 to random@upi', 40, 'SUSPICIOUS'),
('microfraud', 'Repeated ₹50 payments to xyz@upi', 60, 'SUSPICIOUS'),
('chatbot', 'User asked: Pay electricity bill to trusted@upi', 90, 'SAFE');

INSERT INTO bank_transactions (account, payee, amount, score, verdict) VALUES
('ACC1234', 'frauduser@upi', 2000.00, 30, 'FRAUD'),
('ACC5678', 'trustedshop@upi', 999.00, 85, 'SAFE'),
('ACC9999', 'scammer@upi', 50.00, 45, 'SUSPICIOUS'),
('ACC8888', 'employee@upi', 1200.00, 92, 'SAFE');
