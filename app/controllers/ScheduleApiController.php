<?php

namespace App\Controllers;

use App\Models\Doctor;
use Core\Auth;
use Core\SlotEngine;

/**
 * Small JSON endpoint shared by three screens that all need "what's bookable
 * for doctor X on date Y right now": the patient booking page, the admin
 * reassign-appointment form, and the Concurrency Stress Test setup form.
 * Reused rather than duplicated so all three always see the exact same
 * SlotEngine-computed truth.
 */
class ScheduleApiController
{
    public function slots(): void
    {
        Auth::requireLogin();
        $doctorId = (int) ($_GET['doctor_id'] ?? 0);
        $date = $_GET['date'] ?? '';

        header('Content-Type: application/json');

        if ($doctorId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid doctor or date']);
            return;
        }
        $doctor = Doctor::find($doctorId);
        if (!$doctor || $doctor['status'] !== 'active') {
            http_response_code(404);
            echo json_encode(['error' => 'Doctor not found']);
            return;
        }

        $data = SlotEngine::daySlots($doctorId, $date);
        $open = array_values(array_filter($data['slots'], fn($s) => $s['status'] === 'open'));
        $formatted = array_map(fn($s) => [
            'start' => $s['start'],
            'end' => $s['end'],
            'label' => date('g:i A', strtotime($s['start'])) . ' - ' . date('g:i A', strtotime($s['end'])),
            'source' => $s['source'],
        ], $open);

        echo json_encode([
            'full_day_leave' => $data['full_day_leave'],
            'slots' => $formatted,
        ]);
    }
}
