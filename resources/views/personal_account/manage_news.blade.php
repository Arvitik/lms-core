@extends('templates.base')

@section('head')
    <title>Управление новостями</title>
    {!! HTML::style('css/bootstrap.css') !!}
    {!! HTML::style('css/materialadmin.css') !!}
    {!! HTML::style('css/full.css') !!}
@stop

@section('background')
    full
@stop

@section('content')
    <div class="col-lg-offset-1 col-lg-10 col-md-12">
        <div class="card">
            <div class="card-body">
                <h2 class="text-center">Управление новостями</h2>

                @foreach($news as $post)
                    <div class="card card-bordered {{ $post['is_visible'] == 1 ? 'style-warning' : 'style-gray-bright' }}" id="{{ $post['id'] }}">
                        <div class="card-head">
                            <header><i class="fa fa-fw fa-tag"></i>{{ $post['title'] }}</header>
                            <div class="tools">
                                <div class="btn-group">
                                    <a class="btn btn-icon-toggle btn-close show" name="{{ $post['id'] }}" title="Показать или скрыть новость">
                                        <i class="md md-remove-red-eye"></i>
                                    </a>
                                </div>
                                <div class="btn-group">
                                    <a class="btn btn-icon-toggle btn-close delete" name="{{ $post['id'] }}" title="Удалить новость">
                                        <i class="md md-close"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body style-default-bright">
                            <p>{{ $post['body'] }}</p>
                            @if($post['file_path'] != null)
                                {!! HTML::link($post['file_path'], 'Скачать файл', ['class' => 'btn btn-primary btn-raised', 'role' => 'button']) !!}
                            @endif
                        </div>
                    </div>
                @endforeach

                <hr>
                <h3>Добавить новость</h3>
                <form action="{{ route('add_news') }}" method="POST" class="form" enctype="multipart/form-data">
                    {!! csrf_field() !!}
                    <div class="form-group">
                        <label for="title">Заголовок новости</label>
                        <textarea name="title" id="title" class="form-control" rows="2" required>{{ old('title') }}</textarea>
                    </div>
                    <div class="form-group">
                        <label for="body">Текст новости</label>
                        <textarea name="body" id="body" class="form-control" rows="4" required>{{ old('body') }}</textarea>
                    </div>
                    <div class="form-group">
                        <label for="news-file">Прикреплённый файл</label>
                        <input id="news-file" type="file" class="form-control" name="file">
                    </div>
                    <button class="btn btn-primary btn-raised" type="submit">Добавить новость</button>
                </form>
            </div>
        </div>
    </div>
@stop

@section('js-down')
    {!! HTML::script('js/personal_account/delete_news.js') !!}
@stop
