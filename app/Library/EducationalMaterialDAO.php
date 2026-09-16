<?php

namespace App\Library;

use App\EducationalMaterial;
use App\Http\Requests\AddEducationMaterialRequest;
use App\Http\Requests\EditEducationMaterialRequest;
use App\Services\PublicFileStorage;
use Carbon\Carbon;

class EducationalMaterialDAO
{
    public function allEducationalMaterial()
    {
        return EducationalMaterial::where('archived', 0)->orderBy('name')->get();
    }

    public function getEducationalMaterial($id)
    {
        return EducationalMaterial::findOrFail($id);
    }

    public function storeEducationalMaterial(AddEducationMaterialRequest $request)
    {
        if (!$request->hasFile('education_material_file') || !$request->file('education_material_file')->isValid()) {
            return 'Ошибка при загрузке файла';
        }

        $material = new EducationalMaterial();
        $material->file_path = PublicFileStorage::store(
            $request->file('education_material_file'),
            'download/educational_material',
            'material'
        );
        $material->name = $request->name;
        $material->updated_at = Carbon::now();
        $material->save();

        return 'ok';
    }

    public function updateEducationalMaterial(EditEducationMaterialRequest $request, $id)
    {
        $material = EducationalMaterial::findOrFail($id);
        $material->name = $request->name;

        if ($request->hasFile('education_material_file')) {
            if (!$request->file('education_material_file')->isValid()) {
                return 'Ошибка при загрузке файла';
            }
            $oldPath = $material->file_path;
            $material->file_path = PublicFileStorage::store(
                $request->file('education_material_file'),
                'download/educational_material',
                'material'
            );
            PublicFileStorage::delete($oldPath);
        }

        $material->updated_at = Carbon::now();
        $material->save();

        return 'ok';
    }

    public function deleteEducationalMaterial($id)
    {
        $material = EducationalMaterial::findOrFail($id);
        // Старые файлы могут находиться в подключенном read-only архиве.
        // Это не должно мешать удалить сам материал из базы.
        PublicFileStorage::delete($material->file_path);
        $material->delete();

        return 'ok';
    }
}
