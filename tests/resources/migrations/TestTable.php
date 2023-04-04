<?php declare(strict_types=1);

use AshleyHardy\Persistence\MigrationAbstract;

return new class extends MigrationAbstract
{
    public function name(): string
    {
        return 'Test Migration';
    }  

    public function up(): string
    {
        return "
            CREATE TABLE `migration_test` (
                `id` INT NOT NULL,
                `value` INT NULL,
                PRIMARY KEY (`id`)
            )
            COLLATE='latin1_swedish_ci'
            ;
        ";
    }

    public function down(): string
    {  
        return "
            DROP TABLE `migration_test`;
        ";
    }
};