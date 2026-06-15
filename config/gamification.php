<?php

return [
    // XP за тип события (см. GamificationService::recordEvent)
    'xp_per_event' => [
        'couple_activated' => 20,
        'task_completed' => 10,
        'daily_checkin' => 8,
        'wishlist_added' => 5,
        'anniversary' => 25,
        'default' => 3,
    ],

    // Пороги XP для уровней 1..N (level = число порогов <= xp)
    'level_thresholds' => [0, 100, 250, 500, 1000, 2000, 4000, 8000],

    'streak' => [
        // upside-only: бонус, если оба партнёра были активны в день (применяется отдельно, не штраф)
        'both_acted_bonus_xp' => 5,
    ],
];
