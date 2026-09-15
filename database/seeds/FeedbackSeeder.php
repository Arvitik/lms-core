<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeedbackSeeder extends Seeder
{
    public function run()
    {
        DB::table('feedback')->truncate();

        $students = DB::table('users')
            ->whereIn('role', ['Студент', 'Староста'])
            ->pluck('id')
            ->toArray();

        $lectures = DB::table('lectures')
            ->pluck('id_lecture')
            ->toArray();

        $tests = DB::table('tests')
            ->where('visibility', 1)
            ->whereIn('test_type', ['Тренировочный', 'Контрольный'])
            ->pluck('id_test')
            ->toArray();

        $lectureComments = [
            5 => ['Отличная лекция, всё понятно!', 'Очень доступно объяснено', 'Лучшая лекция курса'],
            4 => ['Хорошо, но хотелось бы больше примеров', 'Понятно, спасибо', 'Неплохо структурировано'],
            3 => ['Средне, местами запутанно', 'Можно было бы лучше', 'Нужно больше примеров'],
            2 => ['Сложно для понимания', 'Материал подан нечётко', ''],
            1 => ['Очень трудно воспринимать', '', ''],
        ];

        $testComments = [
            5 => ['Тест хорошо проверяет знания', 'Отличные вопросы!', 'Хорошо составлен'],
            4 => ['Интересные задания', 'Полезный тест', 'Почти всё понравилось'],
            3 => ['Некоторые вопросы неоднозначны', 'Средний по сложности', ''],
            2 => ['Слишком сложно', 'Вопросы не всегда понятны', ''],
            1 => ['Очень трудный тест', '', ''],
        ];

        $rows = [];
        $now  = \Carbon\Carbon::now();

        // Отзывы на лекции
        foreach ($lectures as $lectureId) {
            // Каждый студент с вероятностью 70% оставляет отзыв на лекцию
            foreach ($students as $userId) {
                if (mt_rand(1, 10) > 3) {
                    // Немного смещаем рейтинг: лекции 1-8 выше, 9-16 ниже
                    $bias   = $lectureId <= 8 ? 1 : 0;
                    $rating = min(5, max(1, mt_rand(3, 5) + $bias - mt_rand(0, 1)));
                    $comments = $lectureComments[$rating];
                    $comment  = $comments[array_rand($comments)];
                    $isAnon   = mt_rand(0, 4) === 0; // 20% анонимно

                    $rows[] = [
                        'target_type'  => 'lecture',
                        'target_id'    => $lectureId,
                        'user_id'      => $userId,
                        'rating'       => $rating,
                        'comment'      => $comment,
                        'is_anonymous' => $isAnon ? 1 : 0,
                        'created_at'   => $now->copy()->subDays(mt_rand(0, 60))->subHours(mt_rand(0, 23)),
                        'updated_at'   => $now,
                    ];
                }
            }
        }

        // Отзывы на тесты (берём первые 15 тестов)
        foreach (array_slice($tests, 0, 15) as $testId) {
            foreach ($students as $userId) {
                if (mt_rand(1, 10) > 4) {
                    $rating   = min(5, max(1, mt_rand(2, 5)));
                    $comments = $testComments[$rating];
                    $comment  = $comments[array_rand($comments)];
                    $isAnon   = mt_rand(0, 4) === 0;

                    $rows[] = [
                        'target_type'  => 'test',
                        'target_id'    => $testId,
                        'user_id'      => $userId,
                        'rating'       => $rating,
                        'comment'      => $comment,
                        'is_anonymous' => $isAnon ? 1 : 0,
                        'created_at'   => $now->copy()->subDays(mt_rand(0, 60))->subHours(mt_rand(0, 23)),
                        'updated_at'   => $now,
                    ];
                }
            }
        }

        // Отзывы на курс в целом
        $courseComments = [
            'Отличный курс, рекомендую всем!',
            'Очень полезно для понимания теории алгоритмов',
            'Хороший курс, но местами сложновато',
            'Материал интересный, подача хорошая',
            '',
        ];
        foreach ($students as $userId) {
            if (mt_rand(1, 10) > 5) {
                $rows[] = [
                    'target_type'  => 'course',
                    'target_id'    => null,
                    'user_id'      => $userId,
                    'rating'       => mt_rand(3, 5),
                    'comment'      => $courseComments[array_rand($courseComments)],
                    'is_anonymous' => 0,
                    'created_at'   => $now->copy()->subDays(mt_rand(0, 30)),
                    'updated_at'   => $now,
                ];
            }
        }

        // Вставляем пакетами по 100
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('feedback')->insert($chunk);
        }

        $this->command->info('Вставлено отзывов: ' . count($rows));
    }
}
