<?php

// When running under `php -S` for local testing (not Apache/XAMPP), let the
// built-in server serve real files (assets) directly instead of routing them.
if (PHP_SAPI === 'cli-server') {
    $requested = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($requested !== __DIR__ . '/' && is_file($requested)) {
        return false;
    }
}

require __DIR__ . '/../core/bootstrap.php';

use App\Controllers\AccountController;
use App\Controllers\AdminAppointmentController;
use App\Controllers\AdminAuditController;
use App\Controllers\AdminController;
use App\Controllers\AdminDoctorController;
use App\Controllers\AdminPatientController;
use App\Controllers\AdminScheduleController;
use App\Controllers\AdminSpecializationController;
use App\Controllers\AdminStressTestController;
use App\Controllers\AuthController;
use App\Controllers\DoctorAppointmentController;
use App\Controllers\DoctorAvailabilityController;
use App\Controllers\DoctorScheduleController;
use App\Controllers\PatientAppointmentController;
use App\Controllers\PatientDoctorController;
use App\Controllers\ScheduleApiController;
use Core\Router;

$router = new Router();

// --- Auth ---
$router->get('/', [AuthController::class, 'showLogin']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);

// --- Shared account ---
$router->get('/account', [AccountController::class, 'show']);
$router->post('/account/profile', [AccountController::class, 'updateProfile']);
$router->post('/account/password', [AccountController::class, 'updatePassword']);

// --- Shared JSON slot API (patient booking, admin reassign, stress test setup) ---
$router->get('/api/slots', [ScheduleApiController::class, 'slots']);

// --- Admin: dashboard ---
$router->get('/admin/dashboard', [AdminController::class, 'dashboard']);

// --- Admin: doctors ---
$router->get('/admin/doctors', [AdminDoctorController::class, 'index']);
$router->get('/admin/doctors/create', [AdminDoctorController::class, 'create']);
$router->post('/admin/doctors', [AdminDoctorController::class, 'store']);
$router->get('/admin/doctors/import', [AdminDoctorController::class, 'importForm']);
$router->post('/admin/doctors/import', [AdminDoctorController::class, 'import']);
$router->get('/admin/doctors/{id}/edit', [AdminDoctorController::class, 'edit']);
$router->post('/admin/doctors/{id}', [AdminDoctorController::class, 'update']);
$router->post('/admin/doctors/{id}/deactivate', [AdminDoctorController::class, 'deactivate']);
$router->post('/admin/doctors/{id}/activate', [AdminDoctorController::class, 'activate']);

// --- Admin: specializations ---
$router->get('/admin/specializations', [AdminSpecializationController::class, 'index']);
$router->post('/admin/specializations', [AdminSpecializationController::class, 'store']);
$router->post('/admin/specializations/{id}', [AdminSpecializationController::class, 'update']);
$router->post('/admin/specializations/{id}/delete', [AdminSpecializationController::class, 'delete']);

// --- Admin: schedule drill-down + today's board ---
$router->get('/admin/board', [AdminScheduleController::class, 'board']);
$router->get('/admin/schedule/{id}', [AdminScheduleController::class, 'show']);
$router->get('/admin/schedule/{id}/day/{date}', [AdminScheduleController::class, 'day']);
$router->post('/admin/schedule/{id}/availability', [AdminScheduleController::class, 'addAvailability']);
$router->post('/admin/schedule/availability/{availabilityId}/delete', [AdminScheduleController::class, 'deleteAvailability']);
$router->post('/admin/schedule/{id}/exception', [AdminScheduleController::class, 'addException']);
$router->post('/admin/schedule/exception/{exceptionId}/delete', [AdminScheduleController::class, 'deleteException']);

// --- Admin: appointments ---
$router->get('/admin/appointments', [AdminAppointmentController::class, 'index']);
$router->post('/admin/appointments/{id}/cancel', [AdminAppointmentController::class, 'cancel']);
$router->get('/admin/appointments/{id}/reassign', [AdminAppointmentController::class, 'reassignForm']);
$router->post('/admin/appointments/{id}/reassign', [AdminAppointmentController::class, 'reassign']);

// --- Admin: patients ---
$router->get('/admin/patients', [AdminPatientController::class, 'index']);
$router->post('/admin/patients/{id}/suspend', [AdminPatientController::class, 'suspend']);
$router->post('/admin/patients/{id}/reactivate', [AdminPatientController::class, 'reactivate']);

// --- Admin: concurrency stress test ---
$router->get('/admin/stress-test', [AdminStressTestController::class, 'form']);
$router->post('/admin/stress-test/attempt', [AdminStressTestController::class, 'attempt']);
$router->post('/admin/stress-test/log', [AdminStressTestController::class, 'logRun']);

// --- Admin: audit log + reports ---
$router->get('/admin/audit-log', [AdminAuditController::class, 'index']);

// --- Doctor ---
$router->get('/doctor/dashboard', [DoctorScheduleController::class, 'week']);
$router->get('/doctor/schedule/day/{date}', [DoctorScheduleController::class, 'day']);
$router->get('/doctor/availability', [DoctorAvailabilityController::class, 'index']);
$router->post('/doctor/availability', [DoctorAvailabilityController::class, 'addAvailability']);
$router->post('/doctor/availability/{id}/delete', [DoctorAvailabilityController::class, 'deleteAvailability']);
$router->post('/doctor/exceptions', [DoctorAvailabilityController::class, 'addException']);
$router->post('/doctor/exceptions/{id}/delete', [DoctorAvailabilityController::class, 'deleteException']);
$router->get('/doctor/appointments', [DoctorAppointmentController::class, 'history']);
$router->get('/doctor/appointments/{id}', [DoctorAppointmentController::class, 'show']);
$router->post('/doctor/appointments/{id}/complete', [DoctorAppointmentController::class, 'complete']);
$router->post('/doctor/appointments/{id}/no-show', [DoctorAppointmentController::class, 'noShow']);
$router->post('/doctor/appointments/{id}/cancel', [DoctorAppointmentController::class, 'cancel']);

// --- Patient ---
$router->get('/patient/dashboard', [PatientAppointmentController::class, 'dashboard']);
$router->get('/patient/doctors', [PatientDoctorController::class, 'index']);
$router->get('/patient/doctors/{id}', [PatientDoctorController::class, 'show']);
$router->post('/patient/book', [PatientAppointmentController::class, 'book']);
$router->get('/patient/appointments', [PatientAppointmentController::class, 'myAppointments']);
$router->get('/patient/appointments/{id}/confirmation', [PatientAppointmentController::class, 'confirmation']);
$router->post('/patient/appointments/{id}/cancel', [PatientAppointmentController::class, 'cancel']);
$router->get('/patient/appointments/{id}/reschedule', [PatientAppointmentController::class, 'rescheduleForm']);
$router->post('/patient/appointments/{id}/reschedule', [PatientAppointmentController::class, 'reschedule']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
