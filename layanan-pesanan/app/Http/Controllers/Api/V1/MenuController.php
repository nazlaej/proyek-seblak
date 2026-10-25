<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class MenuController extends Controller
{
    #[OA\Get(
        path: '/api/v1/menu',
        operationId: 'daftarMenu',
        summary: 'Menampilkan daftar menu',
        description: 'Menampilkan semua menu yang tersedia. Daftar dapat difilter berdasarkan kategori.',
        tags: ['Menu'],
        parameters: [
            new OA\Parameter(
                name: 'kategori',
                description: 'Filter menu berdasarkan kategori',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar menu berhasil ditampilkan',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'kode', type: 'string'),
                                    new OA\Property(property: 'nama_menu', type: 'string'),
                                    new OA\Property(property: 'kategori', type: 'string'),
                                    new OA\Property(property: 'harga', type: 'number', format: 'float'),
                                    new OA\Property(property: 'satuan', type: 'string'),
                                    new OA\Property(property: 'tersedia', type: 'boolean'),
                                    new OA\Property(property: 'stok', type: 'integer')
                                ],
                                type: 'object'
                            )
                        )
                    ],
                    type: 'object'
                )
            )
        ]
    )]
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
                'stok',
            ]);

        return response()->json([
            'data' => $menus,
        ], 200);
    }
}
