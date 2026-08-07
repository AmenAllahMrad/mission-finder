<?php

namespace App\Jobs;

use App\Models\Source;
use App\Scrapers\Contracts\SourceParserInterface;
use App\Services\MissionImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class PollerSourceJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $uniqueFor = 600;

public function uniqueId(): string
{
    return (string) $this->sourceId;
}

    public function __construct(
        public int $sourceId
    ) {
    }

    public function handle(MissionImportService $importService): void
    {
        $source = Source::find($this->sourceId);

        if (!$source) {
            Log::warning(
                "PollerSourceJob: source {$this->sourceId} introuvable."
            );

            return;
        }

        if (!$source->actif) {
            Log::info(
                "PollerSourceJob: source {$source->nom} inactive."
            );

            return;
        }

        try {
            $parser = app($source->parser_class);

            if (!$parser instanceof SourceParserInterface) {
                throw new RuntimeException(
                    "{$source->parser_class} doit implémenter SourceParserInterface."
                );
            }

            $items = $parser->fetch();

            $imported = 0;

            foreach ($items as $rawItem) {
                $data = $parser->normaliser($rawItem);

                if (
                    empty($data['titre']) ||
                    empty($data['url_origine'])
                ) {
                    continue;
                }

                $importService->importer($source, $data);

                $imported++;
            }

            $source->update([
                'derniere_execution' => now(),
                'dernier_statut' => 'ok',
            ]);

            Log::info(
                "Source {$source->nom} collectée avec succès.",
                [
                    'source_id' => $source->id,
                    'missions_importees' => $imported,
                ]
            );
        } catch (Throwable $exception) {
            $source->update([
                'derniere_execution' => now(),
                'dernier_statut' => 'erreur',
            ]);

            Log::error(
                "Erreur pendant la collecte de {$source->nom}.",
                [
                    'source_id' => $source->id,
                    'message' => $exception->getMessage(),
                ]
            );
        }
    }
}