<?php
// app/Models/Card.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Card extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'attachment',
        'user_id',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
