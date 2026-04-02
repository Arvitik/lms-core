@extends('templates.base')
@section('head')
    <meta name="csrf_token" content="{{ csrf_token() }}" />
    <title>Личный кабинет</title>
    {!! HTML::style('css/bootstrap.css') !!}
    {!! HTML::style('css/materialadmin.css') !!}
    {!! HTML::style('css/full.css') !!}
@stop

@section('background')
    full
@stop

@section('content')
    <div class="container page-shell account-page">
        <div class="row">
            <div class="col-lg-10 col-lg-offset-1 col-md-10 col-md-offset-1 col-sm-12">
                <div class="card">
                    <div class="card-body">
                <h2 class="text-center">Результаты тестов всех пользователей</h2>
                <div class="form">
                    <form action="">
                        <div class="form-group">
                            <select name="test" id="tests" class="form-control" size="1">
                                <option value="">Все</option>
                                @for ($i=0; $i<$amount; $i++)
                                    <option value="{{ $tests[$i] }}" {{ !empty($request_test) && $request_test == $tests[$i] ? 'selected' : '' }}>{{ $names[$i] }}</option>
                                @endfor
                            </select>
                            <label for="select-type">Тесты</label>
                        </div>
                        <div class="form-group col-md-6">
                            <textarea  class="form-control textarea3" rows="1" id="regexp2" placeholder="" name="surname">{{ !empty($request_surname) ? $request_surname : '' }}</textarea>
                            <label for="textarea3">Фаимилия студета</label>
                        </div>
                        <div class="form-group col-md-6">
                            <select name="group" class="form-control">
                                <option value="">-- Все группы --</option>
                                @foreach($group_list as $group_name)
                                    <option value="{{ $group_name }}" {{ !empty($request_group) && $request_group == $group_name ? 'selected' : '' }}>
                                        {{ $group_name }}
                                    </option>
                                @endforeach
                            </select>
                            <label for="group">Группа</label>
                        </div>
                        <div class="form-group col-md-6">
                            <textarea  class="form-control textarea3" id="regexp" rows="1" placeholder="" name="mark">{{ !empty($request_mark) ? $request_mark : '' }}</textarea>
                            <label for="textarea3">Оценка</label>
                        </div>
                        <input type="submit" value="Применить" class="btn btn-primary">
                    </form>
                </div>
                <br>
                <br>
                {{ $resultsQuery->appends(request()->query())->links() }}
                <table class="table table-condensed table-hover">
                    <tr>
                        <td>Группа</td>
                        <td>Фамилия</td>
                        <td>Имя</td>
                        <td>Тест</td>
                        <td>Результат</td>
                        <td>Дата и время</td>
                        <td>Оценка</td>
                    </tr>
                    <tbody id="target">
                        @for ($i=(count($results)-1); $i>=0; $i--)
                            <tr>
                                <td>{{ $groups[$i] }}</td>
                                <td>{{ $last_names[$i] }}</td>
                                <td>{{ $first_names[$i] }}</td>
                                <td>{{ $test_names[$i] }}</td>
                                <td>{{ $results[$i] }}</td>
                                <td>{{ $result_dates[$i] }}</td>
                                <td>{{ $marks[$i] }}</td>
                            </tr>

                        @endfor
                    </tbody>
                </table>
                {{ $resultsQuery->appends(request()->query())->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js-down')
@stop
