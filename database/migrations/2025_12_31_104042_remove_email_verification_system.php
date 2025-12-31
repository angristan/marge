<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('email_verifications');

        if (Schema::hasColumn('comments', 'email_verified')) {
            Schema::table('comments', function (Blueprint $table): void {
                $table->dropColumn('email_verified');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->boolean('email_verified')->default(false)->after('status');
        });

        Schema::create('email_verifications', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 255);
            $table->string('token', 64)->unique();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['email', 'verified_at']);
        });
    }
};
