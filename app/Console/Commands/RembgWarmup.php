<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RembgWarmup extends Command
{
    protected $signature = 'rembg:warmup {--url=} {--cmd=}';
    protected $description = 'Warm up rembg model (call HTTP warmup or run wrapper command)';

    public function handle(): int
    {
        $url = $this->option('url') ?: config('nametag.bg_removal.rembg_warmup_url', config('nametag.bg_removal.rembg_url'));
        $cmd = $this->option('cmd') ?: env('NAMETAG_REMBG_WARMUP_CMD');

        if ($cmd) {
            $this->info("Running warmup command: $cmd");
            try {
                $out = null;
                $rc = null;
                exec($cmd . ' 2>&1', $out, $rc);
                $this->info("Exit: $rc");
                foreach ($out as $line) $this->line($line);
                return $rc === 0 ? 0 : 1;
            } catch (\Throwable $t) {
                $this->error('Warmup command failed: ' . $t->getMessage());
                Log::error('rembg:warmup command failed', ['err' => $t->getMessage()]);
                return 1;
            }
        }

        if ($url) {
            $this->info("Calling warmup URL: $url");
            try {
                $ctx = stream_context_create(['http' => ['timeout' => 5]]);
                $res = @file_get_contents($url, false, $ctx);
                if ($res === false) {
                    $this->error('Warmup HTTP call failed');
                    Log::warning('rembg:warmup http failed', ['url' => $url]);
                    return 1;
                }
                $this->info('Warmup HTTP OK');
                return 0;
            } catch (\Throwable $t) {
                $this->error('Warmup HTTP failed: ' . $t->getMessage());
                Log::warning('rembg:warmup http exception', ['err' => $t->getMessage(), 'url' => $url]);
                return 1;
            }
        }

        $this->error('No warmup URL or command configured (use --url or set NAMETAG_REMBG_WARMUP_CMD)');
        return 1;
    }
}
