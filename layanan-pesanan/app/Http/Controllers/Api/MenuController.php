<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $query = Menu::query()
            ->where('tersedia', true);

        if ($request->filled('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        $menus = $query
            ->orderBy('kode')
            ->get([
                'kode',
                'nama_menu',
                'kategori',
                'harga',
                'satuan',
                'tersedia',
            ]);

        return response()->json([
            'data' => $menus,
        ], 200);
    }
}