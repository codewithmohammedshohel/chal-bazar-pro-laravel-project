<?php

namespace Tests\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Starts genuinely separate PHP processes, waits until each has booted, and
 * releases them through a shared barrier file so their database transactions
 * overlap on the real MySQL server.
 */
trait RunsConcurrentWorkers
{
    /** @var list<string> */
    private array $concurrencyDirectories = [];

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     * @return array<int, array<string, mixed>>
     */
    protected function runWorkers(array $payloads): array
    {
        $directory = storage_path('framework/testing/concurrency-'.bin2hex(random_bytes(6)));

        File::ensureDirectoryExists($directory);
        $this->concurrencyDirectories[] = $directory;

        $environment = $this->workerEnvironment();
        $processes = [];
        $paths = [];

        foreach ($payloads as $index => $payload) {
            $paths[$index] = [
                'payload' => $directory.DIRECTORY_SEPARATOR.'payload-'.$index.'.json',
                'ready' => $directory.DIRECTORY_SEPARATOR.'ready-'.$index,
                'output' => $directory.DIRECTORY_SEPARATOR.'output-'.$index.'.json',
                'go' => $directory.DIRECTORY_SEPARATOR.'go',
            ];

            $payload['ready'] = $paths[$index]['ready'];
            $payload['go'] = $paths[$index]['go'];
            $payload['output'] = $paths[$index]['output'];

            file_put_contents(
                $paths[$index]['payload'],
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            );

            $process = new Process(
                [PHP_BINARY, base_path('tests/Concurrency/worker.php'), $paths[$index]['payload']],
                base_path(),
                $environment,
            );

            $process->setTimeout(60);
            $process->start();

            $processes[$index] = $process;
        }

        $deadline = microtime(true) + 30;

        foreach ($processes as $index => $process) {
            while (! is_file($paths[$index]['ready'])) {
                if (! $process->isRunning()) {
                    throw new RuntimeException(sprintf(
                        "Concurrency worker %d exited before reaching the barrier.\nSTDOUT:\n%s\nSTDERR:\n%s",
                        $index,
                        $process->getOutput(),
                        $process->getErrorOutput(),
                    ));
                }

                if (microtime(true) > $deadline) {
                    $process->stop();

                    throw new RuntimeException("Concurrency worker {$index} did not reach the barrier in time.");
                }

                usleep(10000);
            }
        }

        touch($directory.DIRECTORY_SEPARATOR.'go');

        foreach ($processes as $index => $process) {
            $process->wait();

            if (! is_file($paths[$index]['output'])) {
                throw new RuntimeException(sprintf(
                    "Concurrency worker %d produced no output (exit %s).\nSTDOUT:\n%s\nSTDERR:\n%s",
                    $index,
                    (string) $process->getExitCode(),
                    $process->getOutput(),
                    $process->getErrorOutput(),
                ));
            }
        }

        $results = [];

        foreach ($paths as $index => $path) {
            $decoded = json_decode(
                (string) file_get_contents($path['output']),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            if (! is_array($decoded)) {
                throw new RuntimeException("Concurrency worker {$index} returned invalid JSON.");
            }

            $results[$index] = $decoded;
        }

        return $results;
    }

    /**
     * Build an explicit environment for child processes. Laravel's Dotenv
     * loader is immutable, so these values win over the project's .env file.
     *
     * @return array<string, string>
     */
    private function workerEnvironment(): array
    {
        $inherited = getenv();

        $environment = is_array($inherited) ? $inherited : [];

        $connection = (string) config('database.default');
        $database = (array) config("database.connections.{$connection}", []);

        $environment['APP_ENV'] = 'testing';
        $environment['APP_KEY'] = (string) config('app.key');
        $environment['APP_DEBUG'] = 'false';
        $environment['DB_CONNECTION'] = $connection;
        $environment['DB_URL'] = '';
        $environment['DB_HOST'] = (string) ($database['host'] ?? '');
        $environment['DB_PORT'] = (string) ($database['port'] ?? '');
        $environment['DB_DATABASE'] = (string) ($database['database'] ?? '');
        $environment['DB_USERNAME'] = (string) ($database['username'] ?? '');
        $environment['DB_PASSWORD'] = (string) ($database['password'] ?? '');
        $environment['DB_SOCKET'] = (string) ($database['socket'] ?? '');
        $environment['CACHE_STORE'] = 'array';
        $environment['SESSION_DRIVER'] = 'array';
        $environment['QUEUE_CONNECTION'] = 'sync';
        $environment['MAIL_MAILER'] = 'array';
        $environment['BCRYPT_ROUNDS'] = '4';

        return $environment;
    }

    protected function removeConcurrencyDirectories(): void
    {
        foreach ($this->concurrencyDirectories as $directory) {
            File::deleteDirectory($directory);
        }

        $this->concurrencyDirectories = [];
    }
}
