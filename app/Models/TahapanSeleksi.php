<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TahapanSeleksi extends Model
{
  use HasFactory;

  // kasih tau tabel yang ada di databasenya
  protected $table = 'tahapan_seleksi';

  // kasih tau primary key yang ada di tabel yang bersangkutan
  protected $primaryKey = 'id_tahapan';

  // set timestamps menjadi false, karena kalau pakai model otomatis dia memasukkan timestamps juga
  public $timestamps = false;

  // bawa relasinya ketika di query
  protected $with = ['lowongan'];

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'id_lowongan',
    'judul_tahapan',
    'ket_tahapan',
    'urutan_tahapan_ke',
    'slug'
  ];

  public function lowongan(): BelongsTo
  {
    return $this->belongsTo(LowonganKerja::class, 'id_lowongan', 'id_lowongan');
  }

  public function penilaian_seleksi(): HasMany
  {
    return $this->hasMany(PenilaianSeleksi::class, 'id_tahapan', 'id_tahapan');
  }

  public function getRouteKeyName()
  {
    return 'slug';
  }
}
