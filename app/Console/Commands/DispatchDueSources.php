<?php

namespace App\Console\Commands;

use App\Jobs\PollerSourceJob;
use App\Models\Source;
use Illuminate\Console\Command;

class DispatchDueSources extends Command
{
    protected $signature = 'sources:poll-due';

    protected $description = 'Dispatch polling jobs for active sources that are due';

    public function handle(): int
    {
        $sources = Source::where('actif', true)->get();

        foreach ($sources as $source) {
            $due = $source->derniere_execution === null;

            if (!$due) {
                $prochaineExecution = $source
                    ->derniere_execution
                    ->copy()
                    ->addMinutes($source->frequence_polling_minutes);

                $due = $prochaineExecution->lte(now());
            }

            if (!$due) {
                continue;
            }

            PollerSourceJob::dispatch($source->id);

            $this->info(
                "Polling job dispatched for source: {$source->nom}"
            );
        }

        return self::SUCCESS;
    }
}