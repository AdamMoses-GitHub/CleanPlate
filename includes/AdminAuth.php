<?php
/**
 * AdminAuth — Session-based admin authentication
 *
 * Credentials are stored in environment variables (ADMIN_USERNAME / ADMIN_PASSWORD)
 * and surfaced via Config. No users table required for admin access.
 *
 * Usage in admin pages:
 *   require_once __DIR__ . '/../../includes/AdminAuth.php';
 *   AdminAuth::check(); // redirects to login if not authenticated
 */
class AdminAuth
{
    private const SESSION_KEY  = 'cleanplate_admin_auth';
    private const LOGIN_PATH   = '/admin/login.php';
    private const SESSION_NAME = 'cleanplate_admin';

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Ensure the visitor is authenticated.
     * If not, redirects to the login page and exits.
     */
    public static function check(): void
    {
        self::startSession();

        if (!self::isLoggedIn()) {
            $redirect = urlencode($_SERVER['REQUEST_URI'] ?? self::LOGIN_PATH);
            header('Location: ' . self::LOGIN_PATH . '?redirect=' . $redirect);
            exit;
        }
    }

    /**
     * Attempt login. Returns true on success, false on bad credentials.
     */
    public static function login(string $username, string $password): bool
    {
        self::startSession();
        self::bootstrapConfig();

        $validUser = Config::get('admin.username', '');
        $validPass = Config::get('admin.password', '');

        if (empty($validUser) || empty($validPass)) {
            // Credentials not configured — refuse login
            return false;
        }

        // Constant-time string comparison to prevent timing attacks
        $userOk = hash_equals($validUser, $username);
        $passOk = hash_equals($validPass, $password);

        if ($userOk && $passOk) {
            // Regenerate session ID on privilege escalation
            session_regenerate_id(true);
            $_SESSION[self::SESSION_KEY] = true;
            $_SESSION['admin_logged_in_at'] = time();
            $_SESSION['admin_user'] = $validUser;
            return true;
        }

        return false;
    }

    /**
     * Destroy the admin session and redirect to login.
     */
    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        header('Location: ' . self::LOGIN_PATH);
        exit;
    }

    /**
     * Check whether the current session is authenticated.
     */
    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]);
    }

    /**
     * Return the logged-in admin username, or empty string.
     */
    public static function getUsername(): string
    {
        return $_SESSION['admin_user'] ?? '';
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(self::SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => 0,            // Browser session cookie
                'path'     => '/',
                'secure'   => false,        // Set to true in production with HTTPS
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    private static function bootstrapConfig(): void
    {
        if (!class_exists('Config')) {
            require_once __DIR__ . '/Config.php';
        }
        Config::load();
    }
}
