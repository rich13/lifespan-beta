<?php

namespace App\Console\Commands;

use App\Models\InvitationCode;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateInvitationCode extends Command
{
    protected $signature = 'invite:generate {--count=1 : Number of codes to generate}';
    protected $description = 'Generate invitation codes for registration';

    public function handle(): void
    {
        $count = $this->option('count');
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $code = Str::random(8);
            InvitationCode::create(['code' => $code]);
            $codes[] = $code;
        }

        $this->info('Generated invitation codes:');
        foreach ($codes as $code) {
            $this->line($code);
        }
    }
} 