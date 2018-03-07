<?php

namespace Rumble\Commands;

use Rumble\Resolver;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommand extends Command
{
    use Resolver;

    /**
     * @var DynamoDbClient
     */
    protected $dynamoDBClient;

    /**
     * @var string
     */
    private $directory = 'migrations';

    /**
     *
     */
    protected function configure()
    {
        $this->setName('migrate')
            ->setDescription('Creates and versions dynamoDB tables.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $classes = $this->getClasses($this->directory);
            $this->runMigration($classes);
        } catch (\Exception $e) {
            echo "Migration Error: {$e->getMessage()}" . PHP_EOL;
        }
    }

    /**
     * Handle the "migrate" command.
     *
     * @param $classes
     * @throws \Exception
     */
    private function runMigration($classes)
    {
        $this->dynamoDBClient = new DynamoDbClient($this->getConfig());

        if (!$this->isMigrationsTableExist()) {
            $this->createMigrationTable();
        }

        $ranMigrations = $this->getRanMigrations();
        $pendingMigrations = $this->getPendingMigrations($classes, $ranMigrations);

        if (count($pendingMigrations) == 0) {
            echo "Nothing new to migrate \n";
            return;
        }

        foreach ($pendingMigrations as $pendingMigration) {
            $migration = new $pendingMigration($this->dynamoDBClient);
            $migration->up();
            $this->addToRanMigrations($pendingMigration);
        }
    }

    /**
     * @param $classes
     * @param $ranMigrations
     * @return mixed
     */
    private function getPendingMigrations($classes, $ranMigrations)
    {
        foreach ($ranMigrations as $ranMigration) {
            $key = array_search($ranMigration, $classes);
            if ($key !== false) {
                unset($classes[$key]);
            }
        }
        return $classes;
    }

    /**
     * @return array
     */
    private function getRanMigrations()
    {
        $result = $this->dynamoDBClient->scan([
            'TableName' => 'migrations'
        ]);

        $marsh = new Marshaler();
        $ranMigrations = [];

        foreach ($result->get('Items') as $item) {
            $ranMigrations[] = $marsh->unmarshalItem($item)['migration'];
        }
        return $ranMigrations;
    }

    /**
     * @return bool
     */
    private function isMigrationsTableExist()
    {
        $tables = $this->dynamoDBClient->listTables();
        return in_array('migrations', $tables['TableNames']);
    }

    /**
     *
     */
    private function createMigrationTable()
    {
        $this->dynamoDBClient->createTable([
            'TableName' => 'migrations',
            'AttributeDefinitions' => [
                [
                    'AttributeName' => 'migration',
                    'AttributeType' => 'S'
                ]
            ],
            'KeySchema' => [
                [
                    'AttributeName' => 'migration',
                    'KeyType' => 'HASH'
                ]
            ],
            'ProvisionedThroughput' => [
                'ReadCapacityUnits' => 100,
                'WriteCapacityUnits' => 100
            ]
        ]);
    }

    /**
     * @param $migration
     */
    private function addToRanMigrations($migration)
    {
        $this->dynamoDBClient->putItem([
            'TableName' => 'migrations',
            'Item' => [
                'migration' => ['S' => $migration]
            ]
        ]);
    }
}