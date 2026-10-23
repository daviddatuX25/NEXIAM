#!/usr/bin/env php
<?php

declare(strict_types=1);

const RELAY_BUFFER_SIZE = 65536;
const RELAY_MAX_BYTES = 134217728;
const RELAY_TOTAL_SECONDS = 120;
const RELAY_IDLE_SECONDS = 15;

$socket = @stream_socket_client('tcp://127.0.0.1:8000', $errorCode, $errorMessage, 10, STREAM_CLIENT_CONNECT);
if ($socket === false) {
    fwrite(STDERR, "document-recovery-relay: connection failed ({$errorCode}): {$errorMessage}\n");
    exit(1);
}
stream_set_blocking(STDIN, false);
stream_set_blocking($socket, false);
$stdinOpen = true;
$socketReadOpen = true;
$pending = '';
$startedAt = microtime(true);
$lastActivityAt = $startedAt;
$receivedBytes = 0;
$sentBytes = 0;

try {
    while ($stdinOpen || $socketReadOpen || $pending !== '') {
        $read = [];
        $write = [];
        if ($stdinOpen && strlen($pending) < RELAY_BUFFER_SIZE * 4) {
            $read[] = STDIN;
        }
        if ($socketReadOpen) {
            $read[] = $socket;
        }
        if ($pending !== '') {
            $write[] = $socket;
        }
        $except = null;
        $remaining = min(
            RELAY_TOTAL_SECONDS - (microtime(true) - $startedAt),
            RELAY_IDLE_SECONDS - (microtime(true) - $lastActivityAt),
        );
        if ($remaining <= 0) {
            throw new RuntimeException('Relay deadline exceeded.');
        }
        $seconds = (int) $remaining;
        $microseconds = (int) (($remaining - $seconds) * 1_000_000);
        $selected = @stream_select($read, $write, $except, $seconds, $microseconds);
        if ($selected === false) {
            throw new RuntimeException('stream_select failed.');
        }
        foreach ($read as $stream) {
            $chunk = @fread($stream, RELAY_BUFFER_SIZE);
            if ($chunk === false) {
                throw new RuntimeException('A stream read failed.');
            }
            if ($chunk !== '') {
                $lastActivityAt = microtime(true);
            }
            if ($stream === STDIN) {
                if ($chunk !== '') {
                    $receivedBytes += strlen($chunk);
                    if ($receivedBytes > RELAY_MAX_BYTES) {
                        throw new RuntimeException('Request byte limit exceeded.');
                    }
                    $pending .= $chunk;
                }
                if (feof(STDIN)) {
                    $stdinOpen = false;
                }
            } elseif ($chunk !== '') {
                $sentBytes += strlen($chunk);
                if ($sentBytes > RELAY_MAX_BYTES) {
                    throw new RuntimeException('Response byte limit exceeded.');
                }
                $offset = 0;
                $length = strlen($chunk);
                while ($offset < $length) {
                    $written = @fwrite(STDOUT, substr($chunk, $offset));
                    if ($written === false || $written === 0) {
                        throw new RuntimeException('Could not forward socket data to stdout.');
                    }
                    $offset += $written;
                }
                fflush(STDOUT);
            } elseif (feof($socket)) {
                $socketReadOpen = false;
                break 2;
            }
        }
        if ($write !== []) {
            $written = @fwrite($socket, $pending);
            if ($written === false) {
                throw new RuntimeException('Could not forward stdin data to the socket.');
            }
            if ($written > 0) {
                $lastActivityAt = microtime(true);
                $pending = (string) substr($pending, $written);
            }
        }
        if (! $stdinOpen && $pending === '') {
            @stream_socket_shutdown($socket, STREAM_SHUT_WR);
        }
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'document-recovery-relay: '.$exception->getMessage()."\n");
    fclose($socket);
    exit(1);
}
fclose($socket);
exit(0);
