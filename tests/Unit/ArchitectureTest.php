<?php

declare(strict_types=1);

arch('controllers')
    ->expect('App\Http\Controllers')
    ->toExtend('App\Http\Controllers\Controller')
    ->toOnlyBeUsedIn('App\Http\Controllers');

arch('models')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model')
    ->toOnlyBeUsedIn([
        'App\Http\Controllers',
        'App\Http\Requests',
        'App\Http\Resources',
        'App\Models',
        'App\Notifications',
        'App\Policies',
        'App\Repositories',
        'App\Services',
        'App\Traits',
        'Database\Factories',
        'Database\Seeders',
    ]);

arch('repositories')
    ->expect('App\Repositories')
    ->toImplement('App\Repositories\Contracts\RepositoryInterface')
    ->toOnlyBeUsedIn(['App\Services', 'App\Repositories', 'App\Providers', 'App\Http\Controllers']);

arch('services')
    ->expect('App\Services')
    ->toOnlyBeUsedIn(['App\Http\Controllers', 'App\Services', 'App\Jobs']);

arch('policies')
    ->expect('App\Policies')
    ->toOnlyBeUsedIn(['App\Policies', 'App\Providers']);

arch('globals')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

arch('strict types')
    ->expect('App')
    ->toUseStrictTypes();
