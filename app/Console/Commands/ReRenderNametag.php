<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Services\NametagRenderService;

class ReRenderNametag extends Command
{
    protected $signature = 'nametag:rerender {ids*} {--front} {--back}';
    protected $description = 'Re-render nametag front/back for given employee IDs (use --front/--back to select)';

    public function handle(): int
    {
        $ids = $this->argument('ids');
        $doFront = $this->option('front');
        $doBack = $this->option('back');
        if (! $doFront && ! $doBack) { $doFront = $doBack = true; }

        $service = new NametagRenderService();
        foreach ($ids as $id) {
            $this->line("Processing id={$id}...");
            $e = Employee::find($id);
            if (! $e) { $this->error("Employee {$id} not found"); continue; }
            try {
                if ($doFront) {
                    $tplFront = config('nametag.templates.front.background');
                    $okf = $service->renderFront($e, $tplFront);
                    $this->line("  front: " . ($okf ? 'ok' : 'fail'));
                }
                if ($doBack) {
                    $tplBack = config('nametag.templates.back.background');
                    $okb = $service->renderBack($e, $tplBack);
                    $this->line("  back: " . ($okb ? 'ok' : 'fail'));
                }
            } catch (\Throwable $ex) {
                $this->error("  error: " . $ex->getMessage());
            }
        }
        return 0;
    }
}
