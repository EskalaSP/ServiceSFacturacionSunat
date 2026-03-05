<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->string('cod_local', 4)->default('0000')->after('tenant_id');
                $table->string('xml_path', 500)->nullable()->after('xml_content');
                $table->string('cdr_path', 500)->nullable()->after('cdr_content');
                $table->string('pdf_path', 500)->nullable()->after('cdr_path');
            });
        }

        Schema::table('dispatch_guides', function (Blueprint $table) {
            $table->string('cod_local', 4)->default('0000')->after('tenant_id');
            $table->string('xml_path', 500)->nullable()->after('xml_content');
            $table->string('cdr_path', 500)->nullable()->after('cdr_content');
            $table->string('pdf_path', 500)->nullable()->after('cdr_path');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn(['cod_local', 'xml_path', 'cdr_path', 'pdf_path']);
            });
        }

        Schema::table('dispatch_guides', function (Blueprint $table) {
            $table->dropColumn(['cod_local', 'xml_path', 'cdr_path', 'pdf_path']);
        });
    }
};
