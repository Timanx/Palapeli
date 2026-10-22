<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// sessions of the simulated browsers live in memory (the tests run many "requests" in one process)
session_set_save_handler(new Tests\Support\MemorySessionHandler, true);

Tests\Support\TestDatabase::recreate();
