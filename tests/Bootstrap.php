<?php

require(__DIR__ . '/../vendor/autoload.php');

use AshleyHardy\Persistence\Query\QueryBuilder;
use AshleyHardy\Utilities\Utils;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->load(__DIR__ . '/../.env');

/*
    These are default values for the test database that runs locally.
    You might not need these, say if you use AUTO_INCREMENT on your ID column
    and set sensible defaults for your created_at and modified_at col's, if you call them that.

    This is a hangover from the migration from the original monolithic repo these tests existed in.
    It might be fixed soon 👍
*/

QueryBuilder::addFilter('INSERT', function(QueryBuilder $qb) {
    $qb->column('id', Utils::uuid(), true)->column('created_at', Utils::datetime(), true)->column('modified_at', Utils::datetime(), true);
});

QueryBuilder::addFilter('UPDATE', function(QueryBuilder $qb) {
    $qb->column('modified_at', Utils::datetime(), true);
});