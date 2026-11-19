<?php

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
        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_account_id')->constrained()->cascadeOnDelete();
            $table->string('folder')->default('INBOX');
            $table->string('external_uid');
            $table->string('message_id')->nullable();
            $table->string('thread_key');
            $table->foreignId('parent_message_id')->nullable()->constrained('mail_messages')->nullOnDelete();
            $table->string('subject');
            $table->string('from_name')->nullable();
            $table->string('from_email');
            $table->text('recipients')->nullable();
            $table->text('cc_recipients')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['mail_account_id', 'folder', 'external_uid'], 'mail_messages_account_folder_uid_unique');
            $table->index(['mail_account_id', 'thread_key', 'received_at'], 'mail_messages_thread_index');
            $table->index(['mail_account_id', 'message_id'], 'mail_messages_message_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_messages');
    }
};
