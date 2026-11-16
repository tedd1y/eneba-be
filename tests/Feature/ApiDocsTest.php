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
        'ids[]', 'search', 'platform[]', 'region[]', 'country', 'type[]', 'genre[]', 'os[]',
        'min_price', 'max_price', 'sort', 'per_page', 'page',
    ])
        ->and($parameters->every(fn (array $parameter) => $parameter['in'] === 'query'))->toBeTrue()
        ->and($parameters->every(fn (array $parameter) => ($parameter['required'] ?? false) === false))->toBeTrue()
        ->and($parameters->every(fn (array $parameter) => filled($parameter['description'] ?? null)))->toBeTrue()
        ->and($parameters['ids[]']['schema'])->toMatchArray([
            'type' => ['array', 'null'],
            'items' => ['type' => 'integer', 'minimum' => 1],
            'maxItems' => 100,
            'uniqueItems' => true,
        ])
        ->and($parameters['search']['schema']['maxLength'])->toBe(100)
        ->and($parameters['min_price']['schema'])->toMatchArray(['minimum' => 0])
        ->and($parameters['max_price']['schema'])->toMatchArray(['minimum' => 0])
        ->and($parameters['per_page']['schema'])->toMatchArray(['minimum' => 1, 'maximum' => 100, 'default' => 20])
        ->and($parameters['sort']['schema']['anyOf'][0])->toMatchArray(['$ref' => '#/components/schemas/ProductSort', 'default' => 'title']);

    foreach (['platform[]', 'region[]', 'type[]', 'genre[]', 'os[]'] as $name) {
        expect($parameters[$name]['schema'])->toBe(['type' => ['array', 'null'], 'items' => ['type' => 'string']]);
    }

    expect($parameters['country']['schema'])->toBe(['type' => ['string', 'null']])
        ->and($parameters['country']['example'])->toBe('lt');
});

it('documents the ProductSort enum', function () {
    $this->getJson('/docs/api.json')
        ->assertJsonPath('components.schemas.ProductSort.type', 'string')
        ->assertJsonPath('components.schemas.ProductSort.enum', ['title', '-title', 'price', '-price', 'created_at', '-created_at']);
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
    $featuredOption = $document['components']['schemas']['FeaturedFilterOptionResource'];

    expect($filters['required'])->toEqualCanonicalizing([
        'platforms', 'regions', 'countries', 'types', 'genres', 'operating_systems', 'price', 'currency',
    ]);

    $groups = [
        'platforms' => 'FeaturedFilterOptionResource',
        'regions' => 'FilterOptionResource',
        'countries' => 'FilterOptionResource',
        'types' => 'FeaturedFilterOptionResource',
        'genres' => 'FeaturedFilterOptionResource',
        'operating_systems' => 'FilterOptionResource',
    ];

    foreach ($groups as $group => $schema) {
        expect($filters['properties'][$group]['type'])->toBe('array')
            ->and($filters['properties'][$group]['items']['$ref'])->toBe("#/components/schemas/{$schema}");
    }

    expect($featuredOption['required'])->toEqualCanonicalizing(['value', 'label', 'count', 'icon', 'is_featured'])
        ->and($featuredOption['properties']['icon']['type'])->toBe(['string', 'null'])
        ->and($featuredOption['properties']['is_featured']['type'])->toBe('boolean');

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

it('documents the show endpoint by slug with ProductDetailResource', function () {
    $operation = $this->getJson('/docs/api.json')->json('paths./v1/products/{product}.get');

    expect($operation['parameters'][0])->toMatchArray(['name' => 'product', 'in' => 'path', 'required' => true])
        ->and($operation['responses']['200']['content']['application/json']['schema']['properties']['data']['$ref'])
        ->toBe('#/components/schemas/ProductDetailResource')
        ->and($operation['responses'])->toHaveKey('404');
});

it('describes ProductDetailResource as ProductResource plus description, genres and operating systems', function () {
    $schemas = $this->getJson('/docs/api.json')->json('components.schemas');
    $detail = $schemas['ProductDetailResource'];
    $base = $schemas['ProductResource'];
    $detailOnly = ['description', 'genres', 'operating_systems'];

    expect(array_keys($detail['properties']))->toBe([...array_keys($base['properties']), ...$detailOnly])
        ->and($detail['required'])->toEqualCanonicalizing([...$base['required'], ...$detailOnly])
        ->and($detail['properties']['description']['type'])->toBe(['string', 'null'])
        ->and($detail['properties']['genres']['type'])->toBe('array')
        ->and($detail['properties']['genres']['items']['$ref'])->toBe('#/components/schemas/LookupResource')
        ->and($detail['properties']['operating_systems']['type'])->toBe('array')
        ->and($detail['properties']['operating_systems']['items']['$ref'])->toBe('#/components/schemas/LookupResource')
        ->and($detail['properties']['description']['description'])->not->toBeEmpty()
        ->and($detail['properties']['price'])->toBe($base['properties']['price'])
        ->and($base['properties'])->not->toHaveKeys($detailOnly);
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
        ->and($properties['created_at']['format'])->toBe('date-time');

    foreach (['platform', 'region', 'type'] as $lookup) {
        expect($properties[$lookup]['$ref'])->toBe('#/components/schemas/LookupResource');
    }
});

it('describes the LookupResource value/label schema', function () {
    $lookup = $this->getJson('/docs/api.json')->json('components.schemas.LookupResource');

    expect($lookup['required'])->toEqualCanonicalizing(['value', 'label'])
        ->and($lookup['properties']['value']['type'])->toBe('string')
        ->and($lookup['properties']['label']['type'])->toBe('string');
});

it('no longer documents the removed platform, region and product type enums', function () {
    expect($this->getJson('/docs/api.json')->json('components.schemas'))
        ->not->toHaveKeys(['Platform', 'Region', 'ProductType']);
});

it('documents placing an order with StoreOrderRequest and a 201 OrderResource', function () {
    $document = $this->getJson('/docs/api.json')->json();
    $operation = $document['paths']['/v1/orders']['post'];
    $request = $document['components']['schemas']['StoreOrderRequest'];
    $item = $request['properties']['items'];

    expect($operation['operationId'])->toBe('v1.orders.store')
        ->and($operation['requestBody']['content']['application/json']['schema']['$ref'])->toBe('#/components/schemas/StoreOrderRequest')
        ->and($operation['responses'])->toHaveKeys(['201', '422', '429'])
        ->and($operation['responses'])->not->toHaveKey('200')
        ->and($operation['responses']['201']['content']['application/json']['schema']['properties']['data']['$ref'])->toBe('#/components/schemas/OrderResource')
        ->and($request['required'])->toEqualCanonicalizing(['email', 'items'])
        ->and($request['properties']['email'])->toMatchArray(['type' => 'string', 'format' => 'email', 'maxLength' => 255])
        ->and($item)->toMatchArray(['type' => 'array', 'minItems' => 1, 'maxItems' => 40])
        ->and($item['items']['required'])->toEqualCanonicalizing(['product_id', 'quantity'])
        ->and($item['items']['properties']['product_id'])->toMatchArray(['type' => 'integer', 'minimum' => 1])
        ->and($item['items']['properties']['quantity'])->toMatchArray(['type' => 'integer', 'minimum' => 1, 'maximum' => 10]);
});

it('documents showing and paying an order by its number', function () {
    $paths = $this->getJson('/docs/api.json')->json('paths');
    $show = $paths['/v1/orders/{order}']['get'];
    $pay = $paths['/v1/orders/{order}/pay']['post'];

    foreach ([$show, $pay] as $operation) {
        expect($operation['parameters'][0])->toMatchArray(['name' => 'order', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']])
            ->and($operation['responses']['200']['content']['application/json']['schema']['properties']['data']['$ref'])->toBe('#/components/schemas/OrderResource')
            ->and($operation['responses'])->toHaveKey('404');
    }

    expect($show['operationId'])->toBe('v1.orders.show')
        ->and($pay['operationId'])->toBe('v1.orders.pay')
        ->and($pay['responses'])->toHaveKeys(['409', '429']);
});

it('describes the OrderResource and OrderItemResource schemas without internal IDs', function () {
    $schemas = $this->getJson('/docs/api.json')->json('components.schemas');
    $order = $schemas['OrderResource'];
    $item = $schemas['OrderItemResource'];

    expect($order['required'])->toEqualCanonicalizing([
        'number', 'email', 'status', 'subtotal', 'cashback', 'currency', 'items', 'paid_at', 'created_at', 'updated_at',
    ])
        ->and($order['properties']['status']['properties']['value']['$ref'])->toBe('#/components/schemas/OrderStatus')
        ->and($order['properties']['status']['properties']['label']['type'])->toBe('string')
        ->and($order['properties']['items']['items']['$ref'])->toBe('#/components/schemas/OrderItemResource')
        ->and($order['properties']['paid_at']['type'])->toBe(['string', 'null'])
        ->and($item['required'])->toEqualCanonicalizing([
            'product_id', 'title', 'platform', 'region', 'unit_price', 'unit_cashback', 'quantity', 'line_total',
        ])
        ->and($item['properties']['product_id']['type'])->toBe(['integer', 'null'])
        ->and($order['properties'])->not->toHaveKey('id')
        ->and($item['properties'])->not->toHaveKeys(['id', 'order_id']);

    foreach (['subtotal', 'cashback'] as $money) {
        expect($order['properties'][$money]['properties']['amount']['type'])->toBe('integer')
            ->and($order['properties'][$money]['properties']['formatted']['type'])->toBe('string');
    }

    foreach (['unit_price', 'unit_cashback', 'line_total'] as $money) {
        expect($item['properties'][$money]['type'])->toBe('object')
            ->and($item['properties'][$money]['properties']['amount']['type'])->toBe('integer');
    }

    expect($schemas['OrderStatus']['enum'])->toBe(['pending', 'paid', 'completed', 'failed', 'cancelled']);
});
