<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMessagesTable extends Migration
{
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->increments('id');
            // Кто отправил (ID пользователя)
            $table->unsignedInteger('from_user_id');
            // Кому адресовано (ID получателя, обычно админ или преподаватель)
            $table->unsignedInteger('to_user_id');
            $table->string('subject')->nullable();      // Тема (можно оставить пустой)
            $table->text('body');                       // Тело сообщения
            $table->boolean('is_read')->default(false); // Прочитано/не прочитано
            $table->timestamps();                       // created_at + updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('messages');
    }
}
