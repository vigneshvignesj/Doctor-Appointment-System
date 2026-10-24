<?php use Core\Router; ?>
<p class="eyebrow mb-1">My schedule</p>
<h1 class="h3 mb-3">Weekly schedule</h1>
<p class="text-muted mb-3"><?= htmlspecialchars($doctor['specialization_name'], ENT_QUOTES) ?> &middot; <a href="<?= Router::url('/doctor/availability') ?>">Manage weekly hours &amp; exceptions &rarr;</a></p>

<?php
$weekUrlBase = '/doctor/dashboard';
$dayUrlBase = '/doctor/schedule/day';
$apptLinkBase = '/doctor/appointments/';
include __DIR__ . '/../schedule/_week_grid.php';
?>
