<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once __DIR__ . '/../../config/jwt.php'; 

class Auth {
    public static function verifyToken() {
        $headers = getallheaders();

        if (!isset($headers['Authorization'])) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "error" => "NO_TOKEN",
                "message" => "No token provided"
            ]);
            exit();
        }

        $authHeader = $headers['Authorization'];
        $token = str_replace('Bearer ', '', $authHeader);

        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET, JWT_ALGO));
            return (array) $decoded; 
        } catch (\Firebase\JWT\ExpiredException $e) {
            
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "error" => "TOKEN_EXPIRED",
                "message" => "Session expired. Please log in again."
            ]);
            exit();
        } catch (Exception $e) {
            // Any other invalid token issue
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "error" => "INVALID_TOKEN",
                "message" => "Invalid token: " . $e->getMessage()
            ]);
            exit();
        }
    }
}
