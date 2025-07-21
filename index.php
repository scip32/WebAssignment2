<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
include 'db.php';

$stmt = $conn->prepare("
    SELECT e.id, e.title, e.deadline, e.votes_per_user, u.name AS owner
    FROM events e
    JOIN users u ON e.owner_id = u.id
    ORDER BY e.id DESC
");
$stmt->execute();
$events = $stmt->get_result();
?>
<!doctype html>
<html lang="en-US">
<head>
  <meta charset="utf-8">
  <title>Event List</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link 
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" 
    rel="stylesheet"
  >
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    integrity="sha512-papKXvZfV2x4wbQ0l0MfY2T+z6EFVv+9X2YQBFuPZY2YNPYR0EjNQ7F7Q1uR6h0kX6G4bZhTpz7g0yZ2+MJl0w=="
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
  />
  <style>
    html, body { height:100%; margin:0; }
    body { display:flex; flex-direction:column; }
    main { flex:1; }
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
    <h3 class="m-0">Event List</h3>
    <div class="text-end">
      <span class="me-2">Welcome, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong></span>
      <a href="logout.php" class="text-decoration-none">Logout</a>
    </div>
  </header>

  <main class="container mb-4">
    <a href="create_event.php" class="btn btn-success mb-3">Create New Event</a>

    <?php if ($events->num_rows): ?>
      <table class="table table-hover">
        <thead class="table-light">
          <tr>
            <th>Title</th>
            <th>Owner</th>
            <th>Deadline</th>
            <th>Votes Limit</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php while ($e = $events->fetch_assoc()):
            $closed = $e['deadline'] && strtotime($e['deadline']) < time();
          ?>
            <tr>
              <td><?= htmlspecialchars($e['title']) ?></td>
              <td><?= htmlspecialchars($e['owner']) ?></td>
              <td><?= $e['deadline'] ?: '—' ?></td>
              <td><?= $e['votes_per_user'] ?></td>
              <td>
                <?php if ($closed): ?>
                  <span class="badge bg-secondary">Closed</span>
                <?php else: ?>
                  <span class="badge bg-success">Ongoing</span>
                <?php endif; ?>
              </td>
              <td>
                <a href="event.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-primary">View / Vote</a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="alert alert-info">No events available.</div>
    <?php endif; ?>
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
    <div class="footer-bottom">&copy; 2025 Your Company Name. All rights reserved.</div>
  </footer>

</body>
</html>
