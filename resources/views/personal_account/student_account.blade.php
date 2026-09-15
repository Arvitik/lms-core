@extends('templates.base')
@section('head')
    <meta name="csrf_token" content="{{ csrf_token() }}" />
    <title>Личный кабинет</title>
    {!! HTML::style('css/bootstrap.css') !!}
    {!! HTML::style('css/materialadmin.css') !!}
    {!! HTML::style('css/full.css') !!}
    {!! HTML::style('css/student_account.css') !!}
@stop

@section('background')
    full
@stop

@section('content')

    <div class="card style-default-light">
                    {{--<div class="card-body test-list">--}}
                    <h2 class="text-center">Личный кабинет</h2>
                    <h3 class="text-center">{{ $user['first_name'] }} {{ $user['last_name'] }} <b>{{ $user['email'] }}</b></h3>
                        <a href="{{ route('exam_schedules.student') }}" class="btn btn-info col-md-offset-3 col-md-6" style="margin-top: 0.5%">Мои контрольные работы</a>
                        <a href="{{ route('current_control.student_schedule') }}" class="btn btn-info col-md-offset-3 col-md-6" style="margin-top: 0.5%">Моё расписание занятий</a>
                        <a href="{{ route('test_results')}}" class="btn btn-warning col-md-offset-3 col-md-6 ">Перейти на страницу результатов системы тестирования</a>
        <a href="{{ route('student_сabinet')}}" class="btn btn-warning col-md-offset-3 col-md-6 " style="margin-top: 0.5%">Перейти на страницу "Заказы книг" </a>


        @foreach($course_plan->section_plans as $ind => $section_plan)
            @php
                $lecturePassesForSection = isset($statement_lecture['lecture_passes'][$section_plan->section_num]) ? $statement_lecture['lecture_passes'][$section_plan->section_num] : collect();
                $seminarPassesForSection = isset($statement_seminar['seminar_passes_sections'][$section_plan->section_num]) ? $statement_seminar['seminar_passes_sections'][$section_plan->section_num] : collect();
                $sectionResult = $statement_result['sections'][$ind] ?? [];
            @endphp
            <div class="col-md-12 col-sm-12 style-gray">
                <h3 class="text-default-bright">Раздел {{$section_plan->section_num}}</h3>
            </div>
            <div class="col-md-12 col-sm-12 card test-list">
                <table class="table table-condensed table-bordered">
                    <tbody>
                    <tr>
                        <td class="warning">Посещение лекций</td>
                        @foreach($lecturePassesForSection as $lecture_pass)
                        <td>
                            №{{$lecture_pass->lecture_plan_num}}
                            <div class='checkbox checkbox-inline checkbox-styled'>
                                <label>
                                    @if( $lecture_pass->presence == 1 )
                                        <input type='checkbox' checked onclick="this.checked=!this.checked;">
                                    @else
                                        <i class="md md-remove"></i>
                                    @endif
                                    <span></span>
                                </label>
                            </div>
                        </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="warning">Посещение семинаров</td>
                        @foreach($seminarPassesForSection as $seminar_pass)
                        <td>
                            №{{$seminar_pass->seminar_plan_num}}
                            <div class='checkbox checkbox-inline checkbox-styled'>
                                <label>
                                    @if( $seminar_pass->presence == 1 )
                                        <input type='checkbox' checked onclick="this.checked=!this.checked;">
                                    @else
                                        <i class="md md-remove"></i>
                                    @endif
                                    <span></span>
                                </label>
                            </div>
                        </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="warning">Работа на семинарах</td>
                        @foreach($seminarPassesForSection as $seminar_pass)
                        <td>
                            {{'№' . $seminar_pass->seminar_plan_num . ' кол.баллов: ' . $seminar_pass->work_points}}
                        </td>
                        @endforeach
                    </tr>
                    </tbody>
                </table>
                <table class="table table-condensed table-bordered">
                    <tbody>
                    <tr>
                        @foreach($section_plan->control_work_plans as $control_work_plan)
                        <td class="info">
                            <div class="dropdown">
                                <button class="dropbtn">{{$control_work_plan->control_work_plan_name}}</button>
                                <div class="dropdown-content">
                                    <a>{{'Макс: ' . $control_work_plan->max_points}}</a>
                                </div>
                            </div>
                        </td>
                        @endforeach
                        <td class="info">
                            <div class="dropdown">
                                <button class="dropbtn">ПЛ</button>
                                <div class="dropdown-content">
                                    <a>{{'Макс: ' . $section_plan->max_lecture_pass_point}}</a>
                                </div>
                            </div>
                        </td>
                        <td class="info">
                            <div class="dropdown">
                                <button class="dropbtn">ПС</button>
                                <div class="dropdown-content">
                                    <a>{{'Макс: ' . $section_plan->max_seminar_pass_point}}</a>
                                </div>
                            </div>
                        </td>
                        <td class="info">
                            <div class="dropdown">
                                <button class="dropbtn">РС</button>
                                <div class="dropdown-content">
                                    <a>{{'Макс: ' . $section_plan->max_seminar_work_point}}</a>
                                </div>
                            </div>
                        </td>
                        <td class="info">
                            <div class="dropdown">
                                <button class="dropbtn">{{'Итог за ' . $section_plan->section_num . ' раздел'}}</button>
                                <div class="dropdown-content">
                                    <a>{{'Макс: ' . $section_plan->getOverallMaxPoints()}}</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        @foreach(($sectionResult['controls'] ?? []) as $control_ind => $control)
                        @php
                            $controlPlan = $section_plan->control_work_plans->get($control_ind);
                            $isControlFailed = $controlPlan ? ($control['points'] < $controlPlan->max_points * 0.6) : false;
                        @endphp
                        <td
                                @if ($controlPlan && $isControlFailed)
                                class="danger"
                                @else
                                class="success"
                                @endif
                        >
                            {{ $control['points'] }}
                        </td>
                        @endforeach
                        {{-- ПЛ --}}
                        <td>{{$sectionResult['lecture'] ?? 0}}</td>
                        {{-- ПС --}}
                        <td>{{ isset($sectionResult['seminar']['presence_points']) ? $sectionResult['seminar']['presence_points'] : 0 }}</td>
                        {{-- РС --}}
                        <td>{{ isset($sectionResult['seminar']['work_points']) ? $sectionResult['seminar']['work_points'] : 0 }}</td>
                        {{--Итог за раздел--}}
                        <td data-result-section_num="{{$section_plan->section_num}}"
                            class="{{($sectionResult['total_ok'] ?? false) ? 'success' : 'danger'}}"
                            data-section-max_points="{{$section_plan->max_points}}">
                            {{$sectionResult['total'] ?? 0}}
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            @endforeach

        <div class="col-md-12 col-sm-12 style-gray">
            <h3 class="text-default-bright">Итоги</h3>
        </div>
        <div class="col-md-12 col-sm-12 card test-list">
            <table class="table table-condensed table-bordered">
                <tr>
                    <td class="info">
                        <div class="dropdown">
                            <button class="dropbtn">Итог за разделы</button>
                            <div class="dropdown-content">
                                <a>{{'Макс: ' . $course_plan->max_semester}}</a>
                            </div>
                        </div>
                    </td>
                    <td class="info">
                        <div class="dropdown">
                            <button class="dropbtn">Экзамен</button>
                            <div class="dropdown-content">
                                <a>{{'Макс: ' . $course_plan->max_exam}}</a>
                            </div>
                        </div>
                    </td>
                    <td class="info">
                        <div class="dropdown">
                            <button class="dropbtn">Суммарный итог</button>
                            <div class="dropdown-content">
                                <a>Макс: {{$course_plan->max_semester + $course_plan->max_exam}} баллов</a>
                            </div>
                        </div>
                    </td>
                    <td class="info">
                        <div class="dropdown">
                            <button class="dropbtn">Оценка</button>
                            <div class="dropdown-content">
                                <a>От E до A баллов</a>
                            </div>
                        </div>
                    </td>
                </tr>
                <tbody>
                    <tr>
                        <td class="{{$statement_result['sections_total_ok'] ? 'success' : 'danger'}}">
                            {{$statement_result['sections_total']}}
                        </td>
                        <td class="{{$statement_result['exams_total_ok'] ? 'success' : 'danger'}}">
                            {{$statement_result['exams_total']}}
                        </td>
                        <td class="{{$statement_result['summary_total_ok'] ? 'success' : 'danger'}}">
                            {{$statement_result['summary_total']}}
                        </td>
                        <td class="{{$statement_result['summary_total_ok'] ? 'success' : 'danger'}}">
                            {{$statement_result['mark_bologna']}}
                        </td>
                    </tr>
                </tbody>
            </table>

        </div>
    </div>
    <div class="section-screenshots">
        <h2>Скриншоты сданных контрольных</h2>
        @foreach($screenshots as $shot)
            <a href="{{ $shot }}" target="_blank">{{ basename($shot) }}</a>
        @endforeach
    </div>
@stop

@section('js-down')
@stop
