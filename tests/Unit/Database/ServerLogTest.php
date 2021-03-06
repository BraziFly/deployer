<?php

namespace REBELinBLUE\Deployer\Tests\Unit\Database;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use REBELinBLUE\Deployer\Events\ServerLogChanged;
use REBELinBLUE\Deployer\Events\ServerOutputChanged;
use REBELinBLUE\Deployer\Server;
use REBELinBLUE\Deployer\ServerLog;
use REBELinBLUE\Deployer\Tests\TestCase;

/**
 * @coversDefaultClass \REBELinBLUE\Deployer\ServerLog
 * @group slow
 */
class ServerLogTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * @covers ::server
     */
    public function testServer()
    {
        /** @var Server $server */
        $server = factory(Server::class)->create();

        /** @var ServerLog $log */
        $log = factory(ServerLog::class)->create([
            'server_id' => $server->id,
        ]);

        $this->assertInstanceOf(Server::class, $log->server);
        $this->assertSame($server->id, $log->server_id);
    }

    /**
     * @covers ::server
     */
    public function testServerIsCorrectRelationship()
    {
        /** @var ServerLog $log */
        $log    = factory(ServerLog::class)->create();
        $actual = $log->server();

        $this->assertInstanceOf(BelongsTo::class, $actual);
        $this->assertSame('server', $actual->getRelation());
    }

    /**
     * @covers ::boot
     */
    public function testBoot()
    {
        $this->doesntExpectEvents(ServerLogChanged::class);
        $this->doesntExpectEvents(ServerOutputChanged::class);

        factory(ServerLog::class)->create();
    }

    /**
     * @covers ::boot
     */
    public function testBootDoesNotFireOutputChangedEventWhenOutputNotChanged()
    {
        $this->expectsEvents(ServerLogChanged::class);
        $this->doesntExpectEvents(ServerOutputChanged::class);

        /** @var ServerLog $log */
        $log = factory(ServerLog::class)->create([
            'status' => ServerLog::RUNNING,
            'output' => 'lorem ipsum',
        ]);

        $log->status = ServerLog::COMPLETED;
        $log->save();
    }

    /**
     * @covers ::boot
     */
    public function testBootFiresOutputChangedEventWhenOutputChanged()
    {
        $this->expectsEvents(ServerLogChanged::class);
        $this->expectsEvents(ServerOutputChanged::class);

        /** @var ServerLog $log */
        $log = factory(ServerLog::class)->create([
            'status' => ServerLog::RUNNING,
        ]);

        $log->status = ServerLog::COMPLETED;
        $log->output = 'lorem ipsum';
        $log->save();
    }
}
