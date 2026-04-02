@extends('templates.base')
@section('content')
<div class="container">
    <h2>Посещение лекций (отметки старосты)</h2>
    <table class="table">
        <thead>
            <tr>
                <th>ФИО</th>
                @foreach ($lectures as $lecture)
                    <th>{{ \Carbon\Carbon::parse($lecture->date)->format('d.m') }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($students as $student)
                <tr>
                    <td>{{ $student->last_name }} {{ $student->first_name }}</td>
                    @foreach ($lectures as $lecture)
                        <td>
                            @if (isset($marks[$lecture->id_lecture]) && $marks[$lecture->id_lecture]->contains('id_user', $student->id))
                                ✅
                            @else
                                ❌
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
