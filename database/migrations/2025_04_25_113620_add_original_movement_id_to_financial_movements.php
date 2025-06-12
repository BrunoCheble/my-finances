<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOriginalMovementIdToFinancialMovements extends Migration
{
    public function up()
    {
        Schema::table('financial_movements', function (Blueprint $table) {
            // Adiciona a coluna original_movement_id para armazenar o relacionamento com o movimento de origem
            $table->unsignedBigInteger('original_movement_id')->nullable()->after('id');

            // Adiciona a chave estrangeira que referencia a tabela financial_movements
            $table->foreign('original_movement_id')->references('id')->on('financial_movements')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('financial_movements', function (Blueprint $table) {
            // Remove a chave estrangeira e a coluna original_movement_id
            $table->dropForeign(['original_movement_id']);
            $table->dropColumn('original_movement_id');
        });
    }
}
