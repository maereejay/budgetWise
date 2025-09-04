<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;


require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/jwt.php';

class UserController {
    private $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    public function register() {
        header("Content-Type: application/json; charset=UTF-8");

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            echo json_encode([
                "success" => false,
                "message" => "All fields are required"
            ]);
            return;
        }

        $result = $this->userModel->register(
            $data['name'],
            $data['email'],
            $data['password']
        );

        echo json_encode($result);
    }

    public function login() {
        header("Content-Type: application/json; charset=UTF-8");

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['email']) || empty($data['password'])) {
            echo json_encode([
                "success" => false,
                "message" => "Email and password required"
            ]);
            return;
        }

        $result = $this->userModel->login($data['email'], $data['password']);

        if (!empty($result['success']) && $result['success'] === true) {
            // Remove password before sending response
            if (isset($result['user']['password'])) {
                unset($result['user']['password']);
            }

            $payload = [
                'id' => $result['user']['id'],
                'email' => $result['user']['email'],
                'name' => $result['user']['name'],
                'exp' => time() + 5400, 
            ];

            $jwt = JWT::encode($payload, JWT_SECRET, JWT_ALGO);

            echo json_encode([
                "success" => true,
                "message" => "Login successful",
                "token" => $jwt,
                "user" => $result['user']
            ]);
            return;
        }

        // Failed login
        $failMessage = $result['message'] ?? "Invalid email or password";
        echo json_encode([
            "success" => false,
            "message" => $failMessage
        ]);
    }
}
