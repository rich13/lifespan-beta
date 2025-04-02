<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InvitationCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvitationCodeSeeder extends Seeder
{
    public function run(): void
    {
        // Create some invitation codes
        $codes = [
            'BETA-2024-001',
            'BETA-2024-002',
            'BETA-2024-003',
            'BETA-2024-004',
            'BETA-2024-005',
        ];

        foreach ($codes as $code) {
            DB::table('invitation_codes')->updateOrInsert(
                ['code' => $code],
                [
                    'used' => false,
                    'id' => Str::uuid()
                ]
            );
        }
    }
} 