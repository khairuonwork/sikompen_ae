<?php

use App\Actions\KompenResponHub\BuildKompenResponHubWarningLetterArtifact;
use App\Models\KompenResponHubPeriodCutoff;
use Symfony\Component\Yaml\Yaml;

test('the app stores finalized SP revisions on a named volume', function (string $composeFile): void {
    $compose = Yaml::parseFile(dirname(__DIR__, 2).'/'.$composeFile);
    $cutoff = new KompenResponHubPeriodCutoff;
    $cutoff->id = 1;
    $path = (new BuildKompenResponHubWarningLetterArtifact)->destinationPath($cutoff, 2, 'sp.xlsx');
    $mount = '/var/www/html/storage/app/private/'.dirname($path, 3);

    expect($compose['volumes'])->toHaveKey('sikompen_sp_artifacts')
        ->and($compose['volumes']['sikompen_sp_artifacts']['name'])->toBe('sikompen_sp_artifacts')
        ->and($compose['services']['app']['volumes'])->toContain('sikompen_sp_artifacts:'.$mount);
})->with(['docker-compose.yml', 'sikompen-compose.yml']);

test('the app entrypoint grants the PHP user access to the SP artifact volume', function (): void {
    $entrypoint = file_get_contents(dirname(__DIR__, 2).'/docker-entrypoint.sh');

    expect($entrypoint)
        ->toContain('storage/app/private/kompen-respon-hub/warning-artifacts')
        ->toContain('chown -R www-data:www-data');
});
