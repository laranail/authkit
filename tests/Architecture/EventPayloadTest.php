<?php

declare(strict_types=1);

it('keeps every public lifecycle event immutable and free of credential-bearing objects', function (): void {
    $files = glob(dirname(__DIR__, 2) . '/src/Events/*.php');
    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $class = new ReflectionClass('Simtabi\\Laranail\\AuthKit\\Events\\' . basename($file, '.php'));
        expect($class->isReadOnly())->toBeTrue();

        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            expect($property->getType())->toBeInstanceOf(ReflectionNamedType::class);
            expect($property->getType()->getName())->toBeIn(['string', 'bool', 'array']);
            expect($property->getName())->not->toBeIn(['password', 'token', 'secret', 'recoveryCodes', 'request', 'user', 'client']);
        }
    }
});
