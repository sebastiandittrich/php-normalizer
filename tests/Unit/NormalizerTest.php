<?php

use SebastianDittrich\Normalizer\Discriminator;
use SebastianDittrich\Normalizer\InterfaceMap;
use SebastianDittrich\Normalizer\Normalizer;
use SebastianDittrich\Normalizer\Type\ArrayType;
use SebastianDittrich\Normalizer\Type\FloatType;
use SebastianDittrich\Normalizer\Type\InterfaceType;
use SebastianDittrich\Normalizer\Type\NullType;
use SebastianDittrich\Normalizer\Type\StringType;
use SebastianDittrich\Normalizer\Type\TypeResolver;
use SebastianDittrich\Normalizer\Type\UnionType;

class Test implements TestInterface
{
    public function __construct(public string $message) {}
}

#[Discriminator('test2')]
class Test2 implements TestInterface
{
    public function __construct(public string $message) {}
}
#[Discriminator('test3')]
class Test3 implements TestInterface
{
    public function __construct(public Test2|Test3 $test) {}
}

#[InterfaceMap([
    Test2::class,
    Test3::class,
])]
interface TestInterface {}

#[InterfaceMap([
    Test5::class,
    Test6::class,
])]
interface TestInterface2 {}

#[Discriminator('test5')]
class Test5
{
    public function __construct(public string $message) {}
}

#[Discriminator('test6')]
class Test6
{
    public function __construct(public string $message) {}
}

class Test4
{
    public function __construct(public TestInterface $test) {}
}

enum TestEnum: string
{
    case Test = 'test';
}

test('test', function (object $orig, array $expectedNormalized) {
    $actualNormalized = (new Normalizer)->normalize($orig);
    expect($actualNormalized)->toEqual($expectedNormalized);
    $denorm = (new Normalizer)->denormalize($actualNormalized, $orig::class);
    expect($denorm)->toEqual($orig);
})->with([
    'simple' => function () {

        return [
            new Test('lala'),
            [
                'message' => 'lala',
            ],
        ];
    },
    'union' => function () {
        return [
            new Test3(new Test2('message')),
            [
                'test' => [
                    'message' => 'message',
                    'type' => 'test2',
                ],
                'type' => 'test3',
            ],
        ];
    },
    'union2' => function () {
        return [
            new Test3(new Test3(new Test2('test'))),
            [
                'test' => [
                    'test' => [
                        'message' => 'test',
                        'type' => 'test2',
                    ],
                    'type' => 'test3',
                ],
                'type' => 'test3',
            ],
        ];
    },
    'interface' => function () {
        return [
            new Test4(new Test2('lala')),
            [
                'test' => [
                    'message' => 'lala',
                    'type' => 'test2',
                ],
            ],
        ];
    },
    'nullable property' => function () {
        return [
            new class
            {
                public function __construct(public ?string $message = null) {}
            },
            [],
        ];
    },
    'nullable class' => function () {
        return [
            new class(new Test('this is a test'))
            {
                public function __construct(public ?Test $test) {}
            },
            [
                'test' => [
                    'message' => 'this is a test',
                ],
            ],
        ];
    },
]);

test('denormalizing arrays', function () {
    expect((new Normalizer)->denormalize(['test', 'test'], 'string[]'))->toEqual([
        'test',
        'test',
    ]);
    expect((new Normalizer)->denormalize([['message' => 'hallo'], ['message' => 'hallo2']], 'Test[]'))->toEqual([
        new Test('hallo'),
        new Test('hallo2'),
    ]);
});
test('denormalizing float in union that is stored as int', function () {
    $class = new class(10000)
    {
        public function __construct(public ?float $number) {}
    };
    expect((new Normalizer)->denormalize(['number' => 10000], $class::class))->toEqual($class);
});
test('float goes before int', function () {
    $class = new class(10000)
    {
        public function __construct(public int|float $number) {}
    };
    expect((new Normalizer)->denormalize(['number' => 10000], $class::class)->number)
        ->toBeFloat();
    expect((new Normalizer)->denormalize(['number' => 10000], $class::class))
        ->toEqual($class);
});
test('nullable enum', function () {

    $class = new class(TestEnum::Test)
    {
        public function __construct(public ?TestEnum $enum) {}
    };
    expect((new Normalizer)->denormalize(['enum' => 'test'], $class::class))
        ->toEqual($class);
});
test('null properties are not serialized', function () {
    $class = new class(null)
    {
        public function __construct(public ?string $message) {}
    };

    expect((new Normalizer)->normalize($class))
        ->toEqual([]);

    expect((new Normalizer)->denormalize([], $class::class))
        ->toEqual($class);
});

test('typeresolver', function () {
    $resolver = new TypeResolver;

    $test = new class([])
    {
        /**
         * @param  string[]  $array
         */
        public function __construct(public array $array) {}
    };

    expect($resolver->typeFromParameter(
        (new ReflectionClass($test))
            ->getConstructor()
            ->getParameters()[0]
    ))->toEqual(new ArrayType(new StringType));
});

test('typeresolver 2', function () {
    $resolver = new TypeResolver;

    expect($resolver->typeFromParameter(
        new ReflectionParameter([
            new class(null)
            {
                public function __construct(public ?string $message) {}
            },
            '__construct',
        ], 'message')
    ))->toEqual(new UnionType([new StringType, new NullType]));
    expect($resolver->typeFromParameter(
        new ReflectionParameter([
            new class(null)
            {
                public function __construct(public ?string $message) {}
            },
            '__construct',
        ], 'message')
    ))->toEqual(new UnionType([new StringType, new NullType]));

    expect($resolver->typeFromParameter(
        new ReflectionParameter([
            new class('string')
            {
                public function __construct(public string $message) {}
            },
            '__construct',
        ], 'message')
    ))->toEqual(new StringType);
});

test('typeresolver 3', function () {
    $resolver = new TypeResolver;
    $type = $resolver->typeFromParameter(
        new ReflectionParameter([
            new class('string')
            {
                public function __construct(public string|float|null $message) {}
            },
            '__construct',
        ], 'message')
    );
    /** @var UnionType $type */
    expect($type)->toBeInstanceOf(UnionType::class);
    expect($type->types)->toHaveCount(3);
    expect($type->types[0])->toBeInstanceOf(StringType::class);
    expect($type->types[1])->toBeInstanceOf(FloatType::class);
    expect($type->types[2])->toBeInstanceOf(NullType::class);
});

test('nullable interface', function () {
    $denormalized = new Normalizer()->denormalizeUnion(
        data: ['type' => 'test2', 'message' => 'hello'],
        unionType: new UnionType([
            new InterfaceType(TestInterface::class),
            new NullType(),
        ])
    );

    expect($denormalized)->toBeInstanceOf(Test2::class);
    expect($denormalized->message)->toBe('hello');
});

test('multiple interfaces', function () {
    $denormalized = new Normalizer()->denormalizeUnion(
        data: ['type' => 'test2', 'message' => 'hello'],
        unionType: new UnionType([
            new InterfaceType(TestInterface::class),
            new InterfaceType(TestInterface2::class),
        ])
    );

    expect($denormalized)->toBeInstanceOf(Test2::class);
    expect($denormalized->message)->toBe('hello');
});

test('default implementation', function () {
    $denormalized = new Normalizer()->denormalizeUnion(
        data: ['type' => 'test5', 'message' => 'hello'],
        unionType: new UnionType([
            new InterfaceType(TestInterface::class),
            new InterfaceType(TestInterface2::class),
        ])
    );

    expect($denormalized)->toBeInstanceOf(Test5::class);
    expect($denormalized->message)->toBe('hello');
});
