<?php

namespace Tests\Support;

/** Marks a string as CBOR bytes rather than text (FakePasskeyAuthenticator). */
final class CborBytes
{
    public function __construct(public readonly string $bytes) {}
}
