<?php

namespace App\Models\Concerns;

/**
 * Kolom relasi selalu dibaca sebagai integer. Sebagian driver MySQL di hosting
 * mengembalikan angka sebagai string sehingga perbandingan ketat (===) gagal.
 */
trait IntegerKeys
{
    public function initializeIntegerKeys(): void
    {
        $this->mergeCasts(array_fill_keys($this->integerKeys, 'integer'));
    }
}
