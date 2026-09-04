<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per request to the MCP endpoint: who, with which credential, which
 * tool, did it succeed, how long. What an assistant did on someone's behalf
 * should be answerable after the fact. Pruned after McpToolCall::RETENTION_DAYS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_tool_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('token_id', 80)->nullable()->index();
            $table->string('client_name')->nullable();
            $table->string('method', 64);
            $table->string('tool')->nullable();
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('duration_ms');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_tool_calls');
    }
};
