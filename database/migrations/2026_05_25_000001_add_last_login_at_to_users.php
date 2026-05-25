<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('expires_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->index('last_login_at', 'users_last_login_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_last_login_idx');
            $table->dropColumn(['last_login_at', 'last_login_ip']);
        });
    }
};
