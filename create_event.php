<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

function csrf_check() {
    return hash_equals($_SESSION['token'] ?? '', $_POST['token'] ?? '');
}

$error  = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = 'Invalid request (CSRF)';
    } else {
        $title      = trim($_POST['title'] ?? '');
        $description = trim($_POST['desc'] ?? '');
        $slots      = trim($_POST['slots'] ?? '');
        $deadline   = trim($_POST['deadline'] ?? '');
        $maxVotes   = max(1, (int)($_POST['max_votes'] ?? 1));
        $inviteList = trim($_POST['invite'] ?? '');

        if ($title === '' || $slots === '') {
            $error = 'Title and time slots are required.';
        } elseif (substr_count($slots, PHP_EOL) > 49) {
            $error = 'You cannot enter more than 50 time slots.';
        } else {
            $conn->begin_transaction();
            try {
                // Insert the event
                $stmt = $conn->prepare(
                    "INSERT INTO events (owner_id, title, description, deadline, votes_per_user)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $dl = $deadline ? date('Y-m-d H:i:s', strtotime($deadline)) : null;
                $stmt->bind_param(
                    'isssi',
                    $_SESSION['user_id'],
                    $title,
                    $description,
                    $dl,
                    $maxVotes
                );
                $stmt->execute();
                $eventId = $stmt->insert_id;

                // Insert each time slot
                $stmtSlot = $conn->prepare(
                    "INSERT INTO timeslots (event_id, slot) VALUES (?, ?)"
                );
                foreach (preg_split('/\R/', $slots) as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $stmtSlot->bind_param('is', $eventId, $line);
                    $stmtSlot->execute();
                }

                $conn->commit();
                $success = true;
            } catch (Throwable $e) {
                $conn->rollback();
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="en-US">
<head>
  <meta charset="utf-8">
  <title>Create Event</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link 
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" 
    rel="stylesheet"
  >
  <link 
    rel="stylesheet" 
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" 
    crossorigin="anonymous"
  >
  <style>
    html, body { height:100%; margin:0; }
    body { display:flex; flex-direction:column; }
    main { flex:1; padding-bottom:2rem; }
    footer {
      background: #0d47a1;
      color: #fff;
      font-size: .75rem;
      line-height:1.2;
    }
    .footer-container {
      display:flex; justify-content:center; flex-wrap:wrap; padding:1rem 2rem;
    }
    .footer-col {
      flex:1 1 180px; max-width:200px; padding:0 1rem; text-align:center;
    }
    .footer-col:not(:last-child) {
      border-right:1px solid rgba(255,255,255,0.3);
    }
    .footer-col h6 {
      margin-bottom:.5rem; font-size:.85rem; font-weight:600; color:#e3f2fd;
    }
    .footer-col ul { list-style:none; padding:0; margin:0; }
    .footer-col li { margin:.2rem 0; }
    .footer-col a {
      color:rgba(255,255,255,0.8); text-decoration:none; transition:color .2s; font-size:.75rem;
    }
    .footer-col a:hover { color:#e3f2fd; text-decoration:underline; }
    .footer-bottom {
      text-align:center; font-size:.65rem; padding:.5rem 0; border-top:1px solid rgba(255,255,255,0.3);
      margin:0 2rem;
    }
  </style>
</head>
<body>
  <header class="container py-3 d-flex justify-content-between align-items-center">
    <h3 class="m-0">Create Event</h3>
    <div class="text-end">
      <span class="me-2">Welcome, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong></span>
      <a href="logout.php" class="text-decoration-none text-light">Logout</a>
    </div>
  </header>
  <main class="container flex-fill mb-4">
    <div class="row">
      <div class="col-md-8 offset-md-2">

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($success): ?>
          <div class="alert alert-success">
            Event created successfully! <a href="index.php">Return to list</a>
          </div>
        <?php endif; ?>

        <form method="post" class="needs-validation" novalidate>
          <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">

          <div class="mb-3">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required>
            <div class="invalid-feedback">Please enter a title.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="desc" class="form-control" rows="2"></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">Time Slots (one per line) <span class="text-danger">*</span></label>
            <textarea id="slots" name="slots" class="form-control" rows="5" required
              placeholder="2025-07-25 10:00&#10;2025-07-26 15:00"></textarea>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Deadline (optional)</label>
              <input type="datetime-local" name="deadline" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Max Votes per User</label>
              <input type="number" name="max_votes" class="form-control" value="1" min="1" max="50">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Invite Members by Email (optional, comma-separated)</label>
            <input type="text" name="invite" class="form-control"
                   placeholder="alice@example.com,bob@example.com">
          </div>

          <button class="btn btn-success">Save Event</button>
          <a href="index.php" class="btn btn-outline-secondary ms-2">Cancel</a>
        </form>

      </div>
    </div>
  </main>
  <footer>
    <div class="footer-container">
      <div class="footer-col">
        <h6>Information For</h6>
        <ul>
          <li><a href="#">Students</a></li>
          <li><a href="#">Parents</a></li>
          <li><a href="#">Staff</a></li>
          <li><a href="#">Alumni</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h6>Related Links</h6>
        <ul>
          <li><a href="#">Admission</a></li>
          <li><a href="#">Academic Calendar</a></li>
          <li><a href="#">Financial Aid</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h6>Resources</h6>
        <ul>
          <li><a href="#">Career @ UTP</a></li>
          <li><a href="#">Visit UTP</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h6>Contact</h6>
        <ul>
          <li>UTP, Malaysia</li>
          <li><a href="tel:+60-5-3688000">+60-5-3688000</a></li>
          <li><a href="#">General Enquiry</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      &copy; 2025 Your Company Name. All rights reserved.
    </div>
  </footer>

  <script>
    (function(){
      'use strict';
      Array.from(document.querySelectorAll('.needs-validation'))
        .forEach(form => {
          form.addEventListener('submit', e => {
            if (!form.checkValidity()) {
              e.preventDefault();
              e.stopPropagation();
            }
            form.classList.add('was-validated');
          });
        });
    })();
  </script>
</body>
</html>
