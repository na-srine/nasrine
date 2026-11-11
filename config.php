<?php
// À adapter : identifiants MySQL et mot de passe de l'administration.
const DB_HOST = 'localhost';
const DB_NAME = 'foodapp';
const DB_USER = 'root';
const DB_PASS = '';
const ADMIN_PASSWORD = 'nasrine';   

session_start();

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fcfa(int $n): string { return number_format($n, 0, ',', ' ') . ' FCFA'; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Session expirée, rechargez la page.'); }
}