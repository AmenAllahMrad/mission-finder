<?php

namespace Tests\Feature;

use App\Mail\MissionDigestMail;
use App\Models\Alerte;
use App\Models\Critere;
use App\Models\Mission;
use App\Models\ProfilRecherche;
use App\Models\RegleFiltrage;
use App\Models\RegleScoring;
use App\Models\Source;
use App\Models\Stack;
use App\Services\MissionDigestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MissionDigestServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_daily_digest_groups_multiple_missions_into_one_email(): void
    {
        [$profil, $alerte] = $this->createProfileAndAlert(
            frequency: 'daily'
        );

        $mission1 = $this->createMission(
            titre: 'Senior Laravel Developer',
            entreprise: 'Acme Corp'
        );

        $mission2 = $this->createMission(
            titre: 'Laravel Backend Engineer',
            entreprise: 'Beta Corp'
        );

        $service = app(MissionDigestService::class);

        $emailsEnvoyes = $service->traiterFrequence(
            'daily'
        );

        /*
         * Deux missions mais UN SEUL email digest.
         */
        $this->assertSame(
            1,
            $emailsEnvoyes
        );

        Mail::assertSent(
            MissionDigestMail::class,
            function (MissionDigestMail $mail) use (
                $alerte,
                $mission1,
                $mission2
            ) {
                return $mail->hasTo($alerte->destination)
                    && $mail->missions->count() === 2
                    && $mail->missions->contains('id', $mission1->id)
                    && $mail->missions->contains('id', $mission2->id);
            }
        );

        /*
         * Les deux missions doivent être marquées
         * comme envoyées après le succès du digest.
         */
        $this->assertDatabaseHas(
            'alertes_missions_envoyees',
            [
                'alerte_id' => $alerte->id,
                'mission_id' => $mission1->id,
            ]
        );

        $this->assertDatabaseHas(
            'alertes_missions_envoyees',
            [
                'alerte_id' => $alerte->id,
                'mission_id' => $mission2->id,
            ]
        );

        $this->assertDatabaseCount(
            'alertes_missions_envoyees',
            2
        );
    }

    public function test_second_daily_digest_does_not_resend_same_missions(): void
    {
        [$profil, $alerte] = $this->createProfileAndAlert(
            frequency: 'daily'
        );

        $this->createMission(
            titre: 'Senior Laravel Developer',
            entreprise: 'Acme Corp'
        );

        $this->createMission(
            titre: 'Laravel API Engineer',
            entreprise: 'Beta Corp'
        );

        $service = app(MissionDigestService::class);

        /*
         * Premier digest.
         */
        $firstRun = $service->traiterFrequence(
            'daily'
        );

        /*
         * Deuxième passage :
         * aucune nouvelle mission.
         */
        $secondRun = $service->traiterFrequence(
            'daily'
        );

        $this->assertSame(
            1,
            $firstRun
        );

        $this->assertSame(
            0,
            $secondRun
        );

        /*
         * Un seul email au total.
         */
        Mail::assertSent(
            MissionDigestMail::class,
            1
        );

        $this->assertDatabaseCount(
            'alertes_missions_envoyees',
            2
        );
    }

    public function test_mission_failing_profile_filters_is_not_included_in_digest(): void
    {
        [$profil, $alerte] = $this->createProfileAndAlert(
            frequency: 'daily'
        );

        /*
         * Cette mission satisfait le profil.
         */
        $this->createMission(
            titre: 'Senior Laravel Developer',
            entreprise: 'Acme Corp',
            tjmMin: 350,
            remoteType: 'full_remote',
            stacks: ['Laravel']
        );

        /*
         * Cette mission échoue sur le TJM.
         */
        $this->createMission(
            titre: 'Laravel Developer Low Rate',
            entreprise: 'Low Rate Corp',
            tjmMin: 150,
            remoteType: 'full_remote',
            stacks: ['Laravel']
        );

        /*
         * Cette mission échoue sur le stack.
         */
        $this->createMission(
            titre: 'Symfony Developer',
            entreprise: 'Symfony Corp',
            tjmMin: 350,
            remoteType: 'full_remote',
            stacks: ['Symfony']
        );

        $service = app(MissionDigestService::class);

        $emailsEnvoyes = $service->traiterFrequence(
            'daily'
        );

        $this->assertSame(
            1,
            $emailsEnvoyes
        );

        Mail::assertSent(
            MissionDigestMail::class,
            function (MissionDigestMail $mail) {
                return $mail->missions->count() === 1
                    && $mail->missions->first()->titre
                        === 'Senior Laravel Developer';
            }
        );

        /*
         * Une seule mission a réellement été envoyée.
         */
        $this->assertDatabaseCount(
            'alertes_missions_envoyees',
            1
        );
    }

    public function test_weekly_digest_works_independently_from_daily_digest(): void
    {
        [$profil, $alerte] = $this->createProfileAndAlert(
            frequency: 'weekly'
        );

        $mission = $this->createMission(
            titre: 'Weekly Laravel Mission',
            entreprise: 'Weekly Corp'
        );

        $service = app(MissionDigestService::class);

        /*
         * La commande daily ne doit pas traiter
         * une alerte weekly.
         */
        $dailyResult = $service->traiterFrequence(
            'daily'
        );

        $this->assertSame(
            0,
            $dailyResult
        );

        Mail::assertNothingSent();

        /*
         * Le weekly doit maintenant envoyer
         * le digest.
         */
        $weeklyResult = $service->traiterFrequence(
            'weekly'
        );

        $this->assertSame(
            1,
            $weeklyResult
        );

        Mail::assertSent(
            MissionDigestMail::class,
            function (MissionDigestMail $mail) use (
                $alerte,
                $mission
            ) {
                return $mail->hasTo($alerte->destination)
                    && $mail->alerte->frequence === 'weekly'
                    && $mail->missions->contains(
                        'id',
                        $mission->id
                    );
            }
        );
    }

    public function test_digest_is_not_sent_when_no_mission_is_eligible(): void
    {
        [$profil, $alerte] = $this->createProfileAndAlert(
            frequency: 'daily'
        );

        /*
         * Mission non éligible :
         * pas Laravel + TJM trop faible + hybrid.
         */
        $this->createMission(
            titre: 'Symfony Hybrid Developer',
            entreprise: 'Example Corp',
            tjmMin: 150,
            remoteType: 'hybrid',
            stacks: ['Symfony']
        );

        $service = app(MissionDigestService::class);

        $emailsEnvoyes = $service->traiterFrequence(
            'daily'
        );

        $this->assertSame(
            0,
            $emailsEnvoyes
        );

        Mail::assertNothingSent();

        $this->assertDatabaseCount(
            'alertes_missions_envoyees',
            0
        );
    }

    private function createProfileAndAlert(
        string $frequency
    ): array {
        $profil = ProfilRecherche::create([
            'nom' => 'Laravel Remote',
            'actif' => true,
        ]);

        $stackCritere = Critere::firstOrCreate(
            [
                'code' => 'stack',
            ],
            [
                'label' => 'Stack technique',
                'type' => 'texte',
            ]
        );

        $tjmCritere = Critere::firstOrCreate(
            [
                'code' => 'tjm_min',
            ],
            [
                'label' => 'TJM minimum',
                'type' => 'nombre',
            ]
        );

        $remoteCritere = Critere::firstOrCreate(
            [
                'code' => 'remote',
            ],
            [
                'label' => 'Type de remote',
                'type' => 'liste',
            ]
        );

        RegleFiltrage::create([
            'profil_recherche_id' => $profil->id,
            'critere_id' => $stackCritere->id,
            'operateur' => 'contient',
            'valeur' => 'Laravel',
        ]);

        RegleFiltrage::create([
            'profil_recherche_id' => $profil->id,
            'critere_id' => $tjmCritere->id,
            'operateur' => 'superieur_egal',
            'valeur' => '250',
        ]);

        RegleFiltrage::create([
            'profil_recherche_id' => $profil->id,
            'critere_id' => $remoteCritere->id,
            'operateur' => 'egal',
            'valeur' => 'full_remote',
        ]);

        RegleScoring::create([
            'profil_recherche_id' => $profil->id,
            'critere_id' => $stackCritere->id,
            'poids' => 3,
        ]);

        RegleScoring::create([
            'profil_recherche_id' => $profil->id,
            'critere_id' => $remoteCritere->id,
            'poids' => 2,
        ]);

        $alerte = Alerte::create([
            'profil_recherche_id' => $profil->id,
            'canal' => 'email',
            'destination' => 'digest@example.com',
            'frequence' => $frequency,
            'seuil_score_min' => 5,
            'actif' => true,
        ]);

        return [
            $profil,
            $alerte,
        ];
    }

    private function createMission(
        string $titre,
        string $entreprise,
        float $tjmMin = 350,
        string $remoteType = 'full_remote',
        array $stacks = ['Laravel']
    ): Mission {
        $source = Source::firstOrCreate(
            [
                'nom' => 'Digest Test Source',
            ],
            [
                'type' => 'api',
                'url_base' => 'https://example.test',
                'parser_class' =>
                    'App\\Scrapers\\RemoteOkParser',
                'frequence_polling_minutes' => 60,
                'actif' => true,
            ]
        );

        $mission = Mission::create([
            'source_id' => $source->id,
            'titre' => $titre,
            'description' =>
                'Test mission for MissionFinder digest.',
            'entreprise' => $entreprise,
            'tjm_min' => $tjmMin,
            'tjm_max' => 500,
            'remote_type' => $remoteType,
            'localisation' => 'Europe',
            'secteur' => 'Software Development',
            'duree_mois' => 6,
            'date_publication' => '2026-08-11',
            'url_origine' =>
                'https://example.test/job/'
                . uniqid(),
            'hash_unique' =>
                md5(uniqid('', true)),
            'raw_data' => [],
        ]);

        foreach ($stacks as $stackName) {
            $stack = Stack::firstOrCreate([
                'nom' => $stackName,
            ]);

            $mission->stacks()->attach(
                $stack->id
            );
        }

        return $mission;
    }
}