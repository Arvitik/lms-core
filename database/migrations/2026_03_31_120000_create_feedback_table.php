<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFeedbackTable extends Migration
{
    public function up()
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('target_type', ['lecture', 'test', 'course']);
            $table->unsignedInteger('target_id')->nullable(); // null для курса в целом
            $table->unsignedInteger('user_id')->nullable();   // null если анонимно
            $table->tinyInteger('rating');                    // 1-5
            $table->text('comment')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('feedback');
    }
}
