<?php

require_once __DIR__ . '/../../config/database.php';

class User {
    private $conn;

    public function __construct() {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); 
        $this->conn = connectDB();
    }

    public function register($name, $email, $password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO users (name, email, password) VALUES (?, ?, ?)"
            );
            $stmt->bind_param("sss", $name, $email, $hashedPassword);
            $stmt->execute();

            return [
                "success" => true,
                "message" => "User registered successfully"
            ];

        } catch (mysqli_sql_exception $e) {
            // Duplicate email error code
            if ($e->getCode() === 1062) {
                return [
                    "success" => false,
                    "message" => "Email already registered"
                ];
            }

            return [
                "success" => false,
                "message" => "Database error: " . $e->getMessage()
            ];
        }
    }

    public function login($email, $password) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    return [
                        "success" => true,
                        "user" => $user
                    ];
                }
            }
            return [
                "success" => false,
                "message" => "Invalid email or password"
            ];

        } catch (mysqli_sql_exception $e) {
            return [
                "success" => false,
                "message" => "Database error: " . $e->getMessage()
            ];
        }
    }
}
