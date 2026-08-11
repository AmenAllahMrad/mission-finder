<?php

namespace App\Console\Commands;

use App\Services\MissionDigestService;
use Illuminate\Console\Command;
use Throwable;

class SendMissionDigests extends Command
{
    protected $signature =
        'alerts:send-digests {frequency}';

    protected $description =
        'Send MissionFinder daily or weekly email digests';

    public function handle(
        MissionDigestService $digestService
    ): int {
        $frequence = strtolower(
            (string) $this->argument('frequency')
        );

        if (! in_array(
            $frequence,
            ['daily', 'weekly'],
            true
        )) {
            $this->error(
                'Frequency must be daily or weekly.'
            );

            return self::FAILURE;
        }

        try {
            $nombre = $digestService
                ->traiterFrequence($frequence);

            $this->info(
                "{$nombre} {$frequence} digest email(s) sent."
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {

            report($exception);

            $this->error(
                'Digest sending failed: '
                . $exception->getMessage()
            );

            return self::FAILURE;
        }
    }
}