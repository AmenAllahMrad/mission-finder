<?php

namespace App\Console\Commands;

use App\Models\Mission;
use App\Services\MissionScoringService;
use Illuminate\Console\Command;

class RecalculateMissionScores extends Command
{
    protected $signature = 'missions:recalculate-scores';

    protected $description = 'Recalculate mission scores for all active search profiles';

    public function handle(
        MissionScoringService $scoringService
    ): int {
        $total = Mission::count();

        if ($total === 0) {
            $this->info('No missions to score.');

            return self::SUCCESS;
        }

        $this->info(
            "Recalculating scores for {$total} missions..."
        );

        $bar = $this->output->createProgressBar($total);

        $bar->start();

        Mission::query()
            ->with('stacks')
            ->chunkById(
                100,
                function ($missions) use (
                    $scoringService,
                    $bar
                ) {
                    foreach ($missions as $mission) {
                        $scoringService
                            ->calculerPourProfilsActifs(
                                $mission
                            );

                        $bar->advance();
                    }
                }
            );

        $bar->finish();

        $this->newLine(2);

        $this->info(
            'Mission scores recalculated successfully.'
        );

        return self::SUCCESS;
    }
}