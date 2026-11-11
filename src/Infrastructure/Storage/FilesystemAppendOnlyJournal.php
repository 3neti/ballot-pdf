<?php

namespace LBHurtado\BallotPdf\Infrastructure\Storage;

use LBHurtado\BallotPdf\Contracts\AppendOnlyJournal;
use LBHurtado\BallotPdf\Domain\Lifecycle\LifecycleEvent;

final readonly class FilesystemAppendOnlyJournal implements AppendOnlyJournal
{
    public function __construct(private string $root)
    {
        if (! is_dir($root) && ! mkdir($root, 0770, true) && ! is_dir($root)) {
            throw new \RuntimeException("Unable to create journal root {$root}.");
        }
    }

    public function append(LifecycleEvent $event): void
    {
        $path = $this->root.'/'.$event->serial.'/'.$event->sequence.'-'.$event->eventId.'.json';
        if (file_exists($path)) {
            throw new \RuntimeException("Journal refuses overwrite: {$path}");
        }
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0770, true) && ! is_dir(dirname($path))) {
            throw new \RuntimeException('Unable to create journal serial directory.');
        }
        file_put_contents($path, json_encode($event->toArray() + ['event_hash' => $event->hash()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
    }

    public function events(string $serial): array
    {
        $directory = $this->root.'/'.$serial;
        if (! is_dir($directory)) {
            return [];
        }
        $files = glob($directory.'/*.json') ?: [];
        sort($files, SORT_STRING);
        return array_map(static function (string $file): LifecycleEvent {
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            return new LifecycleEvent(
                $data['eventId'], $data['eventType'], $data['serial'], $data['contextHash'],
                $data['sequence'], $data['actorRole'], $data['artifactHash'] ?? null, $data['previousEventHash'] ?? null,
            );
        }, $files);
    }
}
