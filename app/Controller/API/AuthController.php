<?php

namespace App\Controller\API;

use App\Core\DB;
use Exception;
use PDO;
use Dotenv\Dotenv;
class AuthController{

    protected $pdo;
    protected $encryptionKey;
    protected $encryptionIV;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
        $dotenv->load();
        $this->pdo = DB::connection();
        $this->encryptionKey = hash('sha256', $_ENV['ENCRYPTION_KEY']);
        $this->encryptionIV = substr(hash('sha256', $_ENV['ENCRYPTION_IV']), 0, 16);
    }

    public function createAdminUser(){
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        $email = $_GET['email'] ?? null;
        $password = $_GET['password'];
        $lastname = $_GET['lastname'] ?? null;
        $firstname = $_GET['firstname'] ?? null;
        $userId = uniqid('user_');
        $role = 'admin';
        $permission = 'all';

        if (!$email || !$password) {
            echo json_encode(['status' => 'error', 'message' => 'Email and password are required']);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'User with this email already exists']);
            return;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $insert = $this->pdo->prepare("INSERT INTO users (user_id, role, permission, firstname, lastname, email, password) VALUES (:user_id, :role, :permission, :firstname, :lastname, :email, :password)");
        $result = $insert->execute([
            'user_id' => $userId,
            'role' => $role,
            'permission' => $permission,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'password' => $passwordHash
        ]);

        if (!$result) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create admin user']);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Admin user created successfully',
            'email' => $email,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'user_id' => $userId,
            'role' => $role,
            'permission' => $permission
        ]);
    }

    public function updateMigrations(){
    // --- Security: Require a key from query string ---
        $key = $_GET['key'] ?? '';
        $envKey = $_ENV['APP_KEY'] ?? 'create1p'; // fallback if .env missing

        if ($key !== $envKey) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'Unauthorized'
            ]);
            return;
        }

        // --- Path setup ---
        $phpPath = '/usr/local/bin/php'; // cPanel PHP CLI path (usually this works)
        $migrateScript = __DIR__ . '/../../../commands/migrate.php';
        $logDir = __DIR__ . '/../../../storage';

        if (!file_exists($migrateScript)) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Migration script not found'
            ]);
            return;
        }

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // --- Execute the migrations ---
        ob_start(); // start capturing output
        include $migrateScript;
        $output = ob_get_clean(); // get everything the script printed

        // --- Log the result ---
        $logFile = $logDir . '/migrate.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . "\n" . $output . "\n\n", FILE_APPEND);

        // --- Respond ---
        echo json_encode([
            'status' => 'success',
            'message' => 'Migrations executed successfully',
            'output' => trim($output)
        ]);
    }

    public function login(){
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!filter_var( $email, FILTER_VALIDATE_EMAIL)){
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid email address'
            ]);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() < 1){
            echo json_encode([
                'status' => 'error',
                'message' => 'User do not exist yet'
            ]);
            return;
        }
        
        $rows = $stmt->fetch();

        $passwordIsValid = password_verify($password, $rows['password']);

        if (!$passwordIsValid) {
            $legacyPassword = openssl_decrypt(
                $rows['password'],
                'AES-256-CBC',
                $this->encryptionKey,
                0,
                $this->encryptionIV
            );

            if ($legacyPassword !== false && hash_equals($legacyPassword, $password)) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $update = $this->pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?');
                $update->execute([$passwordHash, $rows['user_id']]);
                $passwordIsValid = true;
            }
        }

        if (!$passwordIsValid){
            echo json_encode([
                'status' => 'error',
                'message' => 'Wrong password'
            ]);
            return;
        }
        
        $_SESSION['user'] = [
            'user_id' => $rows['user_id'],
            'firstname' => $rows['firstname'],
            'lastname' => $rows['lastname'],
            'email' => $email,
        ];

        session_regenerate_id(true);

        echo json_encode([
            'status' => 'success',
            'message' => 'successful'
        ]);
    }
}