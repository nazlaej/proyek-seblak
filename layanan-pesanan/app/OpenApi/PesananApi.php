<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: '/api/v1/pesanan'
)]
class PesananApi
{
    #[OA\Post(
        path: '/api/v1/pesanan',
        operationId: 'buatPesanan',
        summary: 'Membuat pesanan baru',
        tags: ['Pesanan'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nama_pelanggan', 'items'],
                properties: [
                    new OA\Property(
                        property: 'nama_pelanggan',
                        type: 'string',
                        example: 'Nadya'
                    ),
                    new OA\Property(
                        property: 'metode_pembayaran',
                        type: 'string',
                        nullable: true,
                        example: 'QRIS'
                    ),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(
                                    property: 'kode_menu',
                                    type: 'string',
                                    example: 'MIN001'
                                ),
                                new OA\Property(
                                    property: 'jumlah',
                                    type: 'integer',
                                    example: 2
                                )
                            ],
                            type: 'object'
                        )
                    )
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Pesanan berhasil dibuat'
            ),
            new OA\Response(
                response: 422,
                description: 'Data pesanan tidak valid'
            )
        ]
    )]
    public function buatPesanan(): void
    {
    }

    #[OA\Get(
        path: '/api/v1/pesanan',
        operationId: 'daftarPesanan',
        summary: 'Menampilkan semua pesanan',
        tags: ['Pesanan'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar pesanan berhasil ditampilkan'
            )
        ]
    )]
    public function daftarPesanan(): void
    {
    }

    #[OA\Get(
        path: '/api/v1/pesanan/{nomor}',
        operationId: 'detailPesanan',
        summary: 'Menampilkan detail pesanan',
        tags: ['Pesanan'],
        parameters: [
            new OA\Parameter(
                name: 'nomor',
                in: 'path',
                required: true,
                description: 'ID pesanan',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detail pesanan berhasil ditampilkan'
            ),
            new OA\Response(
                response: 404,
                description: 'Pesanan tidak ditemukan'
            )
        ]
    )]
    public function detailPesanan(): void
    {
    }

    #[OA\Post(
        path: '/api/v1/pesanan/{nomor}/perubahan-status',
        operationId: 'ubahStatusPesanan',
        summary: 'Mengubah status pesanan',
        tags: ['Pesanan'],
        parameters: [
            new OA\Parameter(
                name: 'nomor',
                in: 'path',
                required: true,
                description: 'ID pesanan',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(
                        property: 'status',
                        type: 'string',
                        enum: [
                            'dimasak',
                            'siap',
                            'selesai',
                            'dibatalkan'
                        ],
                        example: 'dimasak'
                    )
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Status pesanan berhasil diperbarui'
            ),
            new OA\Response(
                response: 404,
                description: 'Pesanan tidak ditemukan'
            ),
            new OA\Response(
                response: 409,
                description: 'Perubahan status tidak diperbolehkan'
            ),
            new OA\Response(
                response: 422,
                description: 'Data status tidak valid'
            )
        ]
    )]
    public function ubahStatusPesanan(): void
    {
    }
}
