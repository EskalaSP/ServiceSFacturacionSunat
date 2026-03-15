<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // credit_notes: index for client_num_doc filtering
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->index(['tenant_id', 'client_num_doc'], 'cn_tenant_client_doc');
            $table->index(['tenant_id', 'created_at'], 'cn_tenant_created');
        });

        // debit_notes: same indexes
        Schema::table('debit_notes', function (Blueprint $table) {
            $table->index(['tenant_id', 'client_num_doc'], 'dn_tenant_client_doc');
            $table->index(['tenant_id', 'created_at'], 'dn_tenant_created');
        });

        // invoices: composite for created_at ordering (used by CheckDocumentLimit)
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'inv_tenant_created');
        });

        // boletas: same
        Schema::table('boletas', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'bol_tenant_created');
        });

        // dispatch_guides: created_at for listing + limit counting
        Schema::table('dispatch_guides', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'dg_tenant_created');
        });
    }

    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropIndex('cn_tenant_client_doc');
            $table->dropIndex('cn_tenant_created');
        });
        Schema::table('debit_notes', function (Blueprint $table) {
            $table->dropIndex('dn_tenant_client_doc');
            $table->dropIndex('dn_tenant_created');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('inv_tenant_created');
        });
        Schema::table('boletas', function (Blueprint $table) {
            $table->dropIndex('bol_tenant_created');
        });
        Schema::table('dispatch_guides', function (Blueprint $table) {
            $table->dropIndex('dg_tenant_created');
        });
    }
};
