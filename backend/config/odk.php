<?php

return [
    'roles' => [
        'admin',
        'form_manager',
        'assessor',
        'reviewer',
        'reports_viewer',
        'integration_admin',
    ],

    'statuses' => [
        'forms' => ['draft', 'published', 'retired', 'archived'],
        'exercises' => ['draft', 'scheduled', 'active', 'closed', 'archived'],
        'assignments' => ['assigned', 'in_progress', 'submitted', 'reviewed', 'cancelled', 'expired'],
        'mobile' => ['draft', 'ready_to_submit', 'pending_upload', 'uploading', 'sync_failed', 'submitted'],
        'submissions' => [
            'submitted',
            'under_review',
            'needs_clarification',
            'reviewed',
            'delivery_pending',
            'delivered_to_nicare',
            'delivery_failed',
            'archived',
        ],
    ],
];
