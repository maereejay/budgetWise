# 💸 BudgetWise - Personal Finance Dashboard

For anyone who’s ever asked, **“Where did all my money go?”** — this website has the receipts. 📄💰  

BudgetWise is a full-stack **React + PHP + MySQL** personal finance dashboard that tracks your income, expenses, savings, and even gives monthly summaries and visual charts. It’s like a diary for your wallet! 🗓️📊

---

## Features

- **Monthly Summaries:** See your highest and lowest expenses for each month.  
- **Expense Breakdown:** Categorized spending (Transportation, Food, Utilities, etc.)  
- **Visual Charts:** Pie charts and graphs to spot patterns across months  
- **Income vs Actual Savings:** Understand your financial health at a glance  
- **Notifications:** Get notified whenever you log income or expenses, with optional notes  

---

## Tech Stack

- **Frontend:** React with live charts and dynamic monthly summaries  
- **Backend:** PHP with JWT authentication for secure sessions  
- **Database:** MySQL storing user budgets, monthly income, and notifications  

---

## Getting Started

Follow these steps to download, set up, and run the project locally. **Everything you need is included below.**

---

### 1. Clone the Repository
```bash
git clone https://github.com/yourusername/BudgetWise.git
cd BudgetWise
```
### 2. **Database Setup** 🗃️

Set up the MySQL database and tables for BudgetWise. Run the following SQL commands in your MySQL client or phpMyAdmin:

```sql
-- Create database
CREATE DATABASE **budgetWise** CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE budgetWise;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Budgets table
CREATE TABLE budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    budget_month DATE NOT NULL,               -- e.g. 2025-08-01
    category VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_month_category (user_id, budget_month, category)
);

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    category VARCHAR(100) NULL,          -- nullable for income
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    adjusted_savings DECIMAL(10,2) NOT NULL DEFAULT 0,
    message TEXT NULL,                    -- optional message
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Monthly Income table
CREATE TABLE monthly_income (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    budget_month DATE NOT NULL,
    income DECIMAL(10,2) NOT NULL,
    expected_savings DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, budget_month)
);
```
## Using the App
- Register a new user to start tracking your finances.

- Set your monthly budget and income for each category.

- Add income and expenses as they happen. Each transaction generates a notification, so you never forget what you logged.

- View monthly summaries to see highest and lowest expenses, adjusted savings, and income.

- Visualize your spending using charts to quickly understand patterns.

💡 Note: JWT tokens expire after 1 hour 30 minutes. Adjusted savings are always non-negative
