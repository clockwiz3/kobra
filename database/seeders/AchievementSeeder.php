<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

/**
 * Приватные, аддитивные + секретные ачивки. БЕЗ рефералов/лидербордов/ratio.
 */
class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            ['code' => 'first_task', 'title' => 'Первое общее дело', 'description' => 'Вы вместе закрыли первую задачу', 'points' => 10, 'is_secret' => false, 'sort_order' => 1, 'criteria' => ['type' => 'event_type', 'value' => 'task_completed']],
            ['code' => 'streak_7', 'title' => 'Неделя вместе', 'description' => 'Общий стрик 7 дней', 'points' => 20, 'is_secret' => false, 'sort_order' => 2, 'criteria' => ['type' => 'streak_gte', 'value' => 7]],
            ['code' => 'streak_30', 'title' => 'Месяц нежности', 'description' => 'Общий стрик 30 дней', 'points' => 50, 'is_secret' => false, 'sort_order' => 3, 'criteria' => ['type' => 'streak_gte', 'value' => 30]],
            ['code' => 'streak_100', 'title' => '100 дней вдвоём', 'description' => 'Общий стрик 100 дней', 'points' => 150, 'is_secret' => false, 'sort_order' => 4, 'criteria' => ['type' => 'streak_gte', 'value' => 100]],
            ['code' => 'level_5', 'title' => 'Сыгрались', 'description' => 'Достигли 5 уровня', 'points' => 0, 'is_secret' => false, 'sort_order' => 5, 'criteria' => ['type' => 'level_gte', 'value' => 5]],
            ['code' => 'first_anniversary', 'title' => 'Годовщина', 'description' => 'Секретная пасхалка к годовщине', 'points' => 100, 'is_secret' => true, 'sort_order' => 99, 'criteria' => ['type' => 'event_type', 'value' => 'anniversary']],
        ];

        foreach ($achievements as $row) {
            Achievement::updateOrCreate(['code' => $row['code']], $row);
        }
    }
}
