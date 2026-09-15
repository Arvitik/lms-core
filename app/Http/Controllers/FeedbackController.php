<?php

namespace App\Http\Controllers;

use App\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FeedbackController extends Controller
{
    public function __construct()
    {
        $this->middleware('general_auth');
    }

    /**
     * Сохранить отзыв (AJAX).
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'target_type' => 'required|in:lecture,test,course',
            'target_id'   => 'nullable|integer',
            'rating'      => 'required|integer|min:1|max:5',
            'comment'     => 'nullable|string|max:1000',
            'is_anonymous'=> 'boolean',
        ]);

        $userId = Auth::id();
        $type   = $request->target_type;
        $tid    = $request->target_id ?: null;

        if (Feedback::alreadyLeft($userId, $type, $tid)) {
            return response()->json(['status' => 'already', 'message' => 'Вы уже оставили отзыв.'], 200);
        }

        Feedback::create([
            'target_type'  => $type,
            'target_id'    => $tid,
            'user_id'      => $userId,
            'rating'       => $request->rating,
            'comment'      => $request->comment,
            'is_anonymous' => $request->boolean('is_anonymous'),
        ]);

        return response()->json(['status' => 'ok', 'message' => 'Спасибо за отзыв!']);
    }

    /**
     * Страница аналитики — только для преподавателя/администратора.
     */
    public function analytics()
    {
        $role = Auth::user()->role;
        if (!in_array($role, ['Преподаватель', 'Админ'])) {
            abort(403);
        }

        // Средние оценки по лекциям
        $lectureStats = DB::table('feedback')
            ->join('lectures', function ($j) {
                $j->on('feedback.target_id', '=', 'lectures.id_lecture')
                  ->where('feedback.target_type', '=', 'lecture');
            })
            ->select(
                'lectures.id_lecture',
                'lectures.lecture_number',
                'lectures.lecture_name',
                DB::raw('ROUND(AVG(feedback.rating), 2) as avg_rating'),
                DB::raw('COUNT(feedback.id) as cnt')
            )
            ->groupBy('lectures.id_lecture', 'lectures.lecture_number', 'lectures.lecture_name')
            ->orderBy('lectures.lecture_number')
            ->get();

        // Средние оценки по тестам
        $testStats = DB::table('feedback')
            ->join('tests', function ($j) {
                $j->on('feedback.target_id', '=', 'tests.id_test')
                  ->where('feedback.target_type', '=', 'test');
            })
            ->select(
                'tests.id_test',
                'tests.test_name',
                'tests.test_type',
                DB::raw('ROUND(AVG(feedback.rating), 2) as avg_rating'),
                DB::raw('COUNT(feedback.id) as cnt')
            )
            ->groupBy('tests.id_test', 'tests.test_name', 'tests.test_type')
            ->orderBy('avg_rating')
            ->get();

        // Оценка курса в целом
        $courseStats = [
            'avg_rating' => Feedback::avgRating('course'),
            'cnt'        => Feedback::countFor('course'),
        ];

        // Динамика средней оценки по месяцам (последние 6 месяцев)
        $dynamics = DB::table('feedback')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('ROUND(AVG(rating), 2) as avg_rating'),
                DB::raw('COUNT(id) as cnt')
            )
            ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL 6 MONTH)"))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Последние 20 отзывов с комментариями
        $recentComments = DB::table('feedback')
            ->leftJoin('users', 'feedback.user_id', '=', 'users.id')
            ->leftJoin('lectures', function ($j) {
                $j->on('feedback.target_id', '=', 'lectures.id_lecture')
                  ->where('feedback.target_type', '=', 'lecture');
            })
            ->leftJoin('tests', function ($j) {
                $j->on('feedback.target_id', '=', 'tests.id_test')
                  ->where('feedback.target_type', '=', 'test');
            })
            ->whereNotNull('feedback.comment')
            ->where('feedback.comment', '<>', '')
            ->select(
                'feedback.id',
                'feedback.target_type',
                'feedback.target_id',
                'feedback.rating',
                'feedback.comment',
                'feedback.is_anonymous',
                'feedback.created_at',
                'users.first_name',
                'users.last_name',
                'lectures.lecture_name',
                'lectures.lecture_number',
                'tests.test_name'
            )
            ->orderBy('feedback.created_at', 'desc')
            ->limit(20)
            ->get();

        return view('feedback.analytics', compact(
            'lectureStats',
            'testStats',
            'courseStats',
            'dynamics',
            'recentComments'
        ));
    }
}
