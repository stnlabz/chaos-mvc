<?php
declare(strict_types=1);

/**
 * Digit Chat local Unix stream transport.
 * Framing: uint32 BE + UTF-8 request; uint8 status + uint32 BE + UTF-8 reply.
 */
final class chat_model extends model
{
    private const SOCKET = '/run/digit-web/digit.sock';
    private const MAX_REQUEST = 2047;
    private const MAX_RESPONSE = 511;
    private const TIMEOUT_SECONDS = 3;

    // Fail closed. Enable only after native transport qualification, access
    // verification, and explicit operational authorization.
    private const ENABLED = false;

    public function isEnabled(): bool
    {
        return self::ENABLED;
    }

    public function ask(string $message): string
    {
        if (!self::ENABLED) {
            throw new RuntimeException('Chat is not activated.');
        }

        $size = strlen($message);
        if (
            $size < 1
            || $size > self::MAX_REQUEST
            || preg_match('//u', $message) !== 1
        ) {
            throw new InvalidArgumentException('Enter a UTF-8 message between 1 and 2047 bytes.');
        }

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            'unix://' . self::SOCKET,
            $errno,
            $errstr,
            self::TIMEOUT_SECONDS,
            STREAM_CLIENT_CONNECT
        );

        if (!is_resource($socket)) {
            throw new RuntimeException('Local transport connection failed.');
        }

        try {
            stream_set_timeout($socket, self::TIMEOUT_SECONDS);
            stream_set_blocking($socket, true);
            $this->writeAll($socket, pack('N', $size) . $message);

            $header = $this->readExact($socket, 5);
            $status = ord($header[0]);
            $responseSize = unpack('Nlength', substr($header, 1, 4))['length'];

            if (
                !in_array($status, [0, 1], true)
                || $responseSize > self::MAX_RESPONSE
            ) {
                throw new RuntimeException('Invalid transport response.');
            }

            $answer = $this->readExact($socket, $responseSize);

            if (preg_match('//u', $answer) !== 1) {
                throw new RuntimeException('Invalid response encoding.');
            }

            if ($status === 1) {
                throw new RuntimeException('Digit reported an operation failure.');
            }

            return $answer;
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private function writeAll($socket, string $data): void
    {
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $written = @fwrite($socket, substr($data, $offset));

            if ($written === false || $written === 0 || $this->timedOut($socket)) {
                throw new RuntimeException('Transport write failed.');
            }
            $offset += $written;
        }
    }

    /** @param resource $socket */
    private function readExact($socket, int $length): string
    {
        $result = '';
        while (strlen($result) < $length) {
            $part = @fread($socket, $length - strlen($result));

            if ($part === false || $part === '' || $this->timedOut($socket)) {
                throw new RuntimeException('Transport read failed.');
            }
            $result .= $part;
        }
        return $result;
    }

    /** @param resource $socket */
    private function timedOut($socket): bool
    {
        $metadata = stream_get_meta_data($socket);
        return ($metadata['timed_out'] ?? false) === true;
    }
}
