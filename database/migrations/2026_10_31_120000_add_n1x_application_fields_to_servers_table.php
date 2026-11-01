<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddN1xApplicationFieldsToServersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('domain')->nullable()->after('description');
            $table->string('runtime')->nullable()->default('node')->after('domain');
            $table->string('runtime_version')->nullable()->after('runtime');
            $table->boolean('is_native')->default(true)->after('runtime_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['domain', 'runtime', 'runtime_version', 'is_native']);
        });
    }
}

