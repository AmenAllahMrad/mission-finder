<?php

namespace App\Jobs;

use App\Models\Mission;
use App\Services\MissionAlertDispatcherService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Throwable;

class ProcessMissionAlertsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $uniqueFor = 300;

    public int $tries = 3;

    public function __construct(
        public int $missionId
    ) {
    }

    public function uniqueId(): string
    {
        return (string) $this->missionId;
    }

    public function handle(
        MissionAlertDispatcherService $dispatcher
    ): void {
        $mission = Mission::find($this->missionId);

        if (! $mission) {
            return;
        }

        $dispatcher->traiter($mission);
    }

    public function failed(Throwable $exception): void
    {
        logger()->error(
            'Mission alert processing failed.',
            [
                'mission_id' => $this->missionId,
                'message' => $exception->getMessage(),
            ]
        );
    }
}