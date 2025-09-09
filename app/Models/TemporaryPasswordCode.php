<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


/**
 *  Class Employer
 *
 * @property int    id
 * @property int    $user_id
 * @property string code
 * @property bool is_used
 * @property Carbon created_at
 * @property Carbon updated_at
 */

class TemporaryPasswordCode extends Model
{
    use HasFactory;

    /**
     * The table name associated with the model.
     *
     * @var  string
     */
    protected $table = 'temporary_password_codes';

}
