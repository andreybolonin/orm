<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Cycle\ORM\Tests\Command;

use PHPUnit\Framework\TestCase;
use Cycle\ORM\Command\Branch\Split;
use Cycle\ORM\Command\ContextCarrierInterface;

class SplitCommandTest extends TestCase
{
    public function testNeverExecuted()
    {
        $command = new Split(new TestContextCommand(), new TestContextCommand());
        $this->assertFalse($command->isExecuted());
    }
}

class TestContextCommand implements ContextCarrierInterface
{
    private $executed = false;

    public function isReady(): bool
    {
        return true;
    }

    public function isExecuted(): bool
    {
        return $this->executed;
    }

    public function execute()
    {
        $this->executed = true;
    }

    public function complete()
    {
    }

    public function rollBack()
    {
    }

    public function waitContext(string $key, bool $required = true)
    {
    }

    public function getContext(): array
    {
    }

    public function register(string $key, $value, bool $fresh = false, int $stream = self::DATA)
    {
    }
}