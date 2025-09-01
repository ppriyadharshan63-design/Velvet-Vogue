<?php
session_start();
require_once __DIR__ . '/config/database.php';   // $conn = mysqli
require_once __DIR__ . '/includes/functions.php'; // isLoggedIn(), sanitizeInput() if you need it

// --------- CSRF helpers (defense-in-depth) ----------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8').'">';
}
function verify_csrf(): bool {
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf']);
}

// --------- Already logged in? ----------
if (isLoggedIn()) {
    header('Location: account.php');
    exit;
}

$message = '';
$message_type = 'error';

// --------- Basic rate limiting (per session) ----------
$MAX_ATTEMPTS = 5;           // tries
$WINDOW_SEC   = 900;         // 15 minutes
$now = time();
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = ['count' => 0, 'first' => $now];
} else {
    // reset window
    if ($now - $_SESSION['login_attempts']['first'] > $WINDOW_SEC) {
        $_SESSION['login_attempts'] = ['count' => 0, 'first' => $now];
    }
}

function too_many_attempts(): bool {
    global $MAX_ATTEMPTS;
    return $_SESSION['login_attempts']['count'] >= $MAX_ATTEMPTS;
}

// --------- Allow-list for redirects ----------
function safe_redirect_target(?string $r): string {
    if (!$r) return 'account.php';
    // only allow relative paths we expect
    $allowed = ['account.php', 'admin.php', 'index.php', 'cart.php', 'shop.php'];
    // strip leading slash if any
    $r = ltrim($r, '/');
    return in_array($r, $allowed, true) ? $r : 'account.php';
}

// --------- Handle POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!verify_csrf()) {
        $message = 'Your session expired. Please try again.';
    } elseif (too_many_attempts()) {
        $message = 'Too many attempts. Please wait a few minutes and try again.';
    } else {
        // Validate inputs
        $email    = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? trim($_POST['email']) : '';
        $password = (string)($_POST['password'] ?? '');

        if ($email !== '' && $password !== '') {
            // Look up user by email
            $sql = "SELECT id, email, password, first_name, last_name, is_admin FROM users WHERE email = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $res  = $stmt->get_result();
                $user = $res->fetch_assoc();
                $stmt->close();

                $ok = false;
                if ($user) {
                    // Verify password
                    $ok = password_verify($password, $user['password']);
                }

                if ($ok) {
                    // reset attempts
                    $_SESSION['login_attempts'] = ['count' => 0, 'first' => time()];

                    // Secure the session
                    session_regenerate_id(true);

                    $_SESSION['user_id']    = (int)$user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name']  = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                    $_SESSION['is_admin']   = (int)$user['is_admin'];

                    // Merge guest cart -> user cart (if you track guest_id in session)
                    if (isset($_SESSION['guest_id']) && ctype_digit((string)$_SESSION['guest_id'])) {
                        $guest_id = (int)$_SESSION['guest_id'];
                        $user_id  = (int)$user['id'];

                        // Only if cart_items.user_id stores either real user ids or a temp guest id
                        $transfer_sql = "UPDATE cart_items SET user_id = ? WHERE user_id = ?";
                        if ($ts = $conn->prepare($transfer_sql)) {
                            $ts->bind_param("ii", $user_id, $guest_id); // bind as integers
                            $ts->execute();
                            $ts->close();
                        }
                        unset($_SESSION['guest_id']);
                    }

                    // Redirect
                    $redirect = safe_redirect_target($_GET['redirect'] ?? null);
                    header('Location: ' . $redirect);
                    exit;
                } else {
                    // bump attempts
                    $_SESSION['login_attempts']['count']++;
                    // generic error (don’t leak which field failed)
                    $message = 'Invalid email or password.';
                }
            } else {
                $message = 'A server error occurred. Please try again.';
            }
        } else {
            $message = 'Please fill in all fields.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Velvet Vogue</title>
  <meta name="description" content="Sign in to your Velvet Vogue account to access your orders, wishlist, and personalized shopping experience.">
  <link rel="stylesheet" href="assets/css/style.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    .auth-card{max-width:400px;margin:0 auto;background:#fff;padding:3rem;border-radius:10px;box-shadow:0 5px 15px rgba(0,0,0,.1)}
    .auth-card h1{margin-bottom:1.25rem;text-align:center;color:#2c3e50}
    .form-group{margin-bottom:1rem}
    .form-group label{display:block;margin-bottom:.35rem}
    .form-group input{width:100%}
    .alert{padding:.75rem 1rem;border-radius:8px;margin-bottom:1rem}
    .alert-error{background:#f8d7da;color:#721c24;border-left:4px solid #dc3545}
    .btn{display:inline-flex;align-items:center;gap:.5rem;justify-content:center;width:100%}
  </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<section style="padding:6rem 0 4rem;background:#f8f9fa;min-height:100vh;">
  <div class="container">
    <div class="auth-card">
      <h1>Welcome Back</h1>

      <?php if (!empty($message)): ?>
        <div class="alert alert-error">
          <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
        <?= csrf_field(); ?>
        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" required
                 value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                 placeholder="Enter your email" class="input">
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required placeholder="Enter your password" class="input">
        </div>

        <button type="submit" class="btn btn-primary" style="margin-bottom:1rem;">
          <i class="fas fa-sign-in-alt"></i> Sign In
        </button>
      </form>

      <div style="text-align:center;margin-top:1.5rem;">
        <p style="color:#666;margin-bottom:1rem;">Don't have an account?</p>
        <a href="register.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" class="btn btn-outline">
          Create Account
        </a>
      </div>

      
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="assets/js/main.js"></script>
</body>
</html>
