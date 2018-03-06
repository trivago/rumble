<?php

namespace Rumble\Commands;

use Rumble\Resolver;
use Aws\DynamoDb\Marshaler;
use Aws\DynamoDb\DynamoDbClient;
use Symfony\Component\Console\Command\Command;

class SeedCommand extends Command
{
    use Resolver;

    /**
     * @var string
     */
    private $directory = 'seeds';

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('seed')
            ->setDescription('Seeds dynamoDb tables with sample data.');
    }

    protected function execute()
    {
        try {
            $classes = $this->getClasses($this->directory);
            $this->runSeeder($classes);
        } catch (\Exception $e) {
            echo "Seed Error: {$e->getMessage()}" . PHP_EOL;
        }
    }

    /**
     * Handle the "seed" command.
     *
     * @param $classes
     * @throws \Exception
     */
    private function runSeeder($classes)
    {
        $dynamoDbClient = new DynamoDbClient($this->getConfig());
        $transformer = new Marshaler();

        foreach ($classes as $class) {
            $migration = new $class($dynamoDbClient, $transformer);
            $migration->seed();
        }
    }
}