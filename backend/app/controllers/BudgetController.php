<?php

require_once __DIR__ . '/../models/Budget.php';
require_once __DIR__ . '/../helpers/Auth.php';  

class BudgetController {
    private $budgetModel;

    public function __construct() {
        $this->budgetModel = new Budget();
    }

    // -----------------------
    // Set Budget 
    // -----------------------
    public function setBudget() {
        header("Content-Type: application/json");

        $userData = Auth::verifyToken();
        if (!$userData) {
            echo json_encode(["success" => false, "message" => "Not authenticated"]);
            return;
        }
        $userId = $userData['id'];

        $data = json_decode(file_get_contents("php://input"), true);

        // ✅ Updated validation for separate income
        if (!$data || !isset($data['income']) || !isset($data['categories']) || !is_array($data['categories'])) {
            echo json_encode(["success" => false, "message" => "Invalid request data"]);
            return;
        }

        $monthStart = date('Y-m-01');

        // Check if budget already set this month
        if ($this->budgetModel->budgetExistsThisMonth($userId)) {
            echo json_encode(["success" => false, "message" => "Budget already set for this month"]);
            return;
        }

        // Insert income into monthly_income table
        $income = floatval($data['income']);
        $successIncome = $this->budgetModel->insertIncome($userId, $monthStart, $income);
        if (!$successIncome) {
            echo json_encode(["success" => false, "message" => "Failed to save income"]);
            return;
        }

        // Check duplicate expense categories
        $categoryNames = array_map(fn($c) => strtolower(trim($c['category'])), $data['categories']);
        if (count($categoryNames) !== count(array_unique($categoryNames))) {
            echo json_encode(["success" => false, "message" => "Duplicate categories found in submission"]);
            return;
        }

        // Insert expense categories into budgets table
        $successExpenses = $this->budgetModel->insertBudget($userId, $data['categories']);
        if ($successExpenses) {
                $this->budgetModel->updateActualSavings($userId, $monthStart);

            echo json_encode(["success" => true, "message" => "Budget set successfully"]);
        } else {
            echo json_encode(["success" => false, "message" => "Database error saving budget"]);
        }
    }

    // -----------------------
    // Get month budget data (income + expenses + savings)
    // -----------------------
    public function getMonthBudgetData() {
        header("Content-Type: application/json");

        $userData = Auth::verifyToken();
        if (!$userData || !isset($userData['id'])) {
            echo json_encode(["success" => false, "message" => "Not authenticated"]);
            return;
        }
        $userId = $userData['id'];
        $monthStart = date('Y-m-01');

        $defaultCategories = ["Rent", "Food", "Utilities", "Transportation"];

        // Fetch income from monthly_income table
        $income = $this->budgetModel->getIncomeForMonth($userId, $monthStart);

        // Fetch expenses from budgets table
        $budgetItems = $this->budgetModel->getBudgetForMonth($userId, $monthStart);
        $categoryMap = [];
        $expensesTotal = 0.0;
        foreach ($budgetItems as $item) {
            $cat = trim($item['category']);
            $amt = (float)$item['amount'];
            $categoryMap[$cat] = $amt;
            $expensesTotal += $amt;
        }

        // Ensure default categories exist
        foreach ($defaultCategories as $cat) {
            if (!isset($categoryMap[$cat])) $categoryMap[$cat] = 0.0;
        }

        // Calculate savings
        $savings = max(0, $income - $expensesTotal);

        // Build final categories array (expenses only, plus savings)
        $finalCategories = [];
        foreach ($categoryMap as $name => $amount) {
            $finalCategories[] = ["name" => $name, "amount" => $amount];
        }
        $finalCategories[] = ["name" => "Savings", "amount" => $savings];

        $monthLabel = date('F Y');

        echo json_encode([
            "success" => true,
            "month" => $monthLabel,
            "income" => $income,
            "savings" => $savings,
            "categories" => $finalCategories
        ]);
    }

    // -----------------------
    // Add Expense 
    // -----------------------
public function addExpense() {
    header("Content-Type: application/json");

    $userData = Auth::verifyToken();
    if (!$userData || !isset($userData['id'])) {
        echo json_encode(["success" => false, "message" => "Not authenticated"]);
        return;
    }
    $userId = $userData['id'];

    $data = json_decode(file_get_contents("php://input"), true);
    $category = trim($data['category'] ?? '');
    $subCategory = trim($data['subCategory'] ?? '');
    $amount = floatval($data['amount'] ?? 0);
    $notes = trim($data['notes'] ?? '');

    if (!$category || $amount <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid category or amount"]);
        return;
    }

    if (strtolower($category) === "others") {
        if (!$subCategory) {
            echo json_encode(["success" => false, "message" => "Subcategory required for 'Others'"]);
            return;
        }
        $category = $subCategory;
    }

    $monthStart = date('Y-m-01');

    // Check if category already exists for this month
    $existingBudget = $this->budgetModel->getBudgetForMonthCategory($userId, $monthStart, $category);
    if ($existingBudget) {
        $newAmount = $existingBudget['amount'] + $amount;
        $success = $this->budgetModel->updateBudgetAmount($userId, $monthStart, $category, $newAmount);
    } else {
        $success = $this->budgetModel->insertBudget($userId, [['category' => $category, 'amount' => $amount]]);
    }

    if ($success) {
        $income = $this->budgetModel->getIncomeForMonth($userId, $monthStart);
        $budgetItems = $this->budgetModel->getBudgetForMonth($userId, $monthStart);
        $expensesTotal = 0.0;
        foreach ($budgetItems as $item) $expensesTotal += (float)$item['amount'];
        $savings = $income - $expensesTotal; // allow negative savings

        // Build notification message including notes if provided
        $notifMessage = "Expense of ₦{$amount} added to {$category}";
        if ($notes) {
            $notifMessage .= " Notes: {$notes}";
        }

        
$this->budgetModel->insertNotification($userId, 'expense', $notifMessage, $savings,$amount, $category);


        echo json_encode([
            "success" => true,
            "message" => $notifMessage,
            "adjustedSavings" => $savings
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Failed to save expense"
        ]);
    }
}


// -----------------------
// Add Income 
// -----------------------
public function addIncome() {
    header("Content-Type: application/json");

    $userData = Auth::verifyToken();
    if (!$userData || !isset($userData['id'])) {
        echo json_encode(["success" => false, "message" => "Not authenticated"]);
        return;
    }
    $userId = $userData['id'];

    $data = json_decode(file_get_contents("php://input"), true);
    $amount = floatval($data['amount'] ?? 0);
    $notes = trim($data['notes'] ?? '');

    if ($amount <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid income amount"]);
        return;
    }

    $monthStart = date('Y-m-01');

    // Get existing income for the month
    $existingIncome = $this->budgetModel->getIncomeForMonth($userId, $monthStart);
    $newIncome = $existingIncome + $amount;

    // Insert or update income
    $success = $this->budgetModel->insertIncome($userId, $monthStart, $newIncome);

    if ($success) {
        $budgetItems = $this->budgetModel->getBudgetForMonth($userId, $monthStart);
        $expensesTotal = 0.0;
        foreach ($budgetItems as $item) $expensesTotal += (float)$item['amount'];
        $savings = $newIncome - $expensesTotal; // allow negative savings

        // Build notification message including notes if provided
        $notifMessage = "Income of ₦{$amount} added!";
        if ($notes) {
            $notifMessage .= " Notes: {$notes}";
        }

     // Build notification message including notes if provided
$notifMessage = "Income of ₦{$amount} added!";
if ($notes) {
    $notifMessage .= " Notes: {$notes}";
}

$this->budgetModel->insertNotification(
    $userId,
    'income',
    $notifMessage,
    $savings,
    $amount
);

        echo json_encode([
            "success" => true,
            "message" => $notifMessage,
            "adjustedSavings" => $savings
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Failed to save income"
        ]);
    }
}


    // -----------------------
    // Notifications
    // -----------------------
   public function getNotifications() {
    header("Content-Type: application/json");

    $userData = Auth::verifyToken();
    if (!$userData || !isset($userData['id'])) {
        echo json_encode(["success" => false, "message" => "Not authenticated"]);
        return;
    }
    $userId = $userData['id'];

  
    $monthStart = date('Y-m-01');

    $notifications = $this->budgetModel->getNotifications($userId, $monthStart);

    echo json_encode([
        "success" => true,
        "notifications" => $notifications
    ]);
}


public function getChartData() {
    header("Content-Type: application/json");

    $userData = Auth::verifyToken();
    if (!$userData || !isset($userData['id'])) {
        echo json_encode(["success" => false, "message" => "Not authenticated"]);
        return;
    }
    $userId = $userData['id'];

    $chartData = $this->budgetModel->getChartData($userId);

    if ($chartData) {
        echo json_encode([
            "success" => true,
            "data" => $chartData
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "No chart data found"
        ]);
    }
}

// -----------------------
// Get Monthly Summary
// -----------------------
public function getMonthlySummary() {
    header("Content-Type: application/json");

    $userData = Auth::verifyToken();
    if (!$userData || !isset($userData['id'])) {
        echo json_encode(["success" => false, "message" => "Not authenticated"]);
        return;
    }

    $userId = $userData['id'];

    $month = $_GET['month'] ?? date('Y-m-01');

    $summary = $this->budgetModel->getMonthlySummary($userId, $month);

    if ($summary) {
        echo json_encode([
            "success" => true,
            "data" => $summary
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "No summary data available"
        ]);
    }
}


}
