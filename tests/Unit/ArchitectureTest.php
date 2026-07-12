<?php

arch('models do not depend on matter type definitions')
    ->expect('App\Models')
    ->not->toUse('App\Matters');
