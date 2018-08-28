<?php

use Silber\Bouncer\Database\Models;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBouncerTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(Models::table('abilities'), function (Blueprint $table) {
            $table->uuid('uuid');
            $table->string('name', 150);
            $table->string('title')->nullable();
            $table->uuid('entity_uuid')->nullable();
            $table->string('entity_type', 150)->nullable();
            $table->boolean('only_owned')->default(false);
            $table->integer('scope')->nullable()->index();
            $table->timestamps();

            $table->primary('uuid');
        });

        Schema::create(Models::table('roles'), function (Blueprint $table) {
            $table->uuid('uuid');
            $table->string('name', 150);
            $table->string('title')->nullable();
            $table->integer('level')->unsigned()->nullable();
            $table->integer('scope')->nullable()->index();
            $table->timestamps();

            $table->primary('uuid');

            $table->unique(
                ['name', 'scope'],
                'roles_name_unique'
            );
        });

        Schema::create(Models::table('assigned_roles'), function (Blueprint $table) {
            $table->uuid('role_uuid')->index();
            $table->uuid('entity_uuid');
            $table->string('entity_type', 150);
            $table->integer('scope')->nullable()->index();

            $table->index(
                ['entity_uuid', 'entity_type', 'scope'],
                'assigned_roles_entity_index'
            );

            $table->foreign('role_uuid')
                  ->references('uuid')->on(Models::table('roles'))
                  ->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create(Models::table('permissions'), function (Blueprint $table) {
            $table->uuid('ability_uuid')->index();
            $table->uuid('entity_uuid');
            $table->string('entity_type', 150);
            $table->boolean('forbidden')->default(false);
            $table->integer('scope')->nullable()->index();

            $table->index(
                ['entity_uuid', 'entity_type', 'scope'],
                'permissions_entity_index'
            );

            $table->foreign('ability_uuid')
                  ->references('uuid')->on(Models::table('abilities'))
                  ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop(Models::table('permissions'));
        Schema::drop(Models::table('assigned_roles'));
        Schema::drop(Models::table('roles'));
        Schema::drop(Models::table('abilities'));
    }
}
