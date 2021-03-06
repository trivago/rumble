<?php

namespace Rumble\Commands;

use Rumble\Resolver;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

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
     * @var string
     */
    private $tableName = 'migrations';

    /**
     * @var integer
     */
    private $tableReadCapacity = 10;

    /**
     * @var integer
     */
    private $tableWriteCapacity = 10;

    /**
     *
     */
    protected function configure()
    {
        $this->setName('migrate')
            ->setDescription('Creates and versions dynamoDB tables.')
            ->addArgument('table_name', InputArgument::OPTIONAL, 'Migrations table name (default: "migrations")?')
            ->addArgument('table_read_capacity', InputArgument::OPTIONAL, 'Migrations table read capacity (default: 10)?')
            ->addArgument('table_write_capacity', InputArgument::OPTIONAL, 'Migrations table write capacity (default: 10)?');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $tableName = $input->getArgument('table_name');
            if ($tableName) {
                $this->tableName = $tableName;
            }

            $tableReadCapacity = $input->getArgument('table_read_capacity');
            if ($tableReadCapacity) {
                $this->tableReadCapacity = $tableReadCapacity;
            }

            $tableWriteCapacity = $input->getArgument('table_write_capacity');
            if ($tableWriteCapacity) {
                $this->tableWriteCapacity = $tableWriteCapacity;
            }

            $classes = $this->getClasses($this->directory);

            foreach ($classes as $clazz) {
                $output->writeln("Migration resource found: {$clazz}");
            }

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
            'TableName' => $this->tableName
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
        return in_array($this->tableName, $tables['TableNames']);
    }

    /**
     *
     */
    private function createMigrationTable()
    {
        $this->dynamoDBClient->createTable([
            'TableName' => $this->tableName,
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
                'ReadCapacityUnits' => $this->tableReadCapacity,
                'WriteCapacityUnits' => $this->tableWriteCapacity
            ]
        ]);
    }

    /**
     * @param $migration
     */
    private function addToRanMigrations($migration)
    {
        $this->dynamoDBClient->putItem([
            'TableName' => $this->tableName,
            'Item' => [
                'migration' => ['S' => $migration]
            ]
        ]);
    }
}
