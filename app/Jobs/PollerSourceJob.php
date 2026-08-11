<?php

namespace App\Jobs;

use App\Models\Source;
use App\Scrapers\Contracts\SourceAwareParserInterface;
use App\Scrapers\Contracts\SourceParserInterface;
use App\Services\MissionImportService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PollerSourceJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $uniqueFor = 600;

    public function __construct(
        public int $sourceId
    ) {
    }

    public function uniqueId(): string
    {
        return (string) $this->sourceId;
    }

    public function handle(
        MissionImportService $importService
    ): void {
        $source = Source::find(
            $this->sourceId
        );

        if (! $source) {
            Log::warning(
                "PollerSourceJob: source {$this->sourceId} introuvable."
            );

            return;
        }

        if (! $source->actif) {
            Log::info(
                "PollerSourceJob: source {$source->nom} inactive."
            );

            return;
        }

        try {
            /*
            |--------------------------------------------------------------------------
            | Résoudre le parser
            |--------------------------------------------------------------------------
            */

            $parser = app(
                $source->parser_class
            );

            if (
                ! $parser instanceof
                    SourceParserInterface
            ) {
                throw new RuntimeException(
                    "{$source->parser_class} doit implémenter SourceParserInterface."
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Injecter la Source si nécessaire
            |--------------------------------------------------------------------------
            |
            | RemoteOK / WWR :
            |     rien à faire.
            |
            | LinkedIn Email :
            |     le parser recevra ici les credentials
            |     chiffrés de la Source.
            |
            */

            if (
                $parser instanceof
                SourceAwareParserInterface
            ) {
                $parser->setSource(
                    $source
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Collecte
            |--------------------------------------------------------------------------
            */

            $items =
                $parser->fetch();

            $imported = 0;

            foreach (
                $items as $rawItem
            ) {
                $data =
                    $parser->normaliser(
                        $rawItem
                    );

                /*
                 * Une mission doit au minimum
                 * avoir un titre et une URL.
                 */
                if (
                    empty(
                        $data['titre']
                    ) ||
                    empty(
                        $data['url_origine']
                    )
                ) {
                    continue;
                }

                $importService->importer(
                    $source,
                    $data
                );

                $imported++;
            }

            /*
            |--------------------------------------------------------------------------
            | Succès
            |--------------------------------------------------------------------------
            */

            $source->update([
                'derniere_execution' =>
                    now(),

                'dernier_statut' =>
                    'ok',
            ]);

            Log::info(
                "Source {$source->nom} collectée avec succès.",
                [
                    'source_id' =>
                        $source->id,

                    'missions_importees' =>
                        $imported,
                ]
            );
        } catch (
            Throwable $exception
        ) {
            /*
            |--------------------------------------------------------------------------
            | Erreur
            |--------------------------------------------------------------------------
            */

            $source->update([
                'derniere_execution' =>
                    now(),

                'dernier_statut' =>
                    'erreur',
            ]);

            Log::error(
                "Erreur pendant la collecte de {$source->nom}.",
                [
                    'source_id' =>
                        $source->id,

                    'message' =>
                        $exception
                            ->getMessage(),
                ]
            );
        }
    }
}