<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// `make benchmark-http`: the row the database route reads, in a table shaped like OZone's oz_countries.
class Country extends Model
{
	protected $table = 'countries';

	protected $primaryKey = 'cc2';

	protected $keyType = 'string';

	public $incrementing = false;

	public $timestamps = false;
}
