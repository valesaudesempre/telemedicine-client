<?php

use ValeSaude\TelemedicineClient\Tests\Dummies\DummyCollection;
use ValeSaude\TelemedicineClient\Tests\Dummies\DummyCollectionSubject;

function getDummyCollectionInstance(): DummyCollection
{
    return new DummyCollection([
        new DummyCollectionSubject('item-1'),
        new DummyCollectionSubject('item-2'),
        new DummyCollectionSubject('item-3'),
    ]);
}

test('constructor throws InvalidArgumentException when some of the items does not match subject class', function () {
    new DummyCollection([
        new DummyCollectionSubject('item-1'),
        new stdClass(),
        new DummyCollectionSubject('item-2'),
    ]);
})->throws(InvalidArgumentException::class, 'Every item must be an instance of DummyCollectionSubject.');

test('constructor initializes the collection with given items discarding keys', function () {
    // Given
    $collection = DummyCollection::make([
        'item-1' => new DummyCollectionSubject('item-1'),
        'item-2' => new DummyCollectionSubject('item-2'),
    ]);

    // Then
    expect($collection)->toHaveCount(2)
        ->and($collection->at(0))->name->toEqual('item-1')
        ->and($collection->at(1))->name->toEqual('item-2')
        ->and($collection)->each->toBeInstanceOf(DummyCollectionSubject::class)
        ->and($collection->getItems())->toHaveKeys([0, 1]);
});

test('jsonSerialize returns the array of items', function () {
    // Given
    $collection = getDummyCollectionInstance();

    // When
    $items = $collection->jsonSerialize();

    // Then
    expect($items)->toBeArray()
        ->toHaveCount(3)
        ->each->toBeInstanceOf(DummyCollectionSubject::class);
});

test('getIterator returns an ArrayIterator with collection items', function () {
    // Given
    $collection = getDummyCollectionInstance();

    // When
    $iterator = $collection->getIterator();

    // Then
    expect($iterator)->toBeInstanceOf(ArrayIterator::class)
        ->toHaveCount(3)
        ->each->toBeInstanceOf(DummyCollectionSubject::class);
});

test('count returns the item count', function () {
    // Given
    $collection = getDummyCollectionInstance();

    // When
    $count = $collection->count();

    // Then
    expect($count)->toEqual(3);
});

test('add throws InvalidArgumentException when item does not match subject class', function () {
    // Given
    $collection = new DummyCollection();

    // When
    $collection->add(new stdClass());
})->throws(InvalidArgumentException::class, 'The item must be an instance of DummyCollectionSubject.');

test('add adds an item to the collection', function () {
    // Given
    $collection = getDummyCollectionInstance();

    // When
    $collection->add(new DummyCollectionSubject('item-4'))
        ->add(new DummyCollectionSubject('item-5'));

    // Then
    expect($collection)->toHaveCount(5);
});

test('map returns array containing every item mapped using given callback', function () {
    // Given
    $collection = getDummyCollectionInstance();

    // When
    $mapped = $collection->map(fn (DummyCollectionSubject $item) => $item->name);

    // Then
    expect($mapped)->toBeArray()
        ->toHaveCount(3)
        ->and($mapped[0])->toEqual('item-1')
        ->and($mapped[1])->toEqual('item-2')
        ->and($mapped[2])->toEqual('item-3');
});

test('filter returns a filtered collection instance without modifying the original', function () {
    // Given
    $collection = getDummyCollectionInstance();

    // When
    $filtered = $collection->filter(fn (DummyCollectionSubject $item) => $item->name !== 'item-2');

    // Then
    expect($collection)->toHaveCount(3)
        ->and($filtered)->toHaveCount(2)
        ->and($filtered->at(0))->name->toEqual('item-1')
        ->and($filtered->at(1))->name->toEqual('item-3');
});

test('contains returns true when collection contains a given item based on callback', function () {
    // Given
    $collection = getDummyCollectionInstance();

    // When
    $containsItem1 = $collection->contains(fn (DummyCollectionSubject $item) => $item->name === 'item-1');
    $containsItem4 = $collection->contains(fn (DummyCollectionSubject $item) => $item->name === 'item-4');

    // Then
    expect($containsItem1)->toBeTrue()
        ->and($containsItem4)->toBeFalse();
});

test('at throws OutOfBoundsException when index is does not exist in items array', function () {
    // Given
    $collection = getDummyCollectionInstance();

    // When
    $itemAt1 = $collection->at(3);
})->throws(OutOfBoundsException::class, 'The given index does not exist in items array.');

test('at returns item at given index', function () {
    // Given
    $collection = getDummyCollectionInstance();

    // When
    $itemAt1 = $collection->at(1);

    // Then
    expect($itemAt1)->name->toEqual('item-2');
});

test('getItems returns the array of items', function () {
    // Given
    $collection = getDummyCollectionInstance();

    // When
    $items = $collection->getItems();

    // Then
    expect($items)->toBeArray()
        ->toHaveCount(3)
        ->each->toBeInstanceOf(DummyCollectionSubject::class);
});

test('isEmpty returns true whether the collection is empty', function () {
    // Given
    $collection = new DummyCollection();

    // When
    $isEmpty = $collection->isEmpty();

    // Then
    expect($isEmpty)->toBeTrue();
});

test('sort returns a new collection instance sorted by given callback', function () {
    // Given
    $collection = new DummyCollection([
        new DummyCollectionSubject('item-3'),
        new DummyCollectionSubject('item-1'),
        new DummyCollectionSubject('item-2'),
    ]);

    // When
    $sorted = $collection->sort(fn (DummyCollectionSubject $a, DummyCollectionSubject $b) => $a->name <=> $b->name);

    // Then
    expect($sorted)->toHaveCount(3)
        ->and($sorted->at(0))->name->toEqual('item-1')
        ->and($sorted->at(1))->name->toEqual('item-2')
        ->and($sorted->at(2))->name->toEqual('item-3');
});

test('make returns a new instance containing given items', function () {
    // Given
    $collection = DummyCollection::make([
        new DummyCollectionSubject('item-1'),
        new DummyCollectionSubject('item-2'),
    ]);

    // Then
    expect($collection)->toHaveCount(2)
        ->and($collection->at(0))->name->toEqual('item-1')
        ->and($collection->at(1))->name->toEqual('item-2')
        ->and($collection)->each->toBeInstanceOf(DummyCollectionSubject::class);
});