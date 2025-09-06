<?php
class Auth {
    public static function register(PDO $pdo, array $env, string $email, string $username, string $password): int {
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        $ddnsToken = Util::randToken(64);
        $annualDue = (new DateTimeImmutable('+1 year'))->format('Y-m-d');
        $verifyToken = Util::randToken(64);

        $stmt = $pdo->prepare('INSERT INTO users (email, username, password_hash, role, is_active, ddns_token, email_verification_token, annual_confirm_due, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())');
        // is_active=0 bis E-Mail bestätigt
        $stmt->execute([$email, $username, $hash, 'user', 0, $ddnsToken, $verifyToken, $annualDue]);
        return (int)$pdo->lastInsertId();
    }

    public static function login(PDO $pdo, string $login, string $password): bool {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE (email = ? OR username = ?) AND is_active = 1 LIMIT 1');
        $stmt->execute([$login, $login]);
        $u = $stmt->fetch();
        if (!$u) return false;
        if (!password_verify($password, $u['password_hash'])) return false;
        $_SESSION['uid'] = (int)$u['id'];
        $_SESSION['role'] = $u['role'];
        $_SESSION['username'] = $u['username'];
        return true;
    }

    public static function requireLogin(): void {
        if (empty($_SESSION['uid'])) { header('Location: /login.php'); exit; }
    }

    public static function isAdmin(): bool { return ($_SESSION['role'] ?? '') === 'admin'; }
}
