<?php

header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header("Content-Type: application/json; charset=UTF-8");


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../app/controllers/UserController.php';
require_once __DIR__ . '/../app/controllers/BudgetController.php';
require_once __DIR__ . '/../vendor/autoload.php';         
require_once __DIR__ . '/../config/jwt.php';          

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// -----------------------
// Route handling
// -----------------------
switch ($route) {


    case 'register':
        if ($method === 'POST') (new UserController())->register();
        break;

    case 'login':
        if ($method === 'POST') (new UserController())->login();
        break;


    case 'setBudget':
        if ($method === 'POST') (new BudgetController())->setBudget();
        break;

    case 'getBudget':
        if ($method === 'GET') (new BudgetController())->getMonthBudgetData();
        break;

    case 'monthStats':
        if ($method === 'GET') (new BudgetController())->getMonthBudgetData();
        break;

    case 'getBudgetCards':
        if ($method === 'GET') (new BudgetController())->getMonthBudgetData();
        break;

    case 'getNotifications':
        if ($method === 'GET') (new BudgetController())->getNotifications();
        break;

    case 'addExpense':
        if ($method === 'POST') (new BudgetController())->addExpense();
        break;

    case 'addIncome':
    if ($method === 'POST') (new BudgetController())->addIncome();
    break;

    case 'getChartData':
        if ($method === 'GET') (new BudgetController())->getChartData();
        break;
    case 'getMonthlySummary':
    if ($method === 'GET') (new BudgetController())->getMonthlySummary();
    break;

    default:
        echo json_encode([
            "success" => false,
            "message" => "Route not found"
        ]);
}
