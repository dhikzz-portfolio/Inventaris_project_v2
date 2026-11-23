<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DistributionController extends Controller
{
    /**
     * Halaman distribusi barang per merek/tipe/model. Pemilihan katalog →
     * spesifikasi → tujuan beserta validasi stok ditangani komponen Livewire.
     */
    public function index(Request $request): View
    {
        return view('distributions.index');
    }
}
