<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $guarded = [];

    protected $table = "booking";

    protected $fillable = [
        'user_id',
        'place_id',
        'day',
        'time',
        'person_number',
        'description',
        'special_day',
        'contact_choice',
        'status',
        'comment_status',

    ];


    public function getUser()
    {
        return $this->belongsTo('App\Models\User', 'user_id', 'id');
    }


    public function getPlaces()
    {
        return $this->hasMany('App\Models\Place','id','place_id');
    }

    public function getSpecial()
    {
        return $this->hasMany('App\Models\Specialday', 'booking_id');
    }

}
