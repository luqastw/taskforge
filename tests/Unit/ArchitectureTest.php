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
        'App\Models',
        'App\Policies',
        'App\Repositories',
        'App\Services',
        'Database\Factories',
        'Database\Seeders',
    ]);

arch('repositories')
    ->expect('App\Repositories')
    ->toImplement('App\Repositories\Contracts\RepositoryInterface')
    ->toOnlyBeUsedIn(['App\Services', 'App\Repositories']);

arch('services')
    ->expect('App\Services')
    ->toOnlyBeUsedIn(['App\Http\Controllers', 'App\Services', 'App\Jobs']);

arch('policies')
    ->expect('App\Policies')
    ->toExtend('Illuminate\Auth\Access\HandlesAuthorization')
    ->toOnlyBeUsedIn(['App\Policies', 'App\Providers']);

arch('globals')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

arch('strict types')
    ->expect('App')
    ->toUseStrictTypes();
