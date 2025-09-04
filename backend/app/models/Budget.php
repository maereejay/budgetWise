<?php
// app/models/Budget.php
require_once __DIR__ . '/../../config/database.php';

class Budget {
    private $conn;

    public function __construct() {
        $this->conn = connectDB();
    }

    // -----------------------------
    // Check if user has budget this month
    // -----------------------------
    public function budgetExistsThisMonth($userId) {
        $monthStart = date('Y-m-01');
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM budgets WHERE user_id = ? AND budget_month = ?");
        $stmt->bind_param("is", $userId, $monthStart);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['count'] > 0;
    }

    // -----------------------------
    // Insert multiple expense categories
    // -----------------------------
    public function insertBudget($userId, $categories) {
        $monthStart = date('Y-m-01');
        $stmt = $this->conn->prepare("INSERT INTO budgets (user_id, budget_month, category, amount) VALUES (?, ?, ?, ?)");

        foreach ($categories as $cat) {
            $category = trim($cat['category']);
            $amount = floatval($cat['amount']);
            $stmt->bind_param("issd", $userId, $monthStart, $category, $amount);
            if (!$stmt->execute()) return false;
        }
        return true;
    }

// -----------------------------
// Insert or update income for the month
// -----------------------------
public function insertIncome($userId, $monthStart, $amount) {
    // Check if income already exists
    $stmt = $this->conn->prepare(
        "SELECT id FROM monthly_income WHERE user_id = ? AND budget_month = ?"
    );
    $stmt->bind_param("is", $userId, $monthStart);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result) {
        // Update existing income
        $stmtUpdate = $this->conn->prepare(
            "UPDATE monthly_income SET income = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        $stmtUpdate->bind_param("di", $amount, $result['id']);
        return $stmtUpdate->execute();
    } else {
        // Insert new income
        $stmtInsert = $this->conn->prepare(
            "INSERT INTO monthly_income (user_id, budget_month, income) VALUES (?, ?, ?)"
        );
        $stmtInsert->bind_param("isd", $userId, $monthStart, $amount);
        return $stmtInsert->execute();
    }
}

// -----------------------------
// Get income for month
// -----------------------------
public function getIncomeForMonth($userId, $monthStart) {
    $stmt = $this->conn->prepare(
        "SELECT income FROM monthly_income WHERE user_id = ? AND budget_month = ?"
    );
    $stmt->bind_param("is", $userId, $monthStart);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result ? (float)$result['income'] : 0.0;
}


    // -----------------------------
    // Fetch all budget items for a month
    // -----------------------------
    public function getBudgetForMonth($userId, $month) {
        $stmt = $this->conn->prepare(
            "SELECT category, amount 
             FROM budgets 
             WHERE user_id = ? AND budget_month = ?"
        );
        $stmt->bind_param("is", $userId, $month);
        $stmt->execute();
        $result = $stmt->get_result();

        $budgetItems = [];
        while ($row = $result->fetch_assoc()) {
            $budgetItems[] = [
                "category" => $row['category'],
                "amount" => (float)$row['amount']
            ];
        }
        return $budgetItems;
    }

    // -----------------------------
    // Fetch a single category for a month
    // -----------------------------
    public function getBudgetForMonthCategory($userId, $monthStart, $category) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM budgets WHERE user_id = ? AND budget_month = ? AND category = ?"
        );
        $stmt->bind_param("iss", $userId, $monthStart, $category);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // -----------------------------
    // Update budget amount for a category
    // -----------------------------
    public function updateBudgetAmount($userId, $monthStart, $category, $newAmount) {
        $stmt = $this->conn->prepare(
            "UPDATE budgets SET amount = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE user_id = ? AND budget_month = ? AND category = ?"
        );
        $stmt->bind_param("diss", $newAmount, $userId, $monthStart, $category);
        return $stmt->execute();
    }

    // -----------------------------
    // Insert notification
    // -----------------------------
  public function insertNotification($userId, $type, $message, $adjustedSavings, $amount, $category = null) {
    $stmt = $this->conn->prepare(
        "INSERT INTO notifications (user_id, type, message, adjusted_savings, category,amount) VALUES (?, ?, ?, ?, ?,?)"
    );
    $stmt->bind_param("issdsd", $userId, $type, $message, $adjustedSavings, $category, $amount);
    return $stmt->execute();
}


    // -----------------------------
    // Get latest 20 notifications
    // -----------------------------
   public function getNotifications($userId, $monthStart) {
    $stmt = $this->conn->prepare(
        "SELECT id, type, category, message, adjusted_savings, created_at 
         FROM notifications 
         WHERE user_id = ? 
           AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
         ORDER BY created_at DESC"
    );
    $stmt->bind_param("is", $userId, $monthStart);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            "id" => $row['id'],
            "type" => $row['type'],
            "category" => $row['category'],
            "message" => $row['message'],
            "adjustedSavings" => (float)$row['adjusted_savings'],
            "createdAt" => $row['created_at']
        ];
    }
    return $notifications;
}


    public function updateActualSavings($userId, $monthStart) {
    // 1. Get income
    $stmt = $this->conn->prepare(
        "SELECT income FROM monthly_income WHERE user_id = ? AND budget_month = ?"
    );
    $stmt->bind_param("is", $userId, $monthStart);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        return false; // No income found for this month
    }

    $income = floatval($result['income']);

    // 2. Sum all expenses for this month
    $stmtExp = $this->conn->prepare(
        "SELECT COALESCE(SUM(amount), 0) AS total_expenses 
         FROM budgets 
         WHERE user_id = ? AND budget_month = ?"
    );
    $stmtExp->bind_param("is", $userId, $monthStart);
    $stmtExp->execute();
    $expenseResult = $stmtExp->get_result()->fetch_assoc();

    $totalExpenses = floatval($expenseResult['total_expenses']);

    // 3. Calculate actual savings
    $actualSavings = $income - $totalExpenses;

    // 4. Update monthly_income
    $stmtUpdate = $this->conn->prepare(
        "UPDATE monthly_income 
         SET actual_savings = ?, updated_at = CURRENT_TIMESTAMP
         WHERE user_id = ? AND budget_month = ?"
    );
    $stmtUpdate->bind_param("dis", $actualSavings, $userId, $monthStart);
    return $stmtUpdate->execute();
}

// Get per-month expenses by category for a given year
public function getChartData($userId) {
    // Fixed 12 months array
    $allMonths = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];

    // Prepare default arrays
    $savingsData = [
        "expected" => array_fill(0, 12, null),
        "actual" => array_fill(0, 12, null)
    ];
    $expensesData = [];

    // ---- Get savings (income + expected_savings) ----
    $stmt = $this->conn->prepare("
        SELECT 
            MONTH(budget_month) AS month_num,
            income,
            expected_savings
        FROM monthly_income
        WHERE user_id = ?
        ORDER BY budget_month ASC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $incomeResult = $stmt->get_result();

    while ($row = $incomeResult->fetch_assoc()) {
        $monthIndex = (int)$row['month_num'] - 1; // Jan=0, Feb=1 ...
        $savingsData["expected"][$monthIndex] = (float)$row['expected_savings'];
        $savingsData["actual"][$monthIndex]   = (float)$row['income']; 
    }

    // ---- Get expenses ----
    $stmt2 = $this->conn->prepare("
        SELECT 
            MONTH(bc.budget_month) AS month_num,
            bc.category,
            bc.amount
        FROM budgets bc
        WHERE bc.user_id = ?
        ORDER BY bc.budget_month ASC
    ");
    $stmt2->bind_param("i", $userId);
    $stmt2->execute();
    $expenseResult = $stmt2->get_result();

    while ($row = $expenseResult->fetch_assoc()) {
        $category = $row['category'];
        $monthIndex = (int)$row['month_num'] - 1;
        $amount = (float)$row['amount'];

        if (!isset($expensesData[$category])) {
            $expensesData[$category] = array_fill(0, 12, null);
        }
        $expensesData[$category][$monthIndex] = $amount;
    }

    return [
        "months" => $allMonths,
        "expenses" => $expensesData,
        "savings" => $savingsData
    ];
}


// -----------------------------
// Get Monthly Summary
// -----------------------------
public function getMonthlySummary($userId, $monthStart) {
    // 1. Get planned income + expected savings
    $stmt = $this->conn->prepare("
        SELECT income, expected_savings 
        FROM monthly_income 
        WHERE user_id = ? AND budget_month = ?
    ");
    $stmt->bind_param("is", $userId, $monthStart);
    $stmt->execute();
    $incomeRow = $stmt->get_result()->fetch_assoc();

    $plannedIncome = $incomeRow ? (float)$incomeRow['income'] : 0.0;
    $expectedSavings = $incomeRow ? (float)$incomeRow['expected_savings'] : 0.0;

    // 2. Get extra incomes (notifications)
    $stmt = $this->conn->prepare("
        SELECT amount, message 
        FROM notifications 
        WHERE user_id = ? 
          AND type = 'income' 
          AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
    ");
    $stmt->bind_param("is", $userId, $monthStart);
    $stmt->execute();
    $extraIncomeResult = $stmt->get_result();

    $extraIncomeTotal = 0.0;
    $incomeNotes = [];
    while ($row = $extraIncomeResult->fetch_assoc()) {
        $extraIncomeTotal += (float)$row['amount'];
        if (!empty($row['message'])) {
            $incomeNotes[] = $row['message'];
        }
    }

    // 3. Get expenses (grouped by category)
    $stmt = $this->conn->prepare("
        SELECT category, SUM(amount) as total, COUNT(*) as count
        FROM notifications
        WHERE user_id = ?
          AND type = 'expense'
          AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
        GROUP BY category
    ");
    $stmt->bind_param("is", $userId, $monthStart);
    $stmt->execute();
    $expenseResult = $stmt->get_result();

    $expenseBreakdown = [];
    $totalExpenses = 0.0;
    $highestCategory = null;
    $lowestCategory = null;
    $highestValue = -INF;
    $lowestValue = INF;
    $extraCategoryStats = [];

    while ($row = $expenseResult->fetch_assoc()) {
        $category = $row['category'];
        $total = (float)$row['total'];
        $count = (int)$row['count'];

        $expenseBreakdown[$category] = $total;
        $totalExpenses += $total;

        // track highest/lowest
        if ($total > $highestValue) {
            $highestValue = $total;
            $highestCategory = $category;
        }
        if ($total < $lowestValue) {
            $lowestValue = $total;
            $lowestCategory = $category;
        }

        // track category additions
        $extraCategoryStats[] = "$category ($count times, total $" . number_format($total, 2) . ")";
    }

    // 4. Calculate actual savings
    $totalIncome = $plannedIncome + $extraIncomeTotal;
    $actualSavings = $totalIncome - $totalExpenses;

    // 5. Build structured summary text
    $summaryParts = [];
    $monthName = date("F Y", strtotime($monthStart));

    if ($highestCategory && $lowestCategory) {
        $summaryParts[] = "In $monthName, your highest expense was in <b>$highestCategory</b> ($" . number_format($highestValue, 2) . ") while your lowest was in <b>$lowestCategory</b> ($" . number_format($lowestValue, 2) . ").";
    }

    if (!empty($extraCategoryStats)) {
        $summaryParts[] = "You logged expenses in: " . implode(", ", $extraCategoryStats) . ".";
    }

    if ($extraIncomeTotal > 0) {
        $summaryParts[] = "You added extra income of $" . number_format($extraIncomeTotal, 2) . ".";
    }

    if (!empty($incomeNotes)) {
        $summaryParts[] = "Income notes: " . implode("; ", $incomeNotes) . ".";
    }

    $summaryParts[] = "Your expected savings was $" . number_format($expectedSavings, 2) . ", and your actual savings ended at $" . number_format($actualSavings, 2) . ".";

    $summaryText = implode(" ", $summaryParts);

    // 6. Return final structured data
    return [
        "month" => $monthName,
        "income" => $totalIncome,
        "expected_savings" => $expectedSavings,
        "actual_savings" => $actualSavings,
        "expenses" => $totalExpenses,
        "expenseBreakdown" => $expenseBreakdown,
        "summaryText" => $summaryText
    ];
}

}
