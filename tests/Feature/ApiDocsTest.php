<?php

use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('viewApiDocs', fn ($user = null) => true);
});

it('forbids the docs outside local unless the gate allows it', function () {
    Gate::define('viewApiDocs', fn ($user = null) => false);

    $this->get('/docs/api.json')->assertForbidden();
});

it('serves an OpenAPI document with the product endpoints', function () {
    $this->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('openapi', fn (string $version) => str_starts_with($version, '3.'))
        ->assertJsonPath('paths./v1/products.get.operationId', 'v1.products.index')
        ->assertJsonPath('paths./v1/products/{product}.get.operationId', 'v1.products.show');
});

it('documents the ListProductsRequest query parameters', function () {
    $parameters = collect($this->getJson('/docs/api.json')->json('paths./v1/products.get.parameters'))
        ->keyBy('name');

    expect($parameters->keys()->all())->toEqualCanonicalizing([
        'search', 'platform[]', 'region[]', 'type[]', 'min_price', 'max_price', 'per_page', 'page',
    ])
        ->and($parameters->every(fn (array $parameter) => $parameter['in'] === 'query'))->toBeTrue()
        ->and($parameters->every(fn (array $parameter) => ($parameter['required'] ?? false) === false))->toBeTrue()
        ->and($parameters->every(fn (array $parameter) => filled($parameter['description'] ?? null)))->toBeTrue()
        ->and($parameters['search']['schema']['maxLength'])->toBe(100)
        ->and($parameters['min_price']['schema'])->toMatchArray(['minimum' => 0])
        ->and($parameters['max_price']['schema'])->toMatchArray(['minimum' => 0])
        ->and($parameters['per_page']['schema'])->toMatchArray(['minimum' => 1, 'maximum' => 100, 'default' => 20]);

    foreach (['platform[]' => 'Platform', 'region[]' => 'Region', 'type[]' => 'ProductType'] as $name => $enum) {
        expect($parameters[$name]['schema']['type'])->toContain('array')
            ->and($parameters[$name]['schema']['items']['$ref'])->toBe("#/components/schemas/{$enum}");
    }
});

it('documents the filters endpoint with ProductFilterOptionsResource', function () {
    $document = $this->getJson('/docs/api.json')
        ->assertJsonPath('paths./v1/products/filters.get.operationId', 'v1.products.filters.index')
        ->assertJsonPath(
            'paths./v1/products/filters.get.responses.200.content.application/json.schema.properties.data.$ref',
            '#/components/schemas/ProductFilterOptionsResource',
        )
        ->json();

    $filters = $document['components']['schemas']['ProductFilterOptionsResource'];
    $option = $document['components']['schemas']['FilterOptionResource'];

    expect($filters['required'])->toEqualCanonicalizing(['platforms', 'regions', 'types', 'price', 'currency']);

    foreach (['platforms', 'regions', 'types'] as $group) {
        expect($filters['properties'][$group]['type'])->toBe('array')
            ->and($filters['properties'][$group]['items']['$ref'])->toBe('#/components/schemas/FilterOptionResource');
    }

    foreach (['min', 'max'] as $bound) {
        $schema = $filters['properties']['price']['properties'][$bound];

        expect($schema['type'])->toBe(['object', 'null'])
            ->and($schema['properties']['amount']['type'])->toBe('integer')
            ->and($schema['properties']['formatted']['type'])->toBe('string');
    }

    expect($option['required'])->toEqualCanonicalizing(['value', 'label', 'count'])
        ->and($option['properties']['value']['type'])->toBe('string')
        ->and($option['properties']['label']['type'])->toBe('string')
        ->and($option['properties']['count']['type'])->toBe('integer');
});

it('documents the index response as a paginated ProductResource collection', function () {
    $schema = $this->getJson('/docs/api.json')
        ->assertJsonPath('paths./v1/products.get.responses.422.$ref', '#/components/responses/ValidationException')
        ->json('paths./v1/products.get.responses.200.content.application/json.schema');

    expect($schema['required'])->toEqualCanonicalizing(['data', 'links', 'meta'])
        ->and($schema['properties']['data']['items']['$ref'])->toBe('#/components/schemas/ProductResource')
        ->and($schema['properties']['meta']['properties'])->toHaveKeys(['current_page', 'last_page', 'per_page', 'total']);
});

it('documents the show endpoint by slug', function () {
    $operation = $this->getJson('/docs/api.json')->json('paths./v1/products/{product}.get');

    expect($operation['parameters'][0])->toMatchArray(['name' => 'product', 'in' => 'path', 'required' => true])
        ->and($operation['responses']['200']['content']['application/json']['schema']['properties']['data']['$ref'])
        ->toBe('#/components/schemas/ProductResource')
        ->and($operation['responses'])->toHaveKey('404');
});

it('describes the ProductResource schema', function () {
    $resource = $this->getJson('/docs/api.json')->json('components.schemas.ProductResource');
    $properties = $resource['properties'];

    expect($resource['required'])->toEqualCanonicalizing([
        'id', 'title', 'slug', 'platform', 'region', 'type', 'price', 'original_price',
        'cashback', 'discount_percent', 'currency', 'image_url', 'created_at', 'updated_at',
    ])
        ->and($properties['price']['type'])->toBe('object')
        ->and($properties['original_price']['properties']['amount']['type'])->toBe('integer')
        ->and($properties['price']['properties']['amount']['type'])->toBe('integer')
        ->and($properties['price']['properties']['formatted']['type'])->toBe('string')
        ->and($properties['original_price']['type'])->toBe(['object', 'null'])
        ->and($properties['cashback']['type'])->toBe(['object', 'null'])
        ->and($properties['discount_percent']['type'])->toBe('integer')
        ->and($properties['platform']['properties']['value']['enum'])->toContain('steam', 'playstation')
        ->and($properties['region']['properties']['value']['enum'])->toContain('global', 'europe')
        ->and($properties['type']['properties']['value']['enum'])->toContain('game', 'dlc')
        ->and($properties['created_at']['format'])->toBe('date-time');
});
