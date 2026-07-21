<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorized_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone_e164')->unique(); // E.164 format: +519XXXXXXXXX
            $table->string('role')->default('operator'); // admin, operator, viewer
            $table->boolean('active')->default(true);
            $table->timestamp('allowed_from')->nullable(); // When contact can start authorizing
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Optional link to internal user
            $table->jsonb('metadata')->nullable(); // Additional info (department, notes, etc.)
            $table->timestamps();

            $table->index(['active', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorized_contacts');
    }
};