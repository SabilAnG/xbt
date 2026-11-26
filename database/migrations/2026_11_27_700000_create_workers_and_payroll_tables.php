<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pekerja bengkel: data pekerja, absensi harian (+ lembur), kasbon, dan
 * penggajian mingguan (upah harian + uang makan + lembur − kasbon).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('position', 100)->nullable();
            $table->decimal('daily_wage', 16, 2)->default(0);
            $table->decimal('meal_allowance', 16, 2)->default(0);
            $table->decimal('overtime_rate', 16, 2)->default(0);
            $table->date('joined_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('worker_payrolls', function (Blueprint $table) {
            $table->id();
            $table->string('payroll_number')->unique();
            $table->foreignId('worker_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('days_full', 8, 2)->default(0);
            $table->decimal('days_half', 8, 2)->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            // Tarif saat slip dibuat (tetap walau data pekerja berubah).
            $table->decimal('daily_wage', 16, 2)->default(0);
            $table->decimal('meal_allowance', 16, 2)->default(0);
            $table->decimal('overtime_rate', 16, 2)->default(0);
            $table->decimal('wage_total', 16, 2)->default(0);
            $table->decimal('meal_total', 16, 2)->default(0);
            $table->decimal('overtime_total', 16, 2)->default(0);
            $table->decimal('gross_pay', 16, 2)->default(0);
            $table->decimal('advance_deduction', 16, 2)->default(0);
            $table->decimal('net_pay', 16, 2)->default(0);
            $table->string('status', 10)->default('draft');
            $table->date('paid_at')->nullable();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deduction_expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->date('worked_on');
            // hadir | setengah | izin | sakit | alpa | libur
            $table->string('status', 10)->default('hadir');
            $table->decimal('overtime_hours', 6, 2)->default(0);
            $table->string('notes')->nullable();
            $table->foreignId('worker_payroll_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['worker_id', 'worked_on']);
        });

        Schema::create('worker_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->restrictOnDelete();
            $table->date('given_at');
            $table->decimal('amount', 16, 2);
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_advances');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('worker_payrolls');
        Schema::dropIfExists('workers');
    }
};
