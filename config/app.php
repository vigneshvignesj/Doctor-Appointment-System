<?php

return [
    'timezone' => 'Asia/Kolkata',
    // Base path the app is served under. Empty string if served from the web root.
    'base_path' => '',
    // Default consultation slot length (minutes) used for one-off `extra_hours`
    // exceptions, which don't carry their own slot_duration_minutes column
    // (see doctor_availability_exceptions in database/schema.sql) — falls back
    // to a doctor's own default_slot_duration_minutes at the model layer.
    'default_slot_duration_minutes' => 20,
];
