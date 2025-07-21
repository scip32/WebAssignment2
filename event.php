<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
include 'db.php';

if (!isset($_GET['id'])) {
    die('Missing event ID');
}
$eid = (int)$_GET['id'];

$stmtE = $conn->prepare("
    SELECT e.*, u.name AS owner_name 
    FROM events e 
    JOIN users u ON e.owner_id = u.id 
    WHERE e.id = ?
");
$stmtE->bind_param('i', $eid);
$stmtE->execute();
$event = $stmtE->get_result()->fetch_assoc();
if (!$event) {
    die('Event not found');
}

$deadlinePassed = $event['deadline'] && strtotime($event['deadline']) < time();

$stmtC = $conn->prepare("
    SELECT COUNT(*) 
    FROM votes v 
    JOIN timeslots t ON v.timeslot_id = t.id 
    WHERE v.user_id = ? AND t.event_id = ?
");
$stmtC->bind_param('ii', $_SESSION['user_id'], $eid);
$stmtC->execute();
$userVoted = $stmtC->get_result()->fetch_row()[0] > 0;

$voteError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$deadlinePassed && !$userVoted) {
    $selected = [];
    if ($event['votes_per_user'] == 1 && isset($_POST['slot'])) {
        $selected[] = (int)$_POST['slot'];
    } elseif (!empty($_POST['slots'])) {
        foreach ($_POST['slots'] as $sid) {
            $selected[] = (int)$sid;
        }
    }
    if (empty($selected)) {
        $voteError = 'Please select at least one time slot.';
    } elseif (count($selected) > $event['votes_per_user']) {
        $voteError = "You may select up to {$event['votes_per_user']} time slots.";
    } else {
        $ins = $conn->prepare("INSERT INTO votes (user_id, timeslot_id) VALUES (?, ?)");
        foreach ($selected as $sid) {
            $ins->bind_param('ii', $_SESSION['user_id'], $sid);
            $ins->execute();
        }
        header("Location: event.php?id={$eid}");
        exit;
    }
}

$stmtT = $conn->prepare("
    SELECT t.id, t.slot, COUNT(v.id) AS cnt 
    FROM timeslots t 
    LEFT JOIN votes v ON t.id = v.timeslot_id 
    WHERE t.event_id = ? 
    GROUP BY t.id
");
$stmtT->bind_param('i', $eid);
$stmtT->execute();
$timeslots = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);

$maxCnt = 0;
foreach ($timeslots as $ts) {
    if ($ts['cnt'] > $maxCnt) {
        $maxCnt = $ts['cnt'];
    }
}
?>
<!doctype html>
<html lang="en-US">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($event['title']) ?> – Voting</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link 
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" 
    rel="stylesheet"
  >
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="container py-4">

  <a href="index.php" class="btn btn-link mb-3">&larr; Back to Event List</a>

  <h2><?= htmlspecialchars($event['title']) ?></h2>
  <p><?= nl2br(htmlspecialchars($event['description'])) ?></p>
  <?php if ($event['deadline']): ?>
    <p><strong>Deadline:</strong> <?= htmlspecialchars($event['deadline']) ?></p>
  <?php endif; ?>
  <p><strong>Votes per User:</strong> <?= $event['votes_per_user'] ?></p>

  <?php if (!$deadlinePassed && !$userVoted): ?>
    <?php if ($voteError): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($voteError) ?></div>
    <?php endif; ?>
    <form method="post" class="mb-4">
      <?php foreach ($timeslots as $ts): ?>
        <div class="form-check">
          <?php if ($event['votes_per_user'] == 1): ?>
            <input 
              class="form-check-input" 
              type="radio" 
              name="slot" 
              id="ts<?= $ts['id'] ?>" 
              value="<?= $ts['id'] ?>" 
              required
            >
          <?php else: ?>
            <input 
              class="form-check-input" 
              type="checkbox" 
              name="slots[]" 
              id="ts<?= $ts['id'] ?>" 
              value="<?= $ts['id'] ?>"
            >
          <?php endif; ?>
          <label class="form-check-label" for="ts<?= $ts['id'] ?>">
            <?= htmlspecialchars($ts['slot']) ?>
          </label>
        </div>
      <?php endforeach; ?>
      <button class="btn btn-primary mt-3">Submit Vote</button>
    </form>
  <?php else: ?>
    <?php if ($deadlinePassed): ?>
      <div class="alert alert-warning">Voting has closed.</div>
    <?php elseif ($userVoted): ?>
      <div class="alert alert-success">You have already voted, thank you for participating!</div>
    <?php endif; ?>
  <?php endif; ?>

  <h4>Voting Results</h4>
  <table class="table table-bordered" style="max-width:600px">
    <thead>
      <tr>
        <th>Time Slot</th>
        <th>Votes</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($timeslots as $ts): ?>
        <tr class="<?= ($ts['cnt'] == $maxCnt && $maxCnt > 0) ? 'table-success' : '' ?>">
          <td><?= htmlspecialchars($ts['slot']) ?></td>
          <td><?= $ts['cnt'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="my-4" style="max-width:700px">
    <h4>Voting Visualization</h4>
    <canvas id="resultChart" width="600" height="300"></canvas>
  </div>

  <script>
    const labels = [
      <?php foreach ($timeslots as $ts): echo json_encode($ts['slot']) . ','; endforeach; ?>
    ];
    const data = [
      <?php foreach ($timeslots as $ts): echo $ts['cnt'] . ','; endforeach; ?>
    ];
    const maxCnt = <?= $maxCnt ?>;

    const bgColors = data.map(v => v === maxCnt ? 'rgba(54, 162, 235, 0.7)' : 'rgba(201, 203, 207, 0.7)');
    const bdColors = data.map(v => v === maxCnt ? 'rgba(54, 162, 235, 1)'   : 'rgba(201, 203, 207, 1)');

    new Chart(
      document.getElementById('resultChart'),
      {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: 'Votes',
            data: data,
            backgroundColor: bgColors,
            borderColor: bdColors,
            borderWidth: 1
          }]
        },
        options: {
          scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Votes' } },
            x: { title: { display: true, text: 'Time Slot' } }
          },
          plugins: {
            legend: { display: false },
            title: { display: true, text: 'Vote Counts by Time Slot' }
          }
        }
      }
    );
  </script>

</body>
</html>
