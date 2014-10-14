<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        Schema::create('users', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password', 60);
            $table->string('avatar');
            $table->date('birthday');
            $table->text('bio');
            $table->string('gender');
            $table->string('mobile_no');
            $table->string('country');
            $table->string('reset_password_code');
            $table->string('remember_token');
            $table->enum('activated',[0,1]);
            $table->string('activation_code');
            $table->dateTime('activated_at');
            $table->timestamps();
        });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists("users");
	}

}
