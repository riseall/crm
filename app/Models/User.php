<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $connection = 'mysql';
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nik',
        'name',
        'email',
        'jabatan',
        'is_active',
        'tanggal_masuk',
        'tanggal_keluar',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tanggal_masuk' => 'date:Y-m-d',
        'tanggal_keluar' => 'date:Y-m-d',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'remember_token',
    ];

    public function coverages()
    {
        return $this->hasMany(Mapping::class, 'cov_nik', 'nik')->active();
    }
}
