<?php

namespace App\Library;

use App\Definition;
use App\Http\Requests\AddLectureRequest;
use App\Http\Requests\UpdateLectureRequest;
use App\Services\PublicFileStorage;
use App\Testing\Lecture;
use App\Testing\Theme;
use App\Theorem;
use Carbon\Carbon;
use DateTime;

class LectureDAO
{
    public function allLecture()
    {
        return Lecture::all();
    }

    public function getLecture($index)
    {
        return Lecture::where('lecture_number', $index)->firstOrFail();
    }

    public function storeLecture(AddLectureRequest $request)
    {
        $lecture = new Lecture();

        if ($request->hasFile('doc_file')) {
            if (!$request->file('doc_file')->isValid()) {
                return 'Ошибка при загрузке DOC-файла';
            }
            $lecture->doc_path = PublicFileStorage::store($request->file('doc_file'), 'download/doc', 'TA_lec');
            $lecture->doc_updated_at = Carbon::now();
        }

        if ($request->hasFile('ppt_file')) {
            if (!$request->file('ppt_file')->isValid()) {
                return 'Ошибка при загрузке презентации';
            }
            $lecture->ppt_path = PublicFileStorage::store($request->file('ppt_file'), 'download/ppt', 'TA_lec');
            $lecture->ppt_updated_at = Carbon::now();
        }

        $lecture->lecture_name = $request->lecture_name;
        $lecture->lecture_text = $request->lecture_text;
        if (trim((string) $request->lecture_text) !== '') {
            $lecture->text_updated_at = Carbon::now();
        }
        $lecture->id_section = $request->id_section;

        $currentNumber = Lecture::where('id_section', '<=', $lecture->id_section)->count();
        Lecture::where('lecture_number', '>', $currentNumber)->increment('lecture_number');

        $lecture->lecture_number = $currentNumber + 1;
        $lecture->date = new DateTime();
        $lecture->save();

        return 'ok';
    }

    public function updateLecture(UpdateLectureRequest $request, $id)
    {
        $lecture = Lecture::findOrFail($id);
        $lecture->lecture_name = $request->lecture_name;

        if ($lecture->lecture_text !== $request->lecture_text) {
            $lecture->lecture_text = $request->lecture_text;
            $lecture->text_updated_at = Carbon::now();
        }

        if ($request->hasFile('doc_file')) {
            if (!$request->file('doc_file')->isValid()) {
                return 'Ошибка при загрузке DOC-файла';
            }
            $oldPath = $lecture->doc_path;
            $lecture->doc_path = PublicFileStorage::store($request->file('doc_file'), 'download/doc', 'TA_lec');
            PublicFileStorage::delete($oldPath);
            $lecture->doc_updated_at = Carbon::now();
        }

        if ($request->hasFile('ppt_file')) {
            if (!$request->file('ppt_file')->isValid()) {
                return 'Ошибка при загрузке презентации';
            }
            $oldPath = $lecture->ppt_path;
            $lecture->ppt_path = PublicFileStorage::store($request->file('ppt_file'), 'download/ppt', 'TA_lec');
            PublicFileStorage::delete($oldPath);
            $lecture->ppt_updated_at = Carbon::now();
        }

        $lecture->save();

        return 'ok';
    }

    public function deleteLecture($id)
    {
        $lecture = Lecture::findOrFail($id);

        if (!PublicFileStorage::delete($lecture->doc_path)) {
            return 'Ошибка удаления DOC-файла';
        }
        if (!PublicFileStorage::delete($lecture->ppt_path)) {
            return 'Ошибка удаления презентации';
        }

        Definition::where('id_lecture', $id)->update([
            'id_lecture' => null,
            'name_anchor' => null,
        ]);
        Theorem::where('id_lecture', $id)->update([
            'id_lecture' => null,
            'name_anchor' => null,
        ]);
        Theme::where('id_lecture', $id)->update(['id_lecture' => null]);
        Lecture::where('lecture_number', '>', $lecture->lecture_number)->decrement('lecture_number');
        $lecture->delete();

        return 'ok';
    }
}
