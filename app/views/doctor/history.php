<?php use Core\Router; ?>
<p class="eyebrow mb-1">Appointment history</p>
<h1 class="h3 mb-4">All appointments</h1>

<div class="card card-tc">
    <div class="card-body p-0">
        <?php if (empty($appointments)): ?>
            <p class="text-muted p-4 mb-0">No appointments yet.</p>
        <?php else: ?>
            <table class="table mb-0 align-middle">
                <thead><tr><th>Date</th><th>Time</th><th>Patient</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td class="mono"><?= htmlspecialchars($a['appointment_date'], ENT_QUOTES) ?></td>
                        <td class="mono"><?= date('g:i A', strtotime($a['start_time'])) ?></td>
                        <td><?= htmlspecialchars($a['patient_name'], ENT_QUOTES) ?></td>
                        <td>
                            <?php
                            $badges = ['booked' => 'primary', 'completed' => 'success', 'cancelled' => 'secondary', 'no_show' => 'danger'];
                            $cls = $badges[$a['status']] ?? 'secondary';
                            ?>
                            <span class="badge text-bg-<?= $cls ?>"><?= htmlspecialchars(str_replace('_', ' ', $a['status']), ENT_QUOTES) ?></span>
                            <?= $a['is_stress_test'] ? '<span class="badge text-bg-warning ms-1">stress test</span>' : '' ?>
                        </td>
                        <td><a href="<?= Router::url('/doctor/appointments/' . $a['id']) ?>" class="btn btn-sm btn-outline-tc">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
