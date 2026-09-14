<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// `make benchmark-http`: the table the database route reads, shaped like OZone's oz_countries, with
// the same single row.
return new class extends Migration
{
	public function up(): void
	{
		Schema::create('countries', static function (Blueprint $table): void {
			$table->string('cc2', 2)->primary();
			$table->string('calling_code', 6);
			$table->string('name');
			$table->string('name_real');
			$table->json('data');
			$table->boolean('is_valid')->default(true);
			$table->bigInteger('created_at');
			$table->bigInteger('updated_at');
			$table->boolean('deleted')->default(false);
			$table->bigInteger('deleted_at')->nullable();
		});

		$now = \time();

		DB::table('countries')->insert([
			'cc2'          => 'BJ',
			'calling_code' => '+229',
			'name'         => 'Benin',
			'name_real'    => 'Benin',
			'data'         => '{}',
			'is_valid'     => true,
			'created_at'   => $now,
			'updated_at'   => $now,
			'deleted'      => false,
			'deleted_at'   => null,
		]);
	}
};
