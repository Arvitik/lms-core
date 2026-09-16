<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddContentUpdateDates extends Migration
{
    public function up()
    {
        Schema::table('lectures', function (Blueprint $table) {
            $table->timestamp('text_updated_at')->nullable()->after('lecture_text');
            $table->timestamp('doc_updated_at')->nullable()->after('doc_path');
            $table->timestamp('ppt_updated_at')->nullable()->after('ppt_path');
        });

        Schema::table('educational_materials', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('file_path');
            $table->boolean('archived')->default(false)->after('updated_at');
        });

        DB::table('lectures')->whereNotNull('lecture_text')->update([
            'text_updated_at' => DB::raw('CAST(`date` AS DATETIME)'),
        ]);
        DB::table('lectures')->whereNotNull('doc_path')->update([
            'doc_updated_at' => DB::raw('CAST(`date` AS DATETIME)'),
        ]);
        DB::table('lectures')->whereNotNull('ppt_path')->update([
            'ppt_updated_at' => DB::raw('CAST(`date` AS DATETIME)'),
        ]);

        DB::table('educational_materials')
            ->whereIn('name', [
                'Пробный вариант',
                'Допвопросы для подготовки к экзамену',
            ])
            ->update(['archived' => 1]);
    }

    public function down()
    {
        Schema::table('lectures', function (Blueprint $table) {
            $table->dropColumn(['text_updated_at', 'doc_updated_at', 'ppt_updated_at']);
        });

        Schema::table('educational_materials', function (Blueprint $table) {
            $table->dropColumn(['updated_at', 'archived']);
        });
    }
}
