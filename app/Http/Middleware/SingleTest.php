<?php

namespace App\Http\Middleware;

use App\Testing\Result;
use App\Testing\Test;
use Auth;
use Closure;
use Illuminate\Support\Facades\Log;

class SingleTest
{
    /**
     * Запрещает проходить более одного теста одновременно
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $active_result = Result::whereId(Auth::user()['id'])->whereResult(null)->select('id_test')->first();
        if ($active_result){
            $active_test_id = $active_result->id_test;
            $active_test = Test::whereId_test($active_test_id)->first();
            if (!$active_test) {
                $stale_results = Result::whereId(Auth::user()['id'])->whereResult(null)->select('id_result')->get();
                foreach ($stale_results as $stale_result) {
                    \App\Testing\TestTask::whereId_result($stale_result->id_result)->delete();
                }
                Result::whereId(Auth::user()['id'])->whereResult(null)->delete();
                return $next($request);
            }
            $active_is_adaptive = $active_test->is_adaptive === 1;

            $first_seg = $request->segment(1);
            $desired_is_adaptive = $first_seg !== 'questions';
            $desired_test_id = $request->segment(3);
            if (!$desired_test_id || !Test::whereId_test($desired_test_id)->first()) {
                return $next($request);
            }

            if ($active_is_adaptive ||
                $desired_is_adaptive !== $active_is_adaptive ||
                $active_test_id != $desired_test_id) {
                $active_is_adaptive = $active_is_adaptive ? 1 : 0;
                $desired_is_adaptive = $desired_is_adaptive ? 1 : 0;
                return redirect()->route('single_test', compact('active_test_id', 'active_is_adaptive',
                    'desired_test_id', 'desired_is_adaptive'));
            }
        }
        return $next($request);
    }
}
