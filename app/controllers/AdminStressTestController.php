<?php

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\User;
use Core\Auth;
use Core\Csrf;
use Core\Database;
use Core\SlotEngine;
use Core\View;

/**
 * The viva centrepiece: proves the race-proof booking guarantee live rather
 * than just asserting it in the README. The browser fires N simultaneous
 * fetch() calls at attempt() for the exact same doctor/date/slot, each with
 * a distinct dummy patient; the UNIQUE KEY on slot_reservations means the
 * database itself allows exactly one to win, no matter how the requests
 * interleave. See public/assets/js/stress-test.js for the Promise.all driver.
 */
class AdminStressTestController
{
    public function form(): void
    {
        Auth::requireRole('admin');
        View::render('admin/stress_test/form', ['doctors' => Doctor::allActive()]);
    }

    public function attempt(): void
    {
        Auth::requireRole('admin');
        header('Content-Type: application/json');

        if (!Csrf::verifyRequest()) {
            http_response_code(419);
            echo json_encode(['outcome' => 'error', 'error' => 'CSRF token invalid or expired']);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $doctorId = (int) ($body['doctor_id'] ?? 0);
        $date = $body['date'] ?? '';
        $start = $body['start_time'] ?? '';
        $end = $body['end_time'] ?? '';
        $dummyIndex = (int) ($body['dummy_index'] ?? 0);

        if ($doctorId <= 0 || $date === '' || $start === '' || $end === '' || $dummyIndex <= 0) {
            http_response_code(400);
            echo json_encode(['outcome' => 'error', 'error' => 'Missing parameters']);
            return;
        }

        $patient = User::findOrCreateStressTestPatient($dummyIndex);
        $result = SlotEngine::book($doctorId, (int) $patient['id'], $date, $start, $end);

        if ($result['success']) {
            Database::connection()->prepare('UPDATE appointments SET is_stress_test = 1 WHERE id = ?')
                ->execute([$result['appointment_id']]);
            echo json_encode([
                'outcome' => 'booked',
                'dummy_index' => $dummyIndex,
                'patient_name' => $patient['name'],
                'appointment_id' => $result['appointment_id'],
                'timestamp' => microtime(true),
            ]);
            return;
        }

        echo json_encode([
            'outcome' => 'slot_taken',
            'dummy_index' => $dummyIndex,
            'patient_name' => $patient['name'],
            'timestamp' => microtime(true),
        ]);
    }

    /** Called once by the browser after Promise.all resolves, to log the whole run as one audit entry. */
    public function logRun(): void
    {
        Auth::requireRole('admin');
        header('Content-Type: application/json');

        if (!Csrf::verifyRequest()) {
            http_response_code(419);
            echo json_encode(['ok' => false]);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $doctorId = (int) ($body['doctor_id'] ?? 0);
        $date = $body['date'] ?? '';
        $start = $body['start_time'] ?? '';
        $n = (int) ($body['n'] ?? 0);
        $bookedCount = (int) ($body['booked_count'] ?? 0);
        $winnerId = $body['winner_appointment_id'] ?? null;

        $doctor = Doctor::find($doctorId);
        $doctorName = $doctor['name'] ?? "#{$doctorId}";
        $details = "Doctor {$doctorName}, {$date} {$start}: {$n} simulated concurrent requests, "
            . "{$bookedCount} succeeded (expected exactly 1)"
            . ($winnerId ? ", winning appointment #{$winnerId}" : '');

        AuditLog::record(Auth::id(), 'admin', 'stress_test_run', $details);
        echo json_encode(['ok' => true]);
    }
}
