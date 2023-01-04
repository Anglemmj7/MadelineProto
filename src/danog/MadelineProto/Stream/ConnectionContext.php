<?php

declare(strict_types=1);

/**
 * Connection context.
 *
 * This file is part of MadelineProto.
 * MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU General Public License along with MadelineProto.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Daniil Gentili <daniil@daniil.it>
 * @copyright 2016-2023 Daniil Gentili <daniil@daniil.it>
 * @license   https://opensource.org/licenses/AGPL-3.0 AGPLv3
 * @link https://docs.madelineproto.xyz MadelineProto documentation
 */

namespace danog\MadelineProto\Stream;

use Amp\CancellationToken;
use Amp\Socket\ConnectContext;
use danog\MadelineProto\Exception;
use danog\MadelineProto\Stream\MTProtoTransport\ObfuscatedStream;
use danog\MadelineProto\Stream\Transport\DefaultStream;
use League\Uri\Http;
use Psr\Http\Message\UriInterface;

/**
 * Connection context class.
 *
 * Is responsible for maintaining state about a certain connection to a DC.
 * That includes the Stream chain that is required to use the connection, the connection URI, and other connection-related data.
 *
 * @author Daniil Gentili <daniil@daniil.it>
 */
class ConnectionContext
{
    /**
     * Whether to use a secure socket.
     *
     */
    private bool $secure = false;
    /**
     * Whether to use test servers.
     *
     */
    private bool $test = false;
    /**
     * Whether to use media servers.
     *
     */
    private bool $media = false;
    /**
     * Whether to use CDN servers.
     *
     */
    private bool $cdn = false;
    /**
     * The connection URI.
     *
     */
    private UriInterface $uri;
    /**
     * Whether this connection context will be used by the DNS client.
     *
     */
    private bool $isDns = false;
    /**
     * Socket context.
     *
     */
    private ConnectContext $socketContext;
    /**
     * Cancellation token.
     *
     */
    private CancellationToken $cancellationToken;
    /**
     * The telegram DC ID.
     *
     */
    private int $dc = 0;
    /**
     * Whether to use IPv6.
     *
     */
    private bool $ipv6 = false;
    /**
     * An array of arrays containing an array with the stream name and the extra parameter to pass to it.
     *
     * @var array<0: class-string, 1: mixed>[]
     */
    private $nextStreams = [];
    /**
     * The current stream key.
     *
     */
    private int $key = 0;
    /**
     * Read callback.
     *
     * @var callable
     */
    private $readCallback;
    /**
     * Set the socket context.
     */
    public function setSocketContext(ConnectContext $socketContext): self
    {
        $this->socketContext = $socketContext;
        return $this;
    }
    /**
     * Get the socket context.
     */
    public function getSocketContext(): ConnectContext
    {
        return $this->socketContext;
    }
    /**
     * Set the connection URI.
     *
     */
    public function setUri(string|UriInterface $uri): self
    {
        $this->uri = $uri instanceof UriInterface ? $uri : Http::createFromString($uri);
        return $this;
    }
    /**
     * Get the URI as a string.
     */
    public function getStringUri(): string
    {
        return (string) $this->uri;
    }
    /**
     * Get the URI.
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }
    /**
     * Set the cancellation token.
     */
    public function setCancellationToken(CancellationToken $cancellationToken): self
    {
        $this->cancellationToken = $cancellationToken;
        return $this;
    }
    /**
     * Get the cancellation token.
     */
    public function getCancellationToken(): CancellationToken
    {
        return $this->cancellationToken;
    }
    /**
     * Return a clone of the current connection context.
     */
    public function getCtx(): self
    {
        return clone $this;
    }
    /**
     * Set the test boolean.
     */
    public function setTest(bool $test): self
    {
        $this->test = $test;
        return $this;
    }
    /**
     * Whether this is a test connection.
     */
    public function isTest(): bool
    {
        return $this->test;
    }
    /**
     * Whether this is a media connection.
     */
    public function isMedia(): bool
    {
        return $this->media;
    }
    /**
     * Whether this is a CDN connection.
     */
    public function isCDN(): bool
    {
        return $this->cdn;
    }
    /**
     * Whether this connection context will only be used by the DNS client.
     */
    public function isDns(): bool
    {
        return $this->isDns;
    }
    /**
     * Whether this connection context will only be used by the DNS client.
     */
    public function setIsDns(bool $isDns): self
    {
        $this->isDns = $isDns;
        return $this;
    }
    /**
     * Set the secure boolean.
     */
    public function secure(bool $secure): self
    {
        $this->secure = $secure;
        return $this;
    }
    /**
     * Whether to use TLS with socket connections.
     */
    public function isSecure(): bool
    {
        return $this->secure;
    }
    /**
     * Set the DC ID.
     *
     */
    public function setDc(string|int $dc): self
    {
        $int = \intval($dc);
        if (!(1 <= $int && $int <= 1000)) {
            throw new Exception("Invalid DC id provided: {$dc}");
        }
        $this->dc = $dc;
        $this->media = \strpos($dc, '_media') !== false;
        $this->cdn = \strpos($dc, '_cdn') !== false;
        return $this;
    }
    /**
     * Get the DC ID.
     *
     */
    public function getDc(): string|int
    {
        return $this->dc;
    }
    /**
     * Get the int DC ID.
     *
     */
    public function getIntDc(): string|int
    {
        $dc = \intval($this->dc);
        if ($this->test) {
            $dc += 10000;
        }
        if ($this->media) {
            $dc = -$dc;
        }
        return $dc;
    }
    /**
     * Whether to use ipv6.
     */
    public function setIpv6(bool $ipv6): self
    {
        $this->ipv6 = $ipv6;
        return $this;
    }
    /**
     * Whether to use ipv6.
     */
    public function getIpv6(): bool
    {
        return $this->ipv6;
    }
    /**
     * Add a stream to the stream chain.
     *
     * @psalm-param class-string $streamName
     */
    public function addStream(string $streamName, $extra = null): self
    {
        $this->nextStreams[] = [$streamName, $extra];
        $this->key = \count($this->nextStreams) - 1;
        return $this;
    }
    /**
     * Set read callback, called every time the socket reads at least a byte.
     *
     * @param callable $callable Read callback
     */
    public function setReadCallback(callable $callable): void
    {
        $this->readCallback = $callable;
    }
    /**
     * Check if a read callback is present.
     */
    public function hasReadCallback(): bool
    {
        return $this->readCallback !== null;
    }
    /**
     * Get read callback.
     */
    public function getReadCallback(): callable
    {
        return $this->readCallback;
    }
    /**
     * Get the current stream name from the stream chain.
     */
    public function getStreamName(): string
    {
        return $this->nextStreams[$this->key][0];
    }
    /**
     * Check if has stream within stream chain.
     *
     * @param string $stream Stream name
     */
    public function hasStreamName(string $stream): bool
    {
        foreach ($this->nextStreams as [$name]) {
            if ($name === $stream) {
                return true;
            }
        }
        return false;
    }
    /**
     * Get a stream from the stream chain.
     */
    public function getStream(string $buffer = ''): StreamInterface
    {
        [$clazz, $extra] = $this->nextStreams[$this->key--];
        $obj = new $clazz();
        if ($obj instanceof ProxyStreamInterface) {
            $obj->setExtra($extra);
        }
        $obj->connect($this, $buffer);
        return $obj;
    }
    /**
     * Get the inputClientProxy proxy MTProto object.
     *
     * @return array
     */
    public function getInputClientProxy(): ?array
    {
        foreach ($this->nextStreams as $couple) {
            [$streamName, $extra] = $couple;
            if ($streamName === ObfuscatedStream::class && isset($extra['address'])) {
                $extra['_'] = 'inputClientProxy';
                return $extra;
            }
        }
        return null;
    }
    /**
     * Get a description "name" of the context.
     */
    public function getName(): string
    {
        $string = $this->getStringUri();
        if ($this->isSecure()) {
            $string .= ' (TLS)';
        }
        $string .= $this->isTest() ? ' test' : ' main';
        $string .= ' DC ';
        $string .= $this->getDc();
        $string .= ', via ';
        $string .= $this->getIpv6() ? 'ipv6' : 'ipv4';
        $string .= ' using ';
        foreach (\array_reverse($this->nextStreams) as $k => $stream) {
            if ($k) {
                $string .= ' => ';
            }
            $string .= \preg_replace('/.*\\\\/', '', $stream[0]);
            if ($stream[1] && $stream[0] !== DefaultStream::class) {
                $string .= ' ('.\json_encode($stream[1]).')';
            }
        }
        return $string;
    }
    /**
     * Returns a representation of the context.
     */
    public function __toString(): string
    {
        return $this->getName();
    }
}
