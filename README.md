# type-hint-hydrator
A Symfony Type Hint hydrator that uses @var annotations to determine the hydrated type.
Under the hood it uses the laminas-hydrator for the mapping process. See https://github.com/laminas/laminas-hydrator/

It can handle request objects and hydrate classes and arrays etc. It can also validate against any Assert annotations the hydrated object may contain.

## Documentation
-------------
### 1) Add the type-hint-hydrator to your project

```bash
composer require xactsystems/type-hint-hydrator
```

### 2) Add the bundle to your configuration file (bundles.php/AppKernel.php)
If you are using Symfony 4 onwards and using Flex you can skip this step.

Symfony 3.4 - AppKernel.php
```php
     $bundles = [
        ...
        new Xact\TypeHintHydrator\XactTypeHintHydrator()
     ];

```

Symfony 4.4 onwards - bundles.php
```php
    return [
        ...
        Xact\TypeHintHydrator\XactTypeHintHydrator::class => ['all' => true],
    ];
```

### 3) Annotate your hydrated object properties
```php
namespace App;

class Author
{
    /**
     * @var int|null
     */
    public $objectID;

    /**
     * @var string|null
     */
    public $fullName;

    /**
     * \App\Book[]
     */
    public $books;
}
```

### 4) Hydrate your object in your controller
```php
    public function update(Request $request, EntityManagerInterface $em, TypeHintHydrator $hydrator): JsonResponse
    {
        $author = new Author();
        $hydrator->handleRequest($request, $author);
        if (!$hydrator->isValid()) {
            return JsonResponse::fromJsonString($hydrator->getJsonErrors(), JsonResponse::HTTP_BAD_REQUEST);
        }

        $em->persist($author);
        $em->flush();

        return new JsonResponse::fromJsonString(json_encode($author));
    }
```

### 5) Smile and be happy
It really is that easy. No more form types! Annotate your objects correctly with types and assertions, make sure your submitted forms use the same names as your object proprieties and it will just work!

Create proper Model classes for your data and hydrate them, and let them do the work a proper MVC model should.

## Methods

### hydrateObject
```php
hydrateObject(array $values, object $target, bool $validate = true, $constraints = null, $groups = null): object
```
Hydrate an object from an array of values. If $validate is true, the hydrated object is validated against annotations and supplied validation constraints and groups.


### handleRequest
```php
handleRequest(Request $request, object $target, bool $validate = true, $constraints = null, $groups = null): object
```
Hydrate an object from the Request object. Property mapping is based on the submitted form property names matching the property names of the hydrated object. If $validate is true, the hydrated object is validated against annotations and supplied validation constraints and groups.


### isValid
```php
isValid()
```
Is the hydrated object valid after processing the validation constraints. If no validation has occurred the method returns true;


### getErrors
```php
getErrors()
```
Return a Symfony\Component\Validator\ConstraintViolationListInterface list of any validation errors.


### getJsonErrors
```php
getJsonErrors()
```
Return a JSON serialised version of getErrors().


## Credits
-------

* Ian Foulds as the creator of this package.
* Marco Pivetta (https://github.com/ocramius) for developing and maintaining the laminas-hydrator - https://github.com/laminas/laminas-hydrator.

## License
-------

This bundle is released under the MIT license. See the complete license in the
bundle:

[LICENSE](https://github.com/xactsystems/type-hint-hydrator/blob/master/LICENSE)

