<?php

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Specialization;
use App\Models\User;
use Core\Auth;
use Core\Csrf;
use Core\Router;
use Core\Validator;
use Core\View;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AdminDoctorController
{
    public function index(): void
    {
        Auth::requireRole('admin');
        $search = trim($_GET['q'] ?? '');
        $specializationId = (int) ($_GET['specialization_id'] ?? 0);
        $status = $_GET['status'] ?? '';
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = Doctor::all($search, $specializationId, $status, $page, 15);

        View::render('admin/doctors/index', [
            'doctors' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 15,
            'search' => $search,
            'specializationId' => $specializationId,
            'status' => $status,
            'specializations' => Specialization::all(),
        ]);
    }

    public function create(): void
    {
        Auth::requireRole('admin');
        View::render('admin/doctors/form', [
            'doctor' => null,
            'specializations' => Specialization::all(),
        ]);
    }

    public function store(): void
    {
        Auth::requireRole('admin');
        if (!Csrf::verifyRequest()) {
            Router::redirect('/admin/doctors/create');
        }

        $data = $this->collectInput();
        $validator = new Validator($data);
        $validator->required('name')
            ->required('email')->email('email')->unique('email', 'users', 'email')
            ->required('phone')
            ->required('specialization_id')
            ->required('qualification')
            ->required('consultation_fee')->numeric('consultation_fee');

        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->firstErrors();
            $_SESSION['old'] = $data;
            Router::redirect('/admin/doctors/create');
        }

        [$userId, $plainPassword, $staffCode] = $this->createDoctorUser($data['name'], $data['email'], $data['phone']);
        Doctor::create($userId, (int) $data['specialization_id'], $data['qualification'], (float) $data['consultation_fee'], (int) ($data['slot_duration'] ?: 20), $data['bio']);

        AuditLog::record(Auth::id(), 'admin', 'doctor_created', "Created doctor account {$data['email']} ({$staffCode})");

        View::flash('success', "Doctor added. Staff code: {$staffCode} — initial password: {$plainPassword} (shown once, share with the doctor directly).");
        Router::redirect('/admin/doctors');
    }

    public function edit(string $id): void
    {
        Auth::requireRole('admin');
        $doctor = Doctor::find((int) $id);
        if (!$doctor) {
            Router::redirect('/admin/doctors');
        }
        View::render('admin/doctors/form', [
            'doctor' => $doctor,
            'specializations' => Specialization::all(),
        ]);
    }

    public function update(string $id): void
    {
        Auth::requireRole('admin');
        $doctorId = (int) $id;
        $doctor = Doctor::find($doctorId);
        if (!$doctor) {
            Router::redirect('/admin/doctors');
        }
        if (!Csrf::verifyRequest()) {
            Router::redirect("/admin/doctors/{$doctorId}/edit");
        }

        $data = $this->collectInput();
        $validator = new Validator($data);
        $validator->required('specialization_id')
            ->required('qualification')
            ->required('consultation_fee')->numeric('consultation_fee');

        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->firstErrors();
            Router::redirect("/admin/doctors/{$doctorId}/edit");
        }

        Doctor::update($doctorId, (int) $data['specialization_id'], $data['qualification'], (float) $data['consultation_fee'], (int) ($data['slot_duration'] ?: 20), $data['bio']);
        User::updateProfile((int) $doctor['user_id'], $data['name'] ?: $doctor['name'], $data['phone'] ?: $doctor['phone']);

        AuditLog::record(Auth::id(), 'admin', 'doctor_updated', "Updated doctor #{$doctorId}");
        View::flash('success', 'Doctor profile updated.');
        Router::redirect('/admin/doctors');
    }

    public function deactivate(string $id): void
    {
        Auth::requireRole('admin');
        if (!Csrf::verifyRequest()) {
            Router::redirect('/admin/doctors');
        }
        Doctor::setStatus((int) $id, 'inactive');
        AuditLog::record(Auth::id(), 'admin', 'doctor_deactivated', "Doctor #{$id}");
        View::flash('success', 'Doctor deactivated. They no longer appear in patient search.');
        Router::redirect('/admin/doctors');
    }

    public function activate(string $id): void
    {
        Auth::requireRole('admin');
        if (!Csrf::verifyRequest()) {
            Router::redirect('/admin/doctors');
        }
        Doctor::setStatus((int) $id, 'active');
        AuditLog::record(Auth::id(), 'admin', 'doctor_activated', "Doctor #{$id}");
        View::flash('success', 'Doctor reactivated.');
        Router::redirect('/admin/doctors');
    }

    public function importForm(): void
    {
        Auth::requireRole('admin');
        View::render('admin/doctors/import', ['result' => null]);
    }

    public function import(): void
    {
        Auth::requireRole('admin');
        if (!Csrf::verifyRequest()) {
            Router::redirect('/admin/doctors/import');
        }

        if (empty($_FILES['roster']['tmp_name']) || $_FILES['roster']['error'] !== UPLOAD_ERR_OK) {
            View::flash('error', 'Please choose a valid .xlsx file.');
            Router::redirect('/admin/doctors/import');
        }

        $tmpPath = $_FILES['roster']['tmp_name'];
        $originalName = $_FILES['roster']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext !== 'xlsx' || $_FILES['roster']['size'] > 5 * 1024 * 1024) {
            View::flash('error', 'Upload must be a .xlsx file under 5MB.');
            Router::redirect('/admin/doctors/import');
        }

        $created = [];
        $skipped = [];
        $specializationsByName = [];
        foreach (Specialization::all() as $s) {
            $specializationsByName[strtolower($s['name'])] = (int) $s['id'];
        }

        try {
            $spreadsheet = IOFactory::load($tmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            View::flash('error', 'Could not read that spreadsheet: ' . $e->getMessage());
            Router::redirect('/admin/doctors/import');
        }

        // First row is a header (name, email, specialization, qualification, consultation_fee).
        foreach (array_slice($rows, 1) as $row) {
            $name = trim((string) ($row[0] ?? ''));
            $email = trim((string) ($row[1] ?? ''));
            $specName = trim((string) ($row[2] ?? ''));
            $qualification = trim((string) ($row[3] ?? ''));
            $fee = (float) ($row[4] ?? 0);

            if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if (User::emailExists($email)) {
                $skipped[] = "{$name} <{$email}> — email already exists";
                continue;
            }
            $specId = $specializationsByName[strtolower($specName)] ?? null;
            if ($specId === null) {
                $skipped[] = "{$name} <{$email}> — unknown specialization \"{$specName}\"";
                continue;
            }

            [$userId, $plainPassword, $staffCode] = $this->createDoctorUser($name, $email, '');
            Doctor::create($userId, $specId, $qualification ?: 'MBBS', $fee, 20, '');
            $created[] = ['name' => $name, 'email' => $email, 'staff_code' => $staffCode, 'password' => $plainPassword];
        }

        AuditLog::record(Auth::id(), 'admin', 'doctor_bulk_import', count($created) . ' created, ' . count($skipped) . ' skipped');

        View::render('admin/doctors/import', ['result' => ['created' => $created, 'skipped' => $skipped]]);
    }

    private function collectInput(): array
    {
        return [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'specialization_id' => $_POST['specialization_id'] ?? '',
            'qualification' => trim($_POST['qualification'] ?? ''),
            'consultation_fee' => $_POST['consultation_fee'] ?? '',
            'slot_duration' => $_POST['slot_duration'] ?? '20',
            'bio' => trim($_POST['bio'] ?? ''),
        ];
    }

    /** @return array{0: int, 1: string, 2: string} [userId, plainPassword, staffCode] */
    private function createDoctorUser(string $name, string $email, string $phone): array
    {
        $plainPassword = self::randomPassword();
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $userId = User::createDoctorAccount($name, $email, $hash, $phone);
        $staffCode = 'DOC-' . str_pad((string) $userId, 4, '0', STR_PAD_LEFT);
        User::setStaffCode($userId, $staffCode);
        return [$userId, $plainPassword, $staffCode];
    }

    private static function randomPassword(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $out = '';
        for ($i = 0; $i < 10; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $out;
    }
}
