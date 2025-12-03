<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Suhu extends Model
{
    use HasFactory;

    protected $table = 'suhus';

    protected $fillable = [
        'tanggal',
        'waktu',
        'suhu',
        'source'
    ];

    protected $casts = [
        'tanggal' => 'date',
        'suhu' => 'decimal:1',
    ];

    /**
     * Scope untuk data hari ini
     */
    public function scopeToday($query)
    {
        return $query->whereDate('tanggal', today());
    }

    /**
     * Scope untuk data bulan ini
     */
    public function scopeThisMonth($query)
    {
        return $query->whereYear('tanggal', now()->year)
            ->whereMonth('tanggal', now()->month);
    }

    /**
     * Scope untuk data dari ThingSpeak
     */
    public function scopeFromThingSpeak($query)
    {
        // Jika ada kolom source
        if (Schema::hasColumn($this->getTable(), 'source')) {
            return $query->where('source', 'thingspeak');
        }
        return $query;
    }

    /**
     * Scope untuk data manual
     */
    public function scopeManual($query)
    {
        if (Schema::hasColumn($this->getTable(), 'source')) {
            return $query->where('source', 'manual');
        }
        return $query;
    }
}