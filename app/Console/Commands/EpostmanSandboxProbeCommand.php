<?php

namespace App\Console\Commands;

use App\Services\Invoicing\Efaktura\SapiSkClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use RuntimeException;
use Throwable;

/** Read-only transport evidence. This command never imports, sends or acknowledges documents. */
class EpostmanSandboxProbeCommand extends Command
{
    private const ORIGIN = 'https://testpostman.slovakodata.com';

    private const MAX_CREDENTIAL_BYTES = 16384;

    protected $signature = 'efaktura:probe-sandbox {--credentials= : Protected JSON credential file}';

    protected $description = 'Read-only ePostman sandbox authentication and receiving probe with anonymous output';

    public function handle(SapiSkClient $client): int
    {
        $result = [
            'status' => 'failed',
            'stage' => 'environment',
            'authenticated' => false,
            'pages' => 0,
            'documents_listed' => 0,
            'details_retrieved' => 0,
            'xml_retrieved' => false,
            'receiving_proven' => false,
        ];
        $exit = self::FAILURE;

        try {
            $this->assertIsolatedEnvironment();
            $result['stage'] = 'credentials';
            $credentials = $this->readProtectedCredentials($this->option('credentials'));
            $result['stage'] = 'authentication';
            $token = $client->accessToken($credentials['client_id'], $credentials['client_secret'], self::ORIGIN);
            $result['authenticated'] = true;
            $cursor = null;
            $seenCursors = [];

            for ($page = 0; $page < 5; $page++) {
                $result['stage'] = 'listing';
                $listing = $client->listReceivedDocuments($token, $credentials['participant_id'], limit: 20, pageToken: $cursor, baseUrl: self::ORIGIN);
                $result['pages']++;
                $documents = $listing['documents'] ?? null;
                if (! is_array($documents) || ! array_is_list($documents) || count($documents) > 100) {
                    throw new RuntimeException('Invalid listing.');
                }
                foreach ($documents as $document) {
                    if (! is_array($document) || ! is_string($document['documentId'] ?? null) || trim($document['documentId']) === '' || strlen($document['documentId']) > 255 || ($document['receiverParticipantId'] ?? null) !== $credentials['participant_id']) {
                        throw new RuntimeException('Invalid document metadata.');
                    }
                    $result['documents_listed']++;
                    if ($result['details_retrieved'] < 3) {
                        $result['stage'] = 'detail';
                        // The actual adapter validates the document and receiver metadata.
                        // XML is held in memory only; no ACK, model or filesystem write follows.
                        $client->receivedDocument($token, $credentials['participant_id'], $document['documentId'], self::ORIGIN);
                        $result['details_retrieved']++;
                        $result['xml_retrieved'] = true;
                    }
                }

                $cursor = $listing['nextPageToken'] ?? null;
                if ($cursor === null || $cursor === '') {
                    $result['stage'] = 'complete';
                    $result['status'] = $result['documents_listed'] === 0 ? 'empty_inbox' : 'documents_available';
                    $exit = self::SUCCESS;

                    break;
                }
                if (! is_string($cursor) || isset($seenCursors[$cursor]) || $page === 4) {
                    throw new RuntimeException('Pagination limit reached.');
                }
                $seenCursors[$cursor] = true;
            }
        } catch (RequestException $exception) {
            // Do not report exceptions: their messages may include credentials or provider bodies.
            $result['http_status'] = $exception->response->status();
        } catch (Throwable) {
            // Only fixed stage/status labels are emitted, including for filesystem and JSON errors.
        }

        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return $exit;
    }

    private function assertIsolatedEnvironment(): void
    {
        if (! $this->laravel->environment('testing') || config('app.env') !== 'testing' || $this->laravel->configurationIsCached()
            || config('app.debug') !== false
            || config('queue.default') !== 'sync'
            || ! in_array(config('database.connections.sqlite.url'), [null, ''], true)
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('cache.default') !== 'array'
            || config('session.driver') !== 'array'
            || config('mail.default') !== 'array'
            || config('logging.default') !== 'null') {
            throw new RuntimeException('Unsafe probe environment.');
        }
    }

    /** @return array{client_id: string, client_secret: string, participant_id: string} */
    private function readProtectedCredentials(mixed $path): array
    {
        if (! is_string($path) || ! str_starts_with($path, '/') || ! function_exists('posix_geteuid') || @realpath($path) !== $path) {
            throw new RuntimeException('Invalid credential file.');
        }
        clearstatcache(true, $path);
        $before = @lstat($path);
        if (! is_array($before) || ($before['mode'] & 0170000) !== 0100000 || ($before['mode'] & 07777) !== 0600 || $before['uid'] !== posix_geteuid() || $before['size'] > self::MAX_CREDENTIAL_BYTES) {
            throw new RuntimeException('Invalid credential file.');
        }
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unreadable credential file.');
        }
        try {
            $opened = fstat($stream);
            if (! is_array($opened) || $opened['ino'] !== $before['ino'] || $opened['dev'] !== $before['dev'] || ($opened['mode'] & 0170000) !== 0100000 || ($opened['mode'] & 07777) !== 0600 || $opened['uid'] !== posix_geteuid() || $opened['size'] > self::MAX_CREDENTIAL_BYTES) {
                throw new RuntimeException('Credential file changed.');
            }
            $contents = stream_get_contents($stream, self::MAX_CREDENTIAL_BYTES + 1);
        } finally {
            fclose($stream);
        }
        if (! is_string($contents) || strlen($contents) > self::MAX_CREDENTIAL_BYTES) {
            throw new RuntimeException('Invalid credential file.');
        }
        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data) || count($data) !== 3 || array_diff(['client_id', 'client_secret', 'participant_id'], array_keys($data)) !== []) {
            throw new RuntimeException('Invalid credential fields.');
        }
        foreach (['client_id', 'client_secret', 'participant_id'] as $field) {
            if (! is_string($data[$field]) || trim($data[$field]) === '' || strlen($data[$field]) > 4096) {
                throw new RuntimeException('Invalid credential value.');
            }
        }
        if (! preg_match('/^0245:[0-9]{10}$/D', $data['participant_id'])) {
            throw new RuntimeException('Invalid participant.');
        }

        return ['client_id' => $data['client_id'], 'client_secret' => $data['client_secret'], 'participant_id' => $data['participant_id']];
    }
}
