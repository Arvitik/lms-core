<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNotificationsTable extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');                  // Кому уведомление (совпадает с users.id)
            $table->string('type');                       // attendance | new_test | new_message
            $table->string('title');                      // Заголовок
            $table->text('body');                         // Текст уведомления
            $table->text('data')->nullable();             // JSON: доп. данные (ссылка, id)
            $table->boolean('is_read')->default(false);   // Прочитано?
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'is_read']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
}
