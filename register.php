<?php
session_start();
include 'db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    if ($name && $email && $password) {
        // Check if email is already registered
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows) {
            $error = 'Email is already registered.';
        } else {
            // Insert new user
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                'INSERT INTO users (name, email, password) VALUES (?, ?, ?)'
            );
            $stmt->bind_param('sss', $name, $email, $hash);
            $stmt->execute();

            // Log the user in
            $_SESSION['user_id']   = $stmt->insert_id;
            $_SESSION['user_name'] = $name;
            header('Location: index.php');
            exit;
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!doctype html>
<html lang="en-US">
<head>
  <meta charset="utf-8">
  <title>Register</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link 
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" 
    rel="stylesheet"
  >
  <style>
    body {
      margin: 0;
      padding: 0;
      height: 100vh;
      background-image: url('image/1.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      display: flex;
      justify-content: center;
      align-items: center;
    }
    .form-box {
      background-color: rgba(255, 255, 255, 0.85);
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0,0,0,0.3);
      width: 100%;
      max-width: 400px;
    }
  </style>
</head>
<body>

  <div class="form-box">
    <h2 class="mb-4">User Registration</h2>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="mb-3">
        <label class="form-label">Name</label>
        <input 
          type="text" 
          name="name" 
          class="form-control" 
          required
        >
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input 
          type="email" 
          name="email" 
          class="form-control" 
          required
        >
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input 
          type="password" 
          name="password" 
          class="form-control" 
          required
        >
      </div>
      <button 
        type="submit" 
        class="btn btn-success w-100"
      >
        Register
      </button>
      <p class="mt-3 text-center">
        Already have an account? 
        <a href="login.php">Log in here</a>
      </p>
    </form>
  </div>

</body>
</html>
